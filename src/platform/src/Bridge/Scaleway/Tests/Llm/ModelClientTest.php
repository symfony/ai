<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Scaleway\Llm;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Scaleway\Llm\ModelClient;
use Symfony\AI\Platform\Bridge\Scaleway\Scaleway;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class ModelClientTest extends TestCase
{
    public function testItAcceptsValidApiKey()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'scaleway-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'scaleway-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new ModelClient($httpClient, 'scaleway-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($modelClient->supports(new Scaleway('deepseek-r1-distill-llama-70b')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame([
                'temperature' => 1,
                'model' => 'deepseek-r1-distill-llama-70b',
                'messages' => [
                    ['role' => 'user', 'content' => 'test message'],
                ],
            ], json_decode($options['body'], true));

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['model' => 'deepseek-r1-distill-llama-70b', 'messages' => [['role' => 'user', 'content' => 'test message']]], ['temperature' => 1]);
    }

    public function testItIsExecutingTheCorrectRequestWithArrayPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame([
                'temperature' => 0.7,
                'model' => 'deepseek-r1-distill-llama-70b',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
            ], json_decode($options['body'], true));

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['model' => 'deepseek-r1-distill-llama-70b', 'messages' => [['role' => 'user', 'content' => 'Hello']]], ['temperature' => 0.7]);
    }

    public function testItUsesCorrectBaseUrl()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/chat/completions', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Scaleway('deepseek-r1-distill-llama-70b'), ['messages' => []], []);
    }

    public function testItUsesResponsesApiForGptOssModel()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/responses', $url);
            self::assertSame('Authorization: Bearer scaleway-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame([
                'model' => 'gpt-oss-120b',
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'Hello',
                            ],
                        ],
                    ],
                ],
            ], json_decode($options['body'], true));

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');
        $modelClient->request(new Scaleway('gpt-oss-120b'), ['messages' => [['role' => 'user', 'content' => 'Hello']]], []);
    }

    public function testItConvertsToolsForResponsesApi()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/responses', $url);

            $body = json_decode($options['body'], true);

            // Verify tools are converted from Chat Completions format to Responses API format
            self::assertSame([
                [
                    'type' => 'function',
                    'name' => 'get_weather',
                    'description' => 'Get weather for a location',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => ['type' => 'string'],
                        ],
                    ],
                ],
            ], $body['tools']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');

        // Send tools in Chat Completions format
        $modelClient->request(new Scaleway('gpt-oss-120b'), [
            'messages' => [['role' => 'user', 'content' => 'What is the weather?']],
        ], [
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_weather',
                        'description' => 'Get weather for a location',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'location' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testItConvertsToolMessagesForResponsesApi()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.scaleway.ai/v1/responses', $url);

            $body = json_decode($options['body'], true);

            // Verify the conversation with tool calls is converted correctly
            self::assertSame([
                // User message
                [
                    'role' => 'user',
                    'content' => [['type' => 'input_text', 'text' => 'What time is it?']],
                ],
                // Assistant's function call (from tool_calls)
                [
                    'type' => 'function_call',
                    'call_id' => 'call_123',
                    'name' => 'get_time',
                    'arguments' => '{}',
                ],
                // Tool result converted to function_call_output
                [
                    'type' => 'function_call_output',
                    'call_id' => 'call_123',
                    'output' => '2025-12-17T12:00:00Z',
                ],
            ], $body['input']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'scaleway-api-key');

        // Send a conversation with tool calls in Chat Completions format
        $modelClient->request(new Scaleway('gpt-oss-120b'), [
            'messages' => [
                ['role' => 'user', 'content' => 'What time is it?'],
                [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => [
                        [
                            'id' => 'call_123',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_time',
                                'arguments' => '{}',
                            ],
                        ],
                    ],
                ],
                [
                    'role' => 'tool',
                    'tool_call_id' => 'call_123',
                    'content' => '2025-12-17T12:00:00Z',
                ],
            ],
        ], []);
    }
}
