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
}
