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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Batch\BatchJob;
use Symfony\AI\Platform\Batch\BatchStatus;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\ModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testSupportsGptWithBatchCapability()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');
        $model = new Gpt('gpt-4o-mini', [Capability::BATCH]);

        $this->assertTrue($client->supports($model));
    }

    public function testDoesNotSupportGptWithoutBatchCapability()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');
        $model = new Gpt('gpt-4o-mini', [Capability::INPUT_MESSAGES]);

        $this->assertFalse($client->supports($model));
    }

    public function testDoesNotSupportNonGptModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');
        $model = new Claude(Claude::SONNET_4, [Capability::BATCH]);

        $this->assertFalse($client->supports($model));
    }

    public function testSubmitBatchUploadsFileAndCreatesBatch()
    {
        $calls = 0;
        $capturedUrls = [];
        $capturedBatchBody = null;

        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$calls, &$capturedUrls, &$capturedBatchBody) {
            ++$calls;
            $capturedUrls[] = $url;

            if (1 === $calls) {
                // File upload
                return new MockResponse(json_encode(['id' => 'file-abc123']));
            }

            // Batch creation
            $capturedBatchBody = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'id' => 'batch_xyz789',
                'status' => 'validating',
                'request_counts' => ['total' => 2, 'completed' => 0, 'failed' => 0],
            ]));
        });

        $client = new ModelClient($httpClient, 'sk-test-key');
        $model = new Gpt('gpt-4o-mini', [Capability::BATCH]);

        $job = $client->submitBatch($model, [
            ['id' => 'req-1', 'payload' => ['messages' => [['role' => 'user', 'content' => 'Hello']], 'max_tokens' => 50]],
            ['id' => 'req-2', 'payload' => ['messages' => [['role' => 'user', 'content' => 'World']], 'max_tokens' => 50]],
        ]);

        $this->assertSame(2, $calls);
        $this->assertStringEndsWith('/v1/files', $capturedUrls[0]);
        $this->assertStringEndsWith('/v1/batches', $capturedUrls[1]);
        $this->assertSame('file-abc123', $capturedBatchBody['input_file_id']);
        $this->assertSame('/v1/chat/completions', $capturedBatchBody['endpoint']);
        $this->assertSame('batch_xyz789', $job->getId());
        $this->assertTrue($job->isProcessing());
    }

    public function testSubmitBatchHandlesLargeNumberOfRequests()
    {
        $requestCount = 10000;

        $largeCalls = 0;
        $httpClient = new MockHttpClient(static function ($method, $url) use (&$largeCalls, $requestCount) {
            ++$largeCalls;

            if (1 === $largeCalls) {
                return new MockResponse(json_encode(['id' => 'file-large']));
            }

            return new MockResponse(json_encode([
                'id' => 'batch_large',
                'status' => 'validating',
                'request_counts' => ['total' => $requestCount, 'completed' => 0, 'failed' => 0],
            ]));
        });

        $requests = [];
        for ($i = 0; $i < $requestCount; ++$i) {
            $requests[] = ['id' => "req-{$i}", 'payload' => ['messages' => [['role' => 'user', 'content' => "Question {$i}"]]]];
        }

        $client = new ModelClient($httpClient, 'sk-test-key');
        $model = new Gpt('gpt-4o-mini', [Capability::BATCH]);

        $job = $client->submitBatch($model, $requests);

        $this->assertSame('batch_large', $job->getId());
        $this->assertSame($requestCount, $job->getTotalCount());
    }

    public function testSubmitBatchThrowsWhenFileUploadFails()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['error' => 'Invalid file'])));

        $client = new ModelClient($httpClient, 'sk-test-key');
        $model = new Gpt('gpt-4o-mini', [Capability::BATCH]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to upload batch input file');

        $client->submitBatch($model, [
            ['id' => 'req-1', 'payload' => ['messages' => [['role' => 'user', 'content' => 'Hello']]]],
        ]);
    }

    public function testGetBatchMapsCompletedStatus()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_done',
            'status' => 'completed',
            'request_counts' => ['total' => 3, 'completed' => 3, 'failed' => 0],
            'output_file_id' => 'file-output-123',
        ])));

        $client = new ModelClient($httpClient, 'sk-test-key');
        $job = $client->getBatch('batch_done');

        $this->assertSame('batch_done', $job->getId());
        $this->assertTrue($job->isComplete());
        $this->assertSame(3, $job->getTotalCount());
        $this->assertSame('file-output-123', $job->getOutputFileId());
    }

    public function testGetBatchCompletedCountIncludesFailedRequests()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_mixed',
            'status' => 'completed',
            'request_counts' => ['total' => 10, 'completed' => 7, 'failed' => 3],
        ])));

        $client = new ModelClient($httpClient, 'sk-test-key');
        $job = $client->getBatch('batch_mixed');

        $this->assertSame(10, $job->getTotalCount());
        $this->assertSame(10, $job->getProcessedCount());
        $this->assertSame(3, $job->getFailedCount());
    }

    public function testGetBatchMapsAllStatuses()
    {
        $statuses = [
            'completed' => BatchStatus::COMPLETED,
            'failed' => BatchStatus::FAILED,
            'cancelled' => BatchStatus::CANCELLED,
            'expired' => BatchStatus::EXPIRED,
            'validating' => BatchStatus::PROCESSING,
            'in_progress' => BatchStatus::PROCESSING,
            'finalizing' => BatchStatus::PROCESSING,
            'cancelling' => BatchStatus::PROCESSING,
        ];

        foreach ($statuses as $apiStatus => $expectedStatus) {
            $httpClient = new MockHttpClient(new MockResponse(json_encode([
                'id' => 'batch_1',
                'status' => $apiStatus,
                'request_counts' => ['total' => 0, 'completed' => 0, 'failed' => 0],
            ])));

            $client = new ModelClient($httpClient, 'sk-test-key');
            $job = $client->getBatch('batch_1');

            $this->assertSame($expectedStatus, $job->getStatus(), \sprintf('Failed for status "%s"', $apiStatus));
        }
    }

    public function testFetchResultsThrowsForIncompleteJob()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');
        $job = new BatchJob('batch_123', BatchStatus::PROCESSING);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot fetch results for batch "batch_123"');

        iterator_to_array($client->fetchResults($job));
    }

    public function testFetchResultsThrowsWhenNoOutputFile()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');
        $job = new BatchJob('batch_123', BatchStatus::COMPLETED);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Batch "batch_123" has no output file.');

        iterator_to_array($client->fetchResults($job));
    }

    public function testFetchResultsStreamsSuccessResults()
    {
        $line1 = json_encode([
            'custom_id' => 'req-1',
            'response' => [
                'status_code' => 200,
                'body' => [
                    'choices' => [['message' => ['content' => 'Paris']]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 3],
                ],
            ],
        ]);
        $line2 = json_encode([
            'custom_id' => 'req-2',
            'response' => ['status_code' => 429, 'body' => null],
            'error' => ['message' => 'Rate limit exceeded'],
        ]);

        $httpClient = new MockHttpClient(new MockResponse($line1."\n".$line2."\n"));
        $client = new ModelClient($httpClient, 'sk-test-key');
        $job = new BatchJob('batch_done', BatchStatus::COMPLETED, 2, 2, 1, 'file-output-abc');

        $results = iterator_to_array($client->fetchResults($job));

        $this->assertCount(2, $results);

        $this->assertTrue($results[0]->isSuccess());
        $this->assertSame('req-1', $results[0]->getId());
        $this->assertSame('Paris', $results[0]->getContent());
        $this->assertSame(10, $results[0]->getInputTokens());
        $this->assertSame(3, $results[0]->getOutputTokens());

        $this->assertFalse($results[1]->isSuccess());
        $this->assertSame('req-2', $results[1]->getId());
        $this->assertSame('Rate limit exceeded', $results[1]->getError());
    }

    public function testCancelBatchSendsPostRequest()
    {
        $capturedMethod = null;
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function ($method, $url) use (&$capturedMethod, &$capturedUrl) {
            $capturedMethod = $method;
            $capturedUrl = $url;

            return new MockResponse(json_encode([
                'id' => 'batch_to_cancel',
                'status' => 'cancelling',
                'request_counts' => ['total' => 5, 'completed' => 2, 'failed' => 0],
            ]), ['http_code' => 200]);
        });

        $client = new ModelClient($httpClient, 'sk-test-key');
        $job = $client->cancelBatch('batch_to_cancel');

        $this->assertSame('POST', $capturedMethod);
        $this->assertStringEndsWith('/batch_to_cancel/cancel', $capturedUrl);
        $this->assertSame('batch_to_cancel', $job->getId());
        $this->assertSame(BatchStatus::PROCESSING, $job->getStatus());
    }

    public function testCancelBatchThrowsOnHttpError()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 404]));
        $client = new ModelClient($httpClient, 'sk-test-key');

        $this->expectException(\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface::class);

        $client->cancelBatch('batch_123');
    }

    public function testGetBatchThrowsOnUnknownStatus()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'batch_unknown',
            'status' => 'unknown_future_status',
            'request_counts' => ['total' => 0, 'completed' => 0, 'failed' => 0],
        ])));

        $client = new ModelClient($httpClient, 'sk-test-key');

        $this->expectException(\ValueError::class);

        $client->getBatch('batch_unknown');
    }

    public function testSubmitBatchThrowsOnEmptyRequests()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');
        $model = new Gpt('gpt-4o-mini', [Capability::BATCH]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot submit an empty batch.');

        $client->submitBatch($model, []);
    }

    public function testFetchResultsThrowsOnInvalidJson()
    {
        $httpClient = new MockHttpClient(new MockResponse("not-valid-json\n"));
        $client = new ModelClient($httpClient, 'sk-test-key');
        $job = new BatchJob('batch_done', BatchStatus::COMPLETED, 1, 1, 0, 'file-output-abc');

        $this->expectException(\JsonException::class);

        iterator_to_array($client->fetchResults($job));
    }

    public function testFetchResultsReturnsNullContentWhenChoicesAbsent()
    {
        $line = json_encode([
            'custom_id' => 'req-1',
            'response' => [
                'status_code' => 200,
                'body' => [
                    'choices' => [],
                    'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 0],
                ],
            ],
        ]);

        $httpClient = new MockHttpClient(new MockResponse($line."\n"));
        $client = new ModelClient($httpClient, 'sk-test-key');
        $job = new BatchJob('batch_done', BatchStatus::COMPLETED, 1, 1, 0, 'file-output-abc');

        $results = iterator_to_array($client->fetchResults($job));

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertNull($results[0]->getContent());
    }
}
