<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic\Batch;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\Bridge\Anthropic\Batch\ModelClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testGetBatchMapsEndedToCompleted()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_done',
            'processing_status' => 'ended',
            'request_counts' => ['processing' => 0, 'succeeded' => 3, 'errored' => 1, 'canceled' => 0, 'expired' => 0],
        ])));

        $client = new ModelClient($httpClient, 'test-key');
        $job = $client->getBatch('msgbatch_done');

        $this->assertTrue($job->isComplete());
        $this->assertSame(4, $job->getTotalCount());
        $this->assertSame(4, $job->getProcessedCount());
        $this->assertSame(1, $job->getFailedCount());
    }

    public function testGetBatchMapsEndedWithCancelledToCancelled()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_cancelled',
            'processing_status' => 'ended',
            'request_counts' => ['processing' => 0, 'succeeded' => 2, 'errored' => 0, 'canceled' => 5, 'expired' => 0],
        ])));

        $client = new ModelClient($httpClient, 'test-key');

        $this->assertSame(BatchStatus::CANCELLED, $client->getBatch('msgbatch_cancelled')->getStatus());
    }

    public function testGetBatchMapsInProgressToProcessing()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_running',
            'processing_status' => 'in_progress',
            'request_counts' => ['processing' => 5, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
        ])));

        $client = new ModelClient($httpClient, 'test-key');

        $this->assertTrue($client->getBatch('msgbatch_running')->isProcessing());
    }

    public function testCancelBatchMapsCancelingToProcessing()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_c',
            'processing_status' => 'canceling',
            'request_counts' => ['processing' => 2, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
        ])));

        $client = new ModelClient($httpClient, 'test-key');

        $this->assertTrue($client->cancelBatch('msgbatch_c')->isProcessing());
    }

    public function testCanFetchResultsReflectsTerminalState()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->canFetchResults(new BatchJob('b', BatchStatus::COMPLETED)));
        $this->assertTrue($client->canFetchResults(new BatchJob('b', BatchStatus::CANCELLED)));
        $this->assertFalse($client->canFetchResults(new BatchJob('b', BatchStatus::PROCESSING)));
    }

    public function testFetchResultsThrowsForIncompleteJob()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot fetch results for batch "msgbatch_1"');

        iterator_to_array($client->fetchResults(new BatchJob('msgbatch_1', BatchStatus::PROCESSING)));
    }

    public function testExtractsTextSkippingThinkingBlocks()
    {
        $line = json_encode(['custom_id' => 'req-1', 'result' => [
            'type' => 'succeeded',
            'message' => ['content' => [
                ['type' => 'thinking', 'thinking' => 'let me think...'],
                ['type' => 'text', 'text' => 'Paris'],
            ], 'usage' => ['input_tokens' => 5, 'output_tokens' => 1]],
        ]]);

        $httpClient = new MockHttpClient(new MockResponse($line."\n"));
        $client = new ModelClient($httpClient, 'test-key');

        $results = iterator_to_array($client->fetchResults(new BatchJob('msgbatch_ok', BatchStatus::COMPLETED)));

        $this->assertSame('Paris', $results[0]->getContent());
    }

    public function testFetchResultsAllowedForCancelledEndedBatch()
    {
        $line = json_encode(['custom_id' => 'req-1', 'result' => [
            'type' => 'succeeded',
            'message' => ['content' => [['type' => 'text', 'text' => 'Paris']], 'usage' => []],
        ]]);

        $httpClient = new MockHttpClient(new MockResponse($line."\n"));
        $client = new ModelClient($httpClient, 'test-key');

        $results = iterator_to_array($client->fetchResults(new BatchJob('msgbatch_c', BatchStatus::CANCELLED)));

        $this->assertCount(1, $results);
        $this->assertSame('Paris', $results[0]->getContent());
    }

    public function testFetchResultsStreamsSuccessAndError()
    {
        $line1 = json_encode(['custom_id' => 'req-1', 'result' => [
            'type' => 'succeeded',
            'message' => ['content' => [['type' => 'text', 'text' => 'Paris']], 'usage' => ['input_tokens' => 5, 'output_tokens' => 1]],
        ]]);
        $line2 = json_encode(['custom_id' => 'req-2', 'result' => [
            'type' => 'errored',
            'error' => ['message' => 'Rate limit exceeded'],
        ]]);

        $httpClient = new MockHttpClient(new MockResponse($line1."\n".$line2."\n"));
        $client = new ModelClient($httpClient, 'test-key');

        $results = iterator_to_array($client->fetchResults(new BatchJob('msgbatch_ok', BatchStatus::COMPLETED)));

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertSame('Paris', $results[0]->getContent());
        $this->assertSame(5, $results[0]->getInputTokens());
        $this->assertFalse($results[1]->isSuccess());
        $this->assertSame('Rate limit exceeded', $results[1]->getError());
    }
}
