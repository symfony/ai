<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Gpt;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new ModelClient(new MockHttpClient(), '');
    }

    #[TestWith(['api-key-without-prefix'])]
    #[TestWith(['pk-api-key'])]
    #[TestWith(['SK-api-key'])]
    #[TestWith(['skapikey'])]
    #[TestWith(['sk api-key'])]
    #[TestWith(['sk'])]
    public function testItThrowsExceptionWhenApiKeyDoesNotStartWithSk(string $invalidApiKey)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must start with "sk-".');

        new ModelClient(new MockHttpClient(), $invalidApiKey);
    }

    public function testItAcceptsValidApiKey()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'sk-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new ModelClient($httpClient, 'sk-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($modelClient->supports(new Gpt('gpt-4o')));
    }

    public function testStringPayloadThrowsException()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $modelClient->request(new Gpt('gpt-4o'), 'string payload');
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/responses', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":1,"model":"gpt-4o","messages":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new Gpt('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'test message']]], ['temperature' => 1]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertJson($options['body']);
            self::assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new Gpt('gpt-4o'), ['messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/responses', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"temperature":0.7,"text":{"format":{"name":"foo","schema":[],"type":"json"}},"model":"gpt-4o","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $options = [
            'temperature' => 0.7,
            'response_format' => [
                'type' => 'json',
                'json_schema' => [
                    'name' => 'foo',
                    'schema' => []],
            ],
        ];

        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new Gpt('gpt-4o'), ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'Hello']]], $options);
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/responses'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/responses'])]
    #[TestWith([null, 'https://api.openai.com/v1/responses'])]
    public function testItUsesCorrectBaseUrl(?string $region, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key', $region);
        $modelClient->request(new Gpt('gpt-4o'), ['messages' => []], []);
    }

    public function testItSubmitsABatchOfInputsInsteadOfOneRequest()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = [$url, self::collectBody($options)];

            return str_contains($url, '/v1/files')
                ? new MockResponse('{"id": "file-abc"}')
                : new MockResponse('{"id": "batch_123", "status": "validating"}');
        });

        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $result = $modelClient->request(new Gpt('gpt-4o-mini'), [
            'first' => ['input' => [['role' => 'user', 'content' => 'What is the capital of France?']]],
            'second' => ['input' => [['role' => 'user', 'content' => 'What is the capital of Germany?']]],
        ], ['batch' => true, 'max_output_tokens' => 50]);

        $this->assertSame('batch_123', $result->getData()['id']);
        $this->assertSame('https://api.openai.com/v1/files', $recorded[0][0]);
        $this->assertSame('https://api.openai.com/v1/batches', $recorded[1][0]);

        // Each line is the request it would have been on its own, without the "batch" option itself.
        $this->assertStringContainsString('{"custom_id":"first","method":"POST","url":"\/v1\/responses","body":{"max_output_tokens":50,"model":"gpt-4o-mini","input":[{"role":"user","content":"What is the capital of France?"}]}}', $recorded[0][1]);
        $this->assertStringNotContainsString('"batch"', $recorded[0][1]);
    }

    public function testItRefusesToStreamABatch()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch is answered as a file hours later, so it cannot be streamed.');

        $modelClient->request(new Gpt('gpt-4o-mini'), ['first' => ['input' => []]], ['batch' => true, 'stream' => true]);
    }

    public function testItRefusesASingleInputAsABatch()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch invocation expects an array of inputs, keyed by the identifier to report each result under, and not a single input.');

        $modelClient->request(new Gpt('gpt-4o-mini'), ['input' => [['role' => 'user', 'content' => 'Hello']]], ['batch' => true]);
    }

    public function testItSaysWhatIsWrongWithAnInputThatIsNotARequest()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input "first" of the batch did not normalize into a request');

        $modelClient->request(new Gpt('gpt-4o-mini'), ['first' => 'What is the capital of France?'], ['batch' => true]);
    }

    public function testItRefusesAnEmptyBatch()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch invocation expects a non-empty array of inputs, "array" given.');

        $modelClient->request(new Gpt('gpt-4o-mini'), [], ['batch' => true]);
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
