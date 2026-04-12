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
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
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
    public function testSupportsClaudeWithBatchCapability()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');
        $model = new Claude(Claude::SONNET_4, [Capability::BATCH]);

        $this->assertTrue($client->supports($model));
    }

    public function testDoesNotSupportClaudeWithoutBatchCapability()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');
        $model = new Claude(Claude::SONNET_4, [Capability::INPUT_MESSAGES]);

        $this->assertFalse($client->supports($model));
    }

    public function testDoesNotSupportNonClaudeModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');
        $model = new Gpt('gpt-4o', [Capability::BATCH]);

        $this->assertFalse($client->supports($model));
    }

    public function testSubmitBatchSendsCorrectRequest()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($this->readBody($options['body']), true);

            return new MockResponse(json_encode([
                'id' => 'msgbatch_abc123',
                'processing_status' => 'in_progress',
                'request_counts' => ['processing' => 2, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
            ]));
        });

        $client = new ModelClient($httpClient, 'test-key');
        $model = new Claude(Claude::SONNET_4, [Capability::BATCH]);

        $job = $client->submitBatch($model, [
            ['id' => 'req-1', 'payload' => ['model' => Claude::SONNET_4, 'messages' => [['role' => 'user', 'content' => 'Hello']], 'max_tokens' => 1000]],
            ['id' => 'req-2', 'payload' => ['model' => Claude::SONNET_4, 'messages' => [['role' => 'user', 'content' => 'World']], 'max_tokens' => 1000]],
        ]);

        $this->assertStringEndsWith('/v1/messages/batches', $capturedUrl);
        $this->assertCount(2, $capturedBody['requests']);
        $this->assertSame('req-1', $capturedBody['requests'][0]['custom_id']);
        $this->assertSame('req-2', $capturedBody['requests'][1]['custom_id']);
        $this->assertSame('msgbatch_abc123', $job->getId());
        $this->assertTrue($job->isProcessing());
    }

    public function testGetBatchMapsCompletedStatus()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_done',
            'processing_status' => 'ended',
            'request_counts' => ['processing' => 0, 'succeeded' => 3, 'errored' => 1, 'canceled' => 0, 'expired' => 0],
        ])));

        $client = new ModelClient($httpClient, 'test-key');
        $job = $client->getBatch('msgbatch_done');

        $this->assertSame('msgbatch_done', $job->getId());
        $this->assertTrue($job->isComplete());
        $this->assertSame(4, $job->getTotalCount());
        $this->assertSame(4, $job->getProcessedCount());
        $this->assertSame(1, $job->getFailedCount());
    }

    public function testGetBatchMapsCancelledStatus()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_cancelled',
            'processing_status' => 'ended',
            'request_counts' => ['processing' => 0, 'succeeded' => 2, 'errored' => 0, 'canceled' => 5, 'expired' => 0],
        ])));

        $client = new ModelClient($httpClient, 'test-key');
        $job = $client->getBatch('msgbatch_cancelled');

        $this->assertSame('msgbatch_cancelled', $job->getId());
        $this->assertFalse($job->isComplete());
        $this->assertTrue($job->isTerminal());
        $this->assertSame(BatchStatus::CANCELLED, $job->getStatus());
    }

    public function testGetBatchMapsProcessingStatus()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_running',
            'processing_status' => 'in_progress',
            'request_counts' => ['processing' => 5, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
        ])));

        $client = new ModelClient($httpClient, 'test-key');
        $job = $client->getBatch('msgbatch_running');

        $this->assertTrue($job->isProcessing());
    }

    public function testFetchResultsThrowsForIncompleteJob()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');
        $job = new BatchJob('msgbatch_123', BatchStatus::PROCESSING);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot fetch results for batch "msgbatch_123"');

        iterator_to_array($client->fetchResults($job));
    }

    public function testFetchResultsStreamsSuccessResults()
    {
        $line1 = json_encode([
            'custom_id' => 'req-1',
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'content' => [['type' => 'text', 'text' => 'Paris']],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 3],
                ],
            ],
        ]);
        $line2 = json_encode([
            'custom_id' => 'req-2',
            'result' => [
                'type' => 'errored',
                'error' => ['message' => 'Rate limit exceeded'],
            ],
        ]);

        $httpClient = new MockHttpClient(new MockResponse($line1."\n".$line2."\n"));
        $client = new ModelClient($httpClient, 'test-key');
        $job = new BatchJob('msgbatch_done', BatchStatus::COMPLETED, 2, 2, 1);

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
                'id' => 'msgbatch_to_cancel',
                'processing_status' => 'canceling',
                'request_counts' => ['processing' => 2, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
            ]), ['http_code' => 200]);
        });

        $client = new ModelClient($httpClient, 'test-key');
        $job = $client->cancelBatch('msgbatch_to_cancel');

        $this->assertSame('POST', $capturedMethod);
        $this->assertStringEndsWith('/msgbatch_to_cancel/cancel', $capturedUrl);
        $this->assertSame('msgbatch_to_cancel', $job->getId());
    }

    public function testCancelBatchThrowsOnHttpError()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 404]));
        $client = new ModelClient($httpClient, 'test-key');

        $this->expectException(\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface::class);

        $client->cancelBatch('msgbatch_123');
    }

    public function testFetchResultsReturnsNullContentWhenAbsent()
    {
        $line = json_encode([
            'custom_id' => 'req-1',
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'content' => [],
                    'usage' => ['input_tokens' => 5, 'output_tokens' => 0],
                ],
            ],
        ]);

        $httpClient = new MockHttpClient(new MockResponse($line."\n"));
        $client = new ModelClient($httpClient, 'test-key');
        $job = new BatchJob('msgbatch_done', BatchStatus::COMPLETED, 1, 1, 0);

        $results = iterator_to_array($client->fetchResults($job));

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertNull($results[0]->getContent());
    }

    public function testGetBatchMapsCancelingStatus()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_canceling',
            'processing_status' => 'canceling',
            'request_counts' => ['processing' => 2, 'succeeded' => 1, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
        ])));

        $client = new ModelClient($httpClient, 'test-key');
        $job = $client->getBatch('msgbatch_canceling');

        $this->assertSame(BatchStatus::PROCESSING, $job->getStatus());
    }

    public function testGetBatchThrowsOnUnknownStatus()
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_unknown',
            'processing_status' => 'unknown_future_status',
            'request_counts' => [],
        ])));

        $client = new ModelClient($httpClient, 'test-key');

        $this->expectException(\ValueError::class);

        $client->getBatch('msgbatch_unknown');
    }

    public function testSubmitBatchThrowsOnEmptyRequests()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');
        $model = new Claude(Claude::SONNET_4, [Capability::BATCH]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot submit an empty batch.');

        $client->submitBatch($model, []);
    }

    public function testFetchResultsThrowsOnInvalidJson()
    {
        $httpClient = new MockHttpClient(new MockResponse("not-valid-json\n"));
        $client = new ModelClient($httpClient, 'test-key');
        $job = new BatchJob('msgbatch_done', BatchStatus::COMPLETED, 1, 1, 0);

        $this->expectException(\JsonException::class);

        iterator_to_array($client->fetchResults($job));
    }

    public function testSubmitBatchHandlesLargeNumberOfRequests()
    {
        $requestCount = 10000;

        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'id' => 'msgbatch_large',
            'processing_status' => 'in_progress',
            'request_counts' => ['processing' => $requestCount, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
        ])));

        $requests = [];
        for ($i = 0; $i < $requestCount; ++$i) {
            $requests[] = ['id' => "req-{$i}", 'payload' => ['messages' => [['role' => 'user', 'content' => "Question {$i}"]]]];
        }

        $client = new ModelClient($httpClient, 'test-key');
        $model = new Claude(Claude::SONNET_4, [Capability::BATCH]);

        $job = $client->submitBatch($model, $requests);

        $this->assertSame('msgbatch_large', $job->getId());
        $this->assertSame($requestCount, $job->getTotalCount());
    }

    private function readBody(mixed $body): string
    {
        if (\is_string($body)) {
            return $body;
        }
        if ($body instanceof \Traversable) {
            return implode('', iterator_to_array($body));
        }
        $chunks = [];
        while ('' !== ($chunk = $body(1024))) {
            $chunks[] = $chunk;
        }

        return implode('', $chunks);
    }
}
