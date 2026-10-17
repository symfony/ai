<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Batch;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Batch\BatchClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchClientTest extends TestCase
{
    public function testItUploadsTheRequestsAsJsonLinesAndCreatesTheJob()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = [$method, $url, self::body($options)];

            return str_contains($url, '/v1/files')
                ? new MockResponse('{"id": "file-abc"}')
                : new MockResponse('{"id": "batch_123", "status": "QUEUED"}');
        });

        $result = (new BatchClient($httpClient, 'mistral-key', 'https://api.mistral.ai', '/v1/chat/completions'))->submit('mistral-large-latest', [
            'first' => ['messages' => [['role' => 'user', 'content' => 'What is the capital of France?']]],
            'second' => ['messages' => [['role' => 'user', 'content' => 'What is the capital of Germany?']]],
        ]);

        $this->assertSame(['id' => 'batch_123', 'status' => 'QUEUED'], $result->getData());
        $this->assertCount(2, $recorded);

        [$method, $url, $body] = $recorded[0];

        $this->assertSame('POST', $method);
        $this->assertSame('https://api.mistral.ai/v1/files', $url);
        $this->assertStringContainsString('name="purpose"', $body);
        $this->assertStringContainsString('batch', $body);
        // Mistral rejects a batch file that does not present itself as JSONL.
        $this->assertStringContainsString('filename="batch_input.jsonl"', $body);
        $this->assertStringContainsString('Content-Type: application/jsonl', $body);
        // A line carries the request body and nothing else - no method, no URL, no model.
        $this->assertStringContainsString('{"custom_id":"first","body":{"messages":[{"role":"user","content":"What is the capital of France?"}]}}', $body);
        $this->assertStringContainsString('{"custom_id":"second","body":{"messages":[{"role":"user","content":"What is the capital of Germany?"}]}}', $body);

        [$method, $url, $body] = $recorded[1];

        $this->assertSame('POST', $method);
        $this->assertSame('https://api.mistral.ai/v1/batch/jobs', $url);
        $this->assertSame('{"input_files":["file-abc"],"endpoint":"\/v1\/chat\/completions","model":"mistral-large-latest","timeout_hours":24}', $body);
    }

    public function testItStatesTheTimeoutOnTheJob()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = self::body($options);

            return new MockResponse('{"id": "file-abc"}');
        });

        (new BatchClient($httpClient, 'mistral-key', 'https://api.mistral.ai', '/v1/chat/completions'))->submit(
            'mistral-small-latest',
            ['first' => ['messages' => []]],
            6,
        );

        $this->assertSame('{"input_files":["file-abc"],"endpoint":"\/v1\/chat\/completions","model":"mistral-small-latest","timeout_hours":6}', $recorded[1]);
    }

    public function testItSubmitsToTheConfiguredEndpointAndBaseUrl()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = [$url, self::body($options)];

            return new MockResponse('{"id": "file-abc"}');
        });

        (new BatchClient($httpClient, 'mistral-key', 'https://mistral.example.com', '/v1/embeddings'))->submit('mistral-embed', [
            'first' => ['input' => 'Hello'],
        ]);

        $this->assertSame('https://mistral.example.com/v1/files', $recorded[0][0]);
        $this->assertSame('https://mistral.example.com/v1/batch/jobs', $recorded[1][0]);
        $this->assertStringContainsString('"endpoint":"\/v1\/embeddings"', $recorded[1][1]);
    }

    public function testItDeletesTheInputFileOfAJobMistralRejects()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$recorded): MockResponse {
            $recorded[] = $method.' '.$url;

            return str_contains($url, '/v1/files') && 'POST' === $method
                ? new MockResponse('{"id": "file-abc"}')
                : new MockResponse('{"message": "Unsupported endpoint."}', ['http_code' => 400]);
        });

        $result = (new BatchClient($httpClient, 'mistral-key', 'https://api.mistral.ai', '/v1/chat/completions'))->submit('mistral-large-latest', [
            'first' => ['messages' => []],
        ]);

        $this->assertSame([
            'POST https://api.mistral.ai/v1/files',
            'POST https://api.mistral.ai/v1/batch/jobs',
            'DELETE https://api.mistral.ai/v1/files/file-abc',
        ], $recorded);

        // Reporting the rejection is still the result converter's job.
        $this->assertSame(400, $result->getObject()->getStatusCode());
    }

    public function testItKeepsTheInputFileOfAJobMistralAccepts()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$recorded): MockResponse {
            $recorded[] = $method.' '.$url;

            return new MockResponse('{"id": "file-abc"}');
        });

        (new BatchClient($httpClient, 'mistral-key', 'https://api.mistral.ai', '/v1/chat/completions'))->submit('mistral-large-latest', [
            'first' => ['messages' => []],
        ]);

        $this->assertNotContains('DELETE https://api.mistral.ai/v1/files/file-abc', $recorded);
    }

    public function testAFailingDeleteDoesNotFailTheSubmission()
    {
        $httpClient = new MockHttpClient(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/v1/files') && 'POST' === $method => new MockResponse('{"id": "file-abc"}'),
            'DELETE' === $method => new MockResponse('{}', ['http_code' => 500]),
            default => new MockResponse('{}', ['http_code' => 400]),
        });

        $result = (new BatchClient($httpClient, 'mistral-key', 'https://api.mistral.ai', '/v1/chat/completions'))->submit('mistral-large-latest', [
            'first' => ['messages' => []],
        ]);

        $this->assertSame(400, $result->getObject()->getStatusCode());
    }

    public function testItRejectsAnEmptyBatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch needs at least one request.');

        (new BatchClient(new MockHttpClient(), 'mistral-key', 'https://api.mistral.ai', '/v1/chat/completions'))->submit('mistral-large-latest', []);
    }

    public function testItFailsWhenTheUploadReturnsNoFileIdentifier()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The upload of the batch file did not return a file identifier.');

        (new BatchClient(new MockHttpClient(new MockResponse('{}')), 'mistral-key', 'https://api.mistral.ai', '/v1/chat/completions'))->submit('mistral-large-latest', [
            'first' => ['messages' => []],
        ]);
    }

    public function testItFailsWhenTheUploadIsRejected()
    {
        $this->expectException(RuntimeException::class);

        (new BatchClient(new MockHttpClient(new MockResponse('{"message": "Invalid file."}', ['http_code' => 422])), 'mistral-key', 'https://api.mistral.ai', '/v1/chat/completions'))->submit('mistral-large-latest', [
            'first' => ['messages' => []],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function body(array $options): string
    {
        $body = $options['body'] ?? '';

        if (!\is_callable($body)) {
            return (string) $body;
        }

        $collected = '';

        while ('' !== $chunk = $body(8192)) {
            $collected .= $chunk;
        }

        return $collected;
    }
}
