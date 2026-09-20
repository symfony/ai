<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Batch;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Batch\BatchClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchClientTest extends TestCase
{
    public function testItUploadsTheRequestsAsJsonLinesAndCreatesTheBatch()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = [$method, $url, self::body($options)];

            return str_contains($url, '/v1/files')
                ? new MockResponse('{"id": "file-abc"}')
                : new MockResponse('{"id": "batch_123", "status": "validating"}');
        });

        $result = (new BatchClient($httpClient, 'sk-test', 'https://api.openai.com', '/v1/responses'))->submit([
            'first' => ['model' => 'gpt-4o-mini', 'input' => 'What is the capital of France?'],
            'second' => ['model' => 'gpt-4o-mini', 'input' => 'What is the capital of Germany?'],
        ]);

        $this->assertSame(['id' => 'batch_123', 'status' => 'validating'], $result->getData());
        $this->assertCount(2, $recorded);

        [$method, $url, $body] = $recorded[0];

        $this->assertSame('POST', $method);
        $this->assertSame('https://api.openai.com/v1/files', $url);
        $this->assertStringContainsString('name="purpose"', $body);
        $this->assertStringContainsString('batch', $body);
        // OpenAI rejects a batch file that does not present itself as JSONL.
        $this->assertStringContainsString('filename="batch.jsonl"', $body);
        $this->assertStringContainsString('Content-Type: application/jsonl', $body);
        $this->assertStringContainsString('{"custom_id":"first","method":"POST","url":"\/v1\/responses","body":{"model":"gpt-4o-mini","input":"What is the capital of France?"}}', $body);
        $this->assertStringContainsString('{"custom_id":"second","method":"POST","url":"\/v1\/responses","body":{"model":"gpt-4o-mini","input":"What is the capital of Germany?"}}', $body);

        [$method, $url, $body] = $recorded[1];

        $this->assertSame('POST', $method);
        $this->assertSame('https://api.openai.com/v1/batches', $url);
        $this->assertSame('{"input_file_id":"file-abc","endpoint":"\/v1\/responses","completion_window":"24h"}', $body);
    }

    public function testItSubmitsToTheConfiguredEndpoint()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = self::body($options);

            return new MockResponse('{"id": "file-abc"}');
        });

        (new BatchClient($httpClient, 'sk-test', 'https://eu.api.openai.com', '/v1/embeddings'))->submit([
            'first' => ['model' => 'text-embedding-3-small'],
        ]);

        $this->assertStringContainsString('"url":"\/v1\/embeddings"', $recorded[0]);
        $this->assertStringContainsString('"endpoint":"\/v1\/embeddings"', $recorded[1]);
    }

    public function testItDeletesTheInputFileOfABatchOpenAiRejects()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$recorded): MockResponse {
            $recorded[] = $method.' '.$url;

            return str_contains($url, '/v1/files') && 'POST' === $method
                ? new MockResponse('{"id": "file-abc"}')
                : new MockResponse('{"error": {"message": "Unsupported endpoint."}}', ['http_code' => 400]);
        });

        $result = (new BatchClient($httpClient, 'sk-test', 'https://api.openai.com', '/v1/responses'))->submit([
            'first' => ['model' => 'gpt-4o-mini'],
        ]);

        $this->assertSame([
            'POST https://api.openai.com/v1/files',
            'POST https://api.openai.com/v1/batches',
            'DELETE https://api.openai.com/v1/files/file-abc',
        ], $recorded);

        // Reporting the rejection is still the result converter's job.
        $this->assertSame(400, $result->getObject()->getStatusCode());
    }

    public function testItKeepsTheInputFileOfABatchOpenAiAccepts()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$recorded): MockResponse {
            $recorded[] = $method.' '.$url;

            return new MockResponse('{"id": "file-abc"}');
        });

        (new BatchClient($httpClient, 'sk-test', 'https://api.openai.com', '/v1/responses'))->submit([
            'first' => ['model' => 'gpt-4o-mini'],
        ]);

        $this->assertNotContains('DELETE https://api.openai.com/v1/files/file-abc', $recorded);
    }

    public function testAFailingDeleteDoesNotFailTheSubmission()
    {
        $httpClient = new MockHttpClient(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/v1/files') && 'POST' === $method => new MockResponse('{"id": "file-abc"}'),
            'DELETE' === $method => new MockResponse('{}', ['http_code' => 500]),
            default => new MockResponse('{}', ['http_code' => 400]),
        });

        $result = (new BatchClient($httpClient, 'sk-test', 'https://api.openai.com', '/v1/responses'))->submit([
            'first' => ['model' => 'gpt-4o-mini'],
        ]);

        $this->assertSame(400, $result->getObject()->getStatusCode());
    }

    public function testItRejectsAnEmptyBatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch needs at least one request.');

        (new BatchClient(new MockHttpClient(), 'sk-test', 'https://api.openai.com', '/v1/responses'))->submit([]);
    }

    public function testItRejectsAnIdentifierOpenAiWouldNotAccept()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The identifier of a batch request must be at most 64 characters long');

        (new BatchClient(new MockHttpClient(), 'sk-test', 'https://api.openai.com', '/v1/responses'))->submit([
            str_repeat('a', 65) => ['model' => 'gpt-4o-mini'],
        ]);
    }

    public function testItFailsWhenTheUploadReturnsNoFileIdentifier()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The upload of the batch file did not return a file identifier.');

        (new BatchClient(new MockHttpClient(new MockResponse('{}')), 'sk-test', 'https://api.openai.com', '/v1/responses'))->submit([
            'first' => ['model' => 'gpt-4o-mini'],
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
