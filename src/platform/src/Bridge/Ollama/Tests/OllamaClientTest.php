<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\OllamaClient;
use Symfony\AI\Platform\Bridge\Ollama\OllamaResultConverter;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OllamaClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new OllamaClient(new MockHttpClient(), 'http://127.0.0.1:1234');

        $this->assertTrue($client->supports(new Ollama('llama3.2')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testOutputStructureIsSupported()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'model' => 'foo',
                'response' => [
                    'age' => 22,
                    'available' => true,
                ],
                'done' => true,
            ]),
        ], 'http://127.0.0.1:1234');

        $client = new OllamaClient($httpClient, 'http://127.0.0.1:1234');
        $response = $client->request(new Ollama('llama3.2', [
            Capability::INPUT_MESSAGES,
            Capability::TOOL_CALLING,
        ]), [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Ollama is 22 years old and is busy saving the world. Respond using JSON',
                ],
            ],
            'model' => 'llama3.2',
        ], [
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'clock',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'age' => ['type' => 'integer'],
                            'available' => ['type' => 'boolean'],
                        ],
                        'required' => ['age', 'available'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame([
            'model' => 'foo',
            'response' => [
                'age' => 22,
                'available' => true,
            ],
            'done' => true,
        ], $response->getData());
    }

    public function testStreamingIsSupported()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('data: '.json_encode([
                'model' => 'llama3.2',
                'created_at' => '2025-08-23T10:00:00Z',
                'message' => ['role' => 'assistant', 'content' => 'Hello world'],
                'done' => true,
                'prompt_eval_count' => 10,
                'eval_count' => 10,
            ])."\n\n", [
                'response_headers' => [
                    'content-type' => 'text/event-stream',
                ],
            ]),
        ], 'http://127.0.0.1:1234');

        $platform = PlatformFactory::create('http://127.0.0.1:1234', $httpClient);
        $response = $platform->invoke('llama3.2', [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Say hello world',
                ],
            ],
            'model' => 'llama3.2',
        ], [
            'stream' => true,
        ]);

        $result = $response->getResult();

        $this->assertInstanceOf(StreamResult::class, $result);
        $this->assertInstanceOf(\Generator::class, $result->getContent());
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStreamingConverterWithDirectResponse()
    {
        $streamingData = 'data: '.json_encode([
            'model' => 'llama3.2',
            'created_at' => '2025-08-23T10:00:00Z',
            'message' => ['role' => 'assistant', 'content' => 'Hello'],
            'done' => false,
        ])."\n\n".
        'data: '.json_encode([
            'model' => 'llama3.2',
            'created_at' => '2025-08-23T10:00:01Z',
            'message' => ['role' => 'assistant', 'content' => ' world'],
            'done' => true,
        ])."\n\n";

        $mockHttpClient = new MockHttpClient([
            new MockResponse($streamingData, [
                'response_headers' => [
                    'content-type' => 'text/event-stream',
                ],
            ]),
        ]);

        $mockResponse = $mockHttpClient->request('GET', 'http://test.example');
        $rawResult = new RawHttpResult($mockResponse);
        $converter = new OllamaResultConverter();

        $result = $converter->convert($rawResult, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);
        $this->assertInstanceOf(\Generator::class, $result->getContent());

        $regularMockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => 'Hello world'],
                'done' => true,
            ]),
        ]);

        $regularMockResponse = $regularMockHttpClient->request('GET', 'http://test.example');
        $regularRawResult = new RawHttpResult($regularMockResponse);
        $regularResult = $converter->convert($regularRawResult, ['stream' => false]);

        $this->assertNotInstanceOf(StreamResult::class, $regularResult);
    }

    public function testChatRequestMovesNonTopLevelOptionsIntoNestedOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:1234/api/chat', $url);

            $json = $this->decodeRequestJson($options);

            $this->assertTrue($json['stream']);
            $this->assertFalse($json['think']);

            $this->assertArrayHasKey('options', $json);
            $this->assertIsArray($json['options']);
            $this->assertSame(0.2, $json['options']['temperature']);
            $this->assertSame(64, $json['options']['num_predict']);

            $this->assertArrayNotHasKey('temperature', $json);
            $this->assertArrayNotHasKey('num_predict', $json);

            return new JsonMockResponse([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'done' => true,
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new OllamaClient($httpClient, 'http://127.0.0.1:1234');

        $client->request(
            new Ollama('llama3.2', [Capability::INPUT_MESSAGES]),
            [
                'model' => 'llama3.2',
                'messages' => [
                    ['role' => 'user', 'content' => 'hi'],
                ],
            ],
            [
                'stream' => true,
                'think' => false,
                'temperature' => 0.2,
                'num_predict' => 64,
            ]
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testChatRequestMergesExplicitNestedOptionsWithFlatOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $json = $this->decodeRequestJson($options);

            $this->assertArrayHasKey('options', $json);
            $this->assertSame(0.2, $json['options']['temperature']);
            $this->assertSame(64, $json['options']['num_predict']);
            $this->assertSame(1024, $json['options']['num_ctx']);

            return new JsonMockResponse([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'done' => true,
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new OllamaClient($httpClient, 'http://127.0.0.1:1234');

        $client->request(
            new Ollama('llama3.2', [Capability::INPUT_MESSAGES]),
            [
                'model' => 'llama3.2',
                'messages' => [
                    ['role' => 'user', 'content' => 'hi'],
                ],
            ],
            [
                'temperature' => 0.2,
                'options' => [
                    'num_predict' => 64,
                    'num_ctx' => 1024,
                ],
            ]
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testChatRequestKeepsStructuredOutputFormatOnTopLevel()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $json = $this->decodeRequestJson($options);

            $this->assertArrayHasKey('format', $json);
            $this->assertArrayNotHasKey('format', $json['options'] ?? []);

            return new JsonMockResponse([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => '{"ok":true}'],
                'done' => true,
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new OllamaClient($httpClient, 'http://127.0.0.1:1234');

        $client->request(
            new Ollama('llama3.2', [Capability::INPUT_MESSAGES]),
            [
                'model' => 'llama3.2',
                'messages' => [
                    ['role' => 'user', 'content' => 'respond in json'],
                ],
            ],
            [
                PlatformSubscriber::RESPONSE_FORMAT => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'x',
                        'schema' => ['type' => 'object'],
                    ],
                ],
                'temperature' => 0.2,
            ]
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testEmbedRequestMovesNonTopLevelOptionsIntoNestedOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:1234/api/embed', $url);

            $json = $this->decodeRequestJson($options);

            $this->assertSame('embeddinggemma', $json['model']);
            $this->assertSame('hello', $json['input']);

            $this->assertFalse($json['truncate']);
            $this->assertSame(512, $json['dimensions']);

            $this->assertArrayHasKey('options', $json);
            $this->assertSame(0.1, $json['options']['temperature']);

            $this->assertArrayNotHasKey('temperature', $json);

            return new JsonMockResponse([
                'model' => 'embeddinggemma',
                'embeddings' => [[0.1, 0.2]],
            ]);
        }, 'http://127.0.0.1:1234');

        $client = new OllamaClient($httpClient, 'http://127.0.0.1:1234');

        $client->request(
            new Ollama('embeddinggemma', [Capability::EMBEDDINGS]),
            'hello',
            [
                'truncate' => false,
                'dimensions' => 512,
                'temperature' => 0.1,
            ]
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function decodeRequestJson(array $options): array
    {
        $this->assertArrayHasKey('body', $options, 'Expected "body" in MockHttpClient options.');
        $this->assertIsString($options['body']);

        $data = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);

        return $data;
    }
}
