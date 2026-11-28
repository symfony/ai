<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Gemini;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientTest extends TestCase
{
    public function testRequest()
    {
        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Hello, world!']]],
            ],
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($payload) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent', $url);
            $this->assertArrayHasKey('headers', $options);
            $this->assertContains('x-goog-api-key: test-api-key', $options['headers']);

            $body = json_decode($options['body'], true);
            $this->assertSame($payload['contents'], $body['contents']);
            $this->assertArrayNotHasKey('generationConfig', $body);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), $payload);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): JsonMockResponse {
            $this->assertSame('https://gemini.example.com/v1beta/models/gemini-1.5-flash:generateContent', $url);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key', 'https://gemini.example.com/');
        $client->request(new Gemini('gemini-1.5-flash'), ['contents' => []]);
    }

    public function testRequestWithOptions()
    {
        $payload = ['contents' => []];
        $options = [
            'temperature' => 0.7,
            'topP' => 0.9,
            'tools' => [['name' => 'get_weather']],
            'tool_config' => ['function_calling_config' => ['mode' => 'ANY']],
            'service_tier' => 'priority',
            'server_tools' => ['google_search' => true],
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);

            $this->assertSame(0.7, $body['generationConfig']['temperature']);
            $this->assertSame(0.9, $body['generationConfig']['topP']);
            $this->assertSame([['functionDeclarations' => [['name' => 'get_weather']]], ['google_search' => []]], $body['tools']);
            $this->assertSame(['function_calling_config' => ['mode' => 'ANY']], $body['tool_config']);
            $this->assertSame('priority', $body['service_tier']);
            $this->assertArrayNotHasKey('service_tier', $body['generationConfig']);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), $payload, $options);
    }

    public function testCanonicalMaxOutputTokensIsRenamedToMaxOutputTokensCamelCase()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);

            $this->assertSame(500, $body['generationConfig']['maxOutputTokens']);
            $this->assertArrayNotHasKey('max_output_tokens', $body['generationConfig']);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), ['contents' => []], ['max_output_tokens' => 500]);
    }

    public function testExplicitMaxOutputTokensWinsOverCanonicalOption()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);

            $this->assertSame(2000, $body['generationConfig']['maxOutputTokens']);
            $this->assertArrayNotHasKey('max_output_tokens', $body['generationConfig']);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), ['contents' => []], ['maxOutputTokens' => 2000, 'max_output_tokens' => 500]);
    }

    public function testRequestWithStreamUsesDifferentEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:streamGenerateContent?alt=sse', $url);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), ['contents' => []], ['stream' => true]);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new JsonMockResponse(['candidates' => []]);
        });

        $client = new ModelClient($httpClient, 'test-api-key');
        $client->request(new Gemini('gemini-1.5-flash'), ['contents' => [['parts' => [['text' => "tool output \xB1 here"]]]]]);
    }

    public function testItSubmitsABatchOfInputsInsteadOfOneRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:batchGenerateContent', $url);

            $body = json_decode($options['body'], true);
            $requests = $body['batch']['inputConfig']['requests']['requests'];

            $this->assertCount(2, $requests);
            $this->assertSame('first', $requests[0]['metadata']['key']);
            $this->assertSame([['parts' => [['text' => 'France?']]]], $requests[0]['request']['contents']);
            $this->assertSame(['maxOutputTokens' => 50], $requests[0]['request']['generationConfig']);
            $this->assertSame('second', $requests[1]['metadata']['key']);
            $this->assertStringNotContainsString('"batch":true', $options['body']);

            return new JsonMockResponse(['name' => 'batches/123']);
        });

        $result = (new ModelClient($httpClient, 'test-api-key'))->request(new Gemini('gemini-2.5-flash'), [
            'first' => ['contents' => [['parts' => [['text' => 'France?']]]]],
            'second' => ['contents' => [['parts' => [['text' => 'Germany?']]]]],
        ], ['batch' => true, 'maxOutputTokens' => 50]);

        $this->assertSame('batches/123', $result->getData()['name']);
    }

    public function testItRefusesToStreamABatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch is answered hours later, so it cannot be streamed.');

        (new ModelClient(new MockHttpClient(), 'test-api-key'))->request(new Gemini('gemini-2.5-flash'), ['first' => ['contents' => []]], ['batch' => true, 'stream' => true]);
    }

    public function testItRefusesASingleInputAsABatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expects an array of inputs, keyed by the identifier');

        (new ModelClient(new MockHttpClient(), 'test-api-key'))->request(new Gemini('gemini-2.5-flash'), ['contents' => [['parts' => [['text' => 'Hi']]]]], ['batch' => true]);
    }

    public function testItRefusesABatchTooBigToSendInline()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Split them into several batches.');

        (new ModelClient(new MockHttpClient(), 'test-api-key'))->request(new Gemini('gemini-2.5-flash'), [
            'first' => ['contents' => [['parts' => [['text' => str_repeat('a', 20_000_001)]]]]],
        ], ['batch' => true]);
    }

    public function testItRefusesAnInputThatIsNotARequest()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The input "first" of the batch did not normalize into a request');

        (new ModelClient(new MockHttpClient(), 'test-api-key'))->request(new Gemini('gemini-2.5-flash'), ['first' => 'Hi'], ['batch' => true]);
    }
}
