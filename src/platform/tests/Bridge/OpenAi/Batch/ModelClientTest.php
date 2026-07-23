<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Batch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\ModelClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testGetBatchMapsCompletedStatusAndCounts()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_done',
            'status' => 'completed',
            'request_counts' => ['total' => 10, 'completed' => 7, 'failed' => 3],
            'output_file_id' => 'file-output-123',
        ])));

        $client = new ModelClient($httpClient, 'sk-test-key');
        $job = $client->getBatch('batch_done');

        $this->assertSame('batch_done', $job->getId());
        $this->assertTrue($job->isComplete());
        $this->assertSame(10, $job->getTotalCount());
        $this->assertSame(10, $job->getProcessedCount());
        $this->assertSame(3, $job->getFailedCount());
        $this->assertSame('file-output-123', $job->getOutputFileId());
    }

    #[DataProvider('provideStatuses')]
    public function testGetBatchMapsAllStatuses(string $apiStatus, BatchStatus $expected)
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_1',
            'status' => $apiStatus,
            'request_counts' => ['total' => 0, 'completed' => 0, 'failed' => 0],
        ])));

        $client = new ModelClient($httpClient, 'sk-test-key');

        $this->assertSame($expected, $client->getBatch('batch_1')->getStatus());
    }

    public static function provideStatuses(): iterable
    {
        yield 'completed' => ['completed', BatchStatus::COMPLETED];
        yield 'failed' => ['failed', BatchStatus::FAILED];
        yield 'cancelled' => ['cancelled', BatchStatus::CANCELLED];
        yield 'expired' => ['expired', BatchStatus::EXPIRED];
        yield 'validating' => ['validating', BatchStatus::PROCESSING];
        yield 'in_progress' => ['in_progress', BatchStatus::PROCESSING];
        yield 'finalizing' => ['finalizing', BatchStatus::PROCESSING];
        yield 'cancelling' => ['cancelling', BatchStatus::PROCESSING];
    }

    public function testCancelBatchMapsStatus()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_c',
            'status' => 'cancelling',
            'request_counts' => ['total' => 2, 'completed' => 0, 'failed' => 0],
        ])));

        $client = new ModelClient($httpClient, 'sk-test-key');

        $this->assertTrue($client->cancelBatch('batch_c')->isProcessing());
    }

    public function testCanFetchResultsReflectsAvailableOutput()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');

        $this->assertTrue($client->canFetchResults(new BatchJob('b', BatchStatus::COMPLETED, outputFileId: 'file-out')));
        $this->assertTrue($client->canFetchResults(new BatchJob('b', BatchStatus::CANCELLED, errorFileId: 'file-err')));
        $this->assertFalse($client->canFetchResults(new BatchJob('b', BatchStatus::COMPLETED)));
    }

    public function testFetchResultsThrowsForIncompleteJob()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot fetch results for batch "batch_123"');

        iterator_to_array($client->fetchResults(new BatchJob('batch_123', BatchStatus::PROCESSING)));
    }

    public function testFetchResultsThrowsWhenNoOutputNorErrorFile()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no output available');

        iterator_to_array($client->fetchResults(new BatchJob('batch_123', BatchStatus::COMPLETED)));
    }

    public function testFetchResultsStreamsOutputAndErrorFilesWithRealMessage()
    {
        $ok = json_encode(['custom_id' => 'req-1', 'response' => ['status_code' => 200, 'body' => [
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Paris']]]],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 1],
        ]]]);
        $err = json_encode(['custom_id' => 'req-2', 'response' => ['status_code' => 400, 'body' => ['error' => ['message' => 'Invalid schema']]]]);

        $httpClient = new MockHttpClient([new MockResponse($ok."\n"), new MockResponse($err."\n")]);
        $client = new ModelClient($httpClient, 'sk-test-key');

        $results = iterator_to_array($client->fetchResults(new BatchJob('batch_x', BatchStatus::COMPLETED, outputFileId: 'file-out', errorFileId: 'file-err')));

        $this->assertCount(2, $results);
        $this->assertSame('Paris', $results[0]->getContent());
        $this->assertFalse($results[1]->isSuccess());
        $this->assertSame('Invalid schema', $results[1]->getError());
    }

    public function testFetchResultsSurfacesRefusal()
    {
        $line = json_encode(['custom_id' => 'req-1', 'response' => ['status_code' => 200, 'body' => [
            'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'I cannot help with that']]]],
            'usage' => [],
        ]]]);

        $httpClient = new MockHttpClient(new MockResponse($line."\n"));
        $client = new ModelClient($httpClient, 'sk-test-key');

        $results = iterator_to_array($client->fetchResults(new BatchJob('batch_x', BatchStatus::COMPLETED, outputFileId: 'file-out')));

        $this->assertTrue($results[0]->isSuccess());
        $this->assertStringContainsString('Model refused to generate output: I cannot help with that', $results[0]->getContent());
    }

    public function testFetchResultsAllowedForCancelledBatchWithOutput()
    {
        $line = json_encode(['custom_id' => 'req-1', 'response' => ['status_code' => 200, 'body' => [
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Paris']]]],
            'usage' => [],
        ]]]);

        $httpClient = new MockHttpClient(new MockResponse($line."\n"));
        $client = new ModelClient($httpClient, 'sk-test-key');

        $results = iterator_to_array($client->fetchResults(new BatchJob('batch_x', BatchStatus::CANCELLED, outputFileId: 'file-out')));

        $this->assertCount(1, $results);
        $this->assertSame('Paris', $results[0]->getContent());
    }

    public function testFetchResultsStreamsResponsesFormat()
    {
        $line1 = json_encode(['custom_id' => 'req-1', 'response' => ['status_code' => 200, 'body' => [
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Paris']]]],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 1],
        ]]]);
        $line2 = json_encode(['custom_id' => 'req-2', 'response' => ['status_code' => 500]]);

        $httpClient = new MockHttpClient(new MockResponse($line1."\n".$line2."\n"));
        $client = new ModelClient($httpClient, 'sk-test-key');

        $results = iterator_to_array($client->fetchResults(new BatchJob('batch_ok', BatchStatus::COMPLETED, outputFileId: 'file-out')));

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertSame('req-1', $results[0]->getId());
        $this->assertSame('Paris', $results[0]->getContent());
        $this->assertSame(5, $results[0]->getInputTokens());
        $this->assertSame(1, $results[0]->getOutputTokens());
        $this->assertFalse($results[1]->isSuccess());
        $this->assertSame('HTTP 500', $results[1]->getError());
    }
}
