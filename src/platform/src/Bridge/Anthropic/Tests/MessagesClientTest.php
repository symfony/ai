<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\MessagesClient;
use Symfony\AI\Platform\Bridge\Anthropic\Transport\HttpTransport;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class MessagesClientTest extends TestCase
{
    private MockHttpClient $httpClient;
    private MessagesClient $client;
    private Claude $model;

    protected function setUp(): void
    {
        $this->model = new Claude('claude-3-5-sonnet-latest');
    }

    public function testAnthropicBetaHeaderIsSetWithSingleBetaFeature()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.anthropic.com/v1/messages', $url);

            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayHasKey('anthropic-beta', $headers);
            $this->assertSame('feature-1', $headers['anthropic-beta']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $options = ['beta_features' => ['feature-1']];
        $this->client->request($this->model, ['message' => 'test'], $options);
    }

    public function testAnthropicBetaHeaderIsSetWithMultipleBetaFeatures()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayHasKey('anthropic-beta', $headers);
            $this->assertSame('feature-1,feature-2,feature-3', $headers['anthropic-beta']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $options = ['beta_features' => ['feature-1', 'feature-2', 'feature-3']];
        $this->client->request($this->model, ['message' => 'test'], $options);
    }

    public function testAnthropicBetaHeaderIsNotSetWhenBetaFeaturesIsEmpty()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayNotHasKey('anthropic-beta', $headers);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $options = ['beta_features' => []];
        $this->client->request($this->model, ['message' => 'test'], $options);
    }

    public function testAnthropicBetaHeaderIsNotSetWhenBetaFeaturesIsNotProvided()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayNotHasKey('anthropic-beta', $headers);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $options = ['some_other_option' => 'value'];
        $this->client->request($this->model, ['message' => 'test'], $options);
    }

    public function testThinkingOptionAddsBetaHeaderAndPassesThrough()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayHasKey('anthropic-beta', $headers);
            $this->assertSame('interleaved-thinking-2025-05-14', $headers['anthropic-beta']);

            $body = json_decode($options['body'], true);
            $this->assertSame(['type' => 'enabled', 'budget_tokens' => 10000], $body['thinking']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $options = [
            'thinking' => ['type' => 'enabled', 'budget_tokens' => 10000],
        ];
        $this->client->request($this->model, ['message' => 'test'], $options);
    }

    public function testThinkingBetaHeaderCombinesWithOtherBetaFeatures()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayHasKey('anthropic-beta', $headers);
            $this->assertStringContainsString('interleaved-thinking-2025-05-14', $headers['anthropic-beta']);
            $this->assertStringContainsString('other-feature', $headers['anthropic-beta']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $options = [
            'thinking' => ['type' => 'enabled', 'budget_tokens' => 5000],
            'beta_features' => ['other-feature'],
        ];
        $this->client->request($this->model, ['message' => 'test'], $options);
    }

    public function testTransformsResponseFormatToOutputConfig()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $headers = $this->parseHeaders($options['headers']);

            $this->assertArrayNotHasKey('anthropic-beta', $headers);

            $body = json_decode($options['body'], true);
            $this->assertArrayHasKey('output_config', $body);
            $this->assertArrayHasKey('format', $body['output_config']);
            $this->assertSame('json_schema', $body['output_config']['format']['type']);
            $this->assertSame(['type' => 'object', 'properties' => ['foo' => ['type' => 'string']]], $body['output_config']['format']['schema']);
            $this->assertArrayNotHasKey('response_format', $body);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $options = [
            'response_format' => [
                'json_schema' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'foo' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $this->client->request($this->model, ['message' => 'test'], $options);
    }

    public function testStringPayloadThrowsException()
    {
        $this->client = new MessagesClient(new HttpTransport(new MockHttpClient(), 'test-api-key'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $this->client->request($this->model, 'string payload');
    }

    public function testInvalidCacheRetentionThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid cache retention "foo". Supported values are "none", "short" and "long".');

        new MessagesClient(new HttpTransport(new MockHttpClient(), 'test-api-key'), 'foo'); // @phpstan-ignore argument.type (testing invalid value)
    }

    public function testDefaultCacheRetentionInjectsEphemeralOnPlainStringContent()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'], true);
            $messages = $body['messages'];

            // Plain string must have been promoted to a block with cache_control
            $this->assertIsArray($messages[0]['content']);
            $this->assertSame('text', $messages[0]['content'][0]['type']);
            $this->assertSame('Hello world', $messages[0]['content'][0]['text']);
            $this->assertSame(['type' => 'ephemeral'], $messages[0]['content'][0]['cache_control']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $payload = ['messages' => [['role' => 'user', 'content' => 'Hello world']]];
        $this->client->request($this->model, $payload);
    }

    public function testLongCacheRetentionInjectsEphemeralWithTtl()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'], true);
            $messages = $body['messages'];

            $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $messages[0]['content'][0]['cache_control']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'), 'long');

        $payload = ['messages' => [['role' => 'user', 'content' => 'Cache for an hour']]];
        $this->client->request($this->model, $payload);
    }

    public function testNoneCacheRetentionDoesNotInject()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'], true);
            $messages = $body['messages'];

            // Content must remain a plain string – no annotation
            $this->assertSame('Hello', $messages[0]['content']);
            $this->assertIsString($messages[0]['content']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'), 'none');

        $payload = ['messages' => [['role' => 'user', 'content' => 'Hello']]];
        $this->client->request($this->model, $payload);
    }

    public function testCacheRetentionInjectsOnLastBlockOfMultiBlockMessage()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'], true);
            $content = $body['messages'][0]['content'];

            $this->assertCount(2, $content);
            // Only the LAST block carries the annotation
            $this->assertArrayNotHasKey('cache_control', $content[0]);
            $this->assertSame(['type' => 'ephemeral'], $content[1]['cache_control']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $payload = ['messages' => [['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => 'First block'],
            ['type' => 'text', 'text' => 'Second block'],
        ]]]];
        $this->client->request($this->model, $payload);
    }

    public function testCacheRetentionAnnotatesLastUserMessageOnly()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'], true);
            $messages = $body['messages'];

            $this->assertCount(3, $messages);

            // First user message must NOT have cache_control
            $this->assertSame('First user message', $messages[0]['content']);

            // Assistant message is unchanged
            $this->assertSame('Acknowledged', $messages[1]['content']);

            // Second (last) user message carries the annotation
            $this->assertIsArray($messages[2]['content']);
            $this->assertSame(['type' => 'ephemeral'], $messages[2]['content'][0]['cache_control']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $payload = ['messages' => [
            ['role' => 'user', 'content' => 'First user message'],
            ['role' => 'assistant', 'content' => 'Acknowledged'],
            ['role' => 'user', 'content' => 'Second user message'],
        ]];
        $this->client->request($this->model, $payload);
    }

    public function testCacheRetentionAnnotatesToolResultBlock()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'], true);
            $messages = $body['messages'];

            $lastMessage = end($messages);
            $this->assertSame('user', $lastMessage['role']);
            $lastBlock = end($lastMessage['content']);
            $this->assertSame('tool_result', $lastBlock['type']);
            $this->assertSame(['type' => 'ephemeral'], $lastBlock['cache_control']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $payload = ['messages' => [
            ['role' => 'user', 'content' => 'Run the tool please'],
            ['role' => 'assistant', 'content' => [['type' => 'tool_use', 'id' => 'call_1', 'name' => 'my_tool', 'input' => ['arg' => 'val']]]],
            ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'call_1', 'content' => 'tool output']]],
        ]];
        $this->client->request($this->model, $payload);
    }

    public function testCacheRetentionWithNoMessagesKeyInPayload()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'], true);

            // Payload without messages key should pass through unchanged
            $this->assertSame('test', $body['data']);
            $this->assertArrayNotHasKey('messages', $body);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $payload = ['data' => 'test'];
        $this->client->request($this->model, $payload);
    }

    public function testCacheRetentionWithEmptyMessages()
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) {
            $body = json_decode($options['body'], true);

            $this->assertSame([], $body['messages']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'));

        $payload = ['messages' => []];
        $this->client->request($this->model, $payload);
    }

    /**
     * @param array{type: string, ttl?: string} $expectedCacheControl
     */
    #[DataProvider('cacheRetentionProvider')]
    public function testCacheControlShapeForRetentionValue(string $retention, array $expectedCacheControl)
    {
        $this->httpClient = new MockHttpClient(function ($method, $url, $options) use ($expectedCacheControl) {
            $body = json_decode($options['body'], true);
            $messages = $body['messages'];

            $this->assertSame($expectedCacheControl, $messages[0]['content'][0]['cache_control']);

            return new JsonMockResponse('{"success": true}');
        });

        $this->client = new MessagesClient(new HttpTransport($this->httpClient, 'test-api-key'), $retention);

        $payload = ['messages' => [['role' => 'user', 'content' => 'Test']]];
        $this->client->request($this->model, $payload);
    }

    /**
     * @return iterable<string, array{0: string, 1: array{type: string, ttl?: string}}>
     */
    public static function cacheRetentionProvider(): iterable
    {
        yield 'short' => ['short', ['type' => 'ephemeral']];
        yield 'long' => ['long', ['type' => 'ephemeral', 'ttl' => '1h']];
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, string>
     */
    private function parseHeaders(array $headers): array
    {
        $parsed = [];
        foreach ($headers as $header) {
            if (str_contains($header, ':')) {
                [$key, $value] = explode(':', $header, 2);
                $parsed[trim($key)] = trim($value);
            }
        }

        return $parsed;
    }
}
