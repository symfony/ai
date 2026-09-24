<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Llm;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Llm\ModelClient;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClientTest extends TestCase
{
    public function testItIsExecutingTheCorrectRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mistral.ai/v1/chat/completions', $url);
            $this->assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);
            $this->assertSame('{"messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Mistral('mistral-large-latest'), ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url): MockResponse {
            $this->assertSame('https://mistral.example.com/v1/chat/completions', $url);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-api-key', 'https://mistral.example.com/');
        $client->request(new Mistral('mistral-large-latest'), ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Mistral('mistral-large-latest'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testItSubmitsABatchOfInputsInsteadOfOneRequest()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = [$url, self::collectBody($options)];

            return str_contains($url, '/v1/files')
                ? new MockResponse('{"id": "file-abc"}')
                : new MockResponse('{"id": "batch_123", "status": "QUEUED"}');
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $result = $client->request(new Mistral('mistral-large-latest'), [
            'first' => ['messages' => [['role' => 'user', 'content' => 'What is the capital of France?']], 'model' => 'mistral-large-latest'],
            'second' => ['messages' => [['role' => 'user', 'content' => 'What is the capital of Germany?']], 'model' => 'mistral-large-latest'],
        ], ['batch' => true, 'max_tokens' => 50]);

        $this->assertSame('batch_123', $result->getData()['id']);
        $this->assertSame('https://api.mistral.ai/v1/files', $recorded[0][0]);
        $this->assertSame('https://api.mistral.ai/v1/batch/jobs', $recorded[1][0]);

        // Each line is the request it would have been on its own, without the "batch" option and
        // without the model, which Mistral states once on the job instead.
        $this->assertStringContainsString('{"custom_id":"first","body":{"max_tokens":50,"messages":[{"role":"user","content":"What is the capital of France?"}]}}', $recorded[0][1]);
        $this->assertStringNotContainsString('"batch"', $recorded[0][1]);
        $this->assertStringNotContainsString('"model"', $recorded[0][1]);
        $this->assertStringContainsString('"model":"mistral-large-latest"', $recorded[1][1]);
    }

    public function testItStatesTheTimeoutOnTheJobRatherThanOnItsRequests()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = self::collectBody($options);

            return new MockResponse('{"id": "file-abc"}');
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Mistral('mistral-small-latest'), ['first' => ['messages' => []]], [
            'batch' => true,
            'timeout_hours' => 6,
        ]);

        $this->assertStringNotContainsString('timeout_hours', $recorded[0]);
        $this->assertStringContainsString('"timeout_hours":6', $recorded[1]);
    }

    public function testItRefusesToStreamABatch()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch is answered as a file hours later, so it cannot be streamed.');

        $client->request(new Mistral('mistral-large-latest'), ['first' => ['messages' => []]], ['batch' => true, 'stream' => true]);
    }

    public function testItRefusesASingleInputAsABatch()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch invocation expects an array of inputs, keyed by the identifier to report each result under, and not a single input.');

        $client->request(new Mistral('mistral-large-latest'), ['messages' => [['role' => 'user', 'content' => 'Hello']]], ['batch' => true]);
    }

    public function testItSaysWhatIsWrongWithAnInputThatIsNotARequest()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input "first" of the batch did not normalize into a request');

        $client->request(new Mistral('mistral-large-latest'), ['first' => 'What is the capital of France?'], ['batch' => true]);
    }

    public function testItRefusesAnEmptyBatch()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch invocation expects a non-empty array of inputs, "array" given.');

        $client->request(new Mistral('mistral-large-latest'), [], ['batch' => true]);
    }

    public function testItRefusesATimeoutThatIsNotAWholeNumberOfHours()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The timeout of a batch is stated in whole hours, "string" given.');

        $client->request(new Mistral('mistral-large-latest'), ['first' => ['messages' => []]], ['batch' => true, 'timeout_hours' => '6']);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function collectBody(array $options): string
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
