<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\ResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testConvertCompletionToTextResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'index' => 0,
                        'message' => [
                            'content' => 'Hello! How can I help you?',
                            'role' => 'assistant',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 8,
                    'total_tokens' => 18,
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'chat/completions');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result);

        $this->assertInstanceOf(TextResult::class, $converted);
        $this->assertSame('Hello! How can I help you?', $converted->getContent());
    }

    public function testConvertStreamingCompletionToStreamResult()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getInfo')->willReturnCallback(static function (string $key): ?string {
            if ('url' === $key) {
                return 'https://api.venice.ai/api/v1/chat/completions';
            }

            return null;
        });

        $streamData = [
            ['choices' => [['delta' => ['content' => 'Hello']]]],
            ['choices' => [['delta' => ['content' => ' world']]]],
            ['choices' => [['delta' => ['content' => '!']]], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8]],
        ];

        $result = new InMemoryRawResult([], $streamData, $response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $converted);

        $chunks = [];
        foreach ($converted->getContent() as $chunk) {
            $chunks[] = $chunk;
        }

        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame(' world', $chunks[1]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[2]);
        $this->assertSame('!', $chunks[2]->getText());
        $this->assertInstanceOf(TokenUsage::class, $chunks[3]);
        $this->assertSame(5, $chunks[3]->getPromptTokens());
        $this->assertSame(3, $chunks[3]->getCompletionTokens());
        $this->assertSame(8, $chunks[3]->getTotalTokens());
    }

    public function testConvertSpeechToBinaryResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'audio/speech');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result);

        $this->assertInstanceOf(BinaryResult::class, $converted);
    }

    public function testConvertEmbeddingsToVectorResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'embedding' => [0.1, 0.2, 0.3],
                        'index' => 0,
                        'object' => 'embedding',
                    ],
                ],
                'model' => 'text-embedding-bge-m3',
                'object' => 'list',
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'embeddings');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result);

        $this->assertInstanceOf(VectorResult::class, $converted);
    }

    public function testConvertImageGenerationToBinaryResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'images' => [base64_encode('fake-image-binary-data')],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'image/generate');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();
        $converted = $converter->convert($result);

        $this->assertInstanceOf(BinaryResult::class, $converted);
        $this->assertSame('fake-image-binary-data', $converted->getContent());
    }

    public function testConvertUnsupportedThrowsException()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'unknown/endpoint');
        $result = new RawHttpResult($response);

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported model capability.');

        $converter->convert($result);
    }

    public function testConvertCompletionWithToolCalls()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'tool_calls' => [[
                            'id' => 'call_123',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_weather',
                                'arguments' => '{"city":"Paris"}',
                            ],
                        ]],
                    ],
                ]],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'chat/completions');
        $converted = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ToolCallResult::class, $converted);
        $calls = $converted->getContent();
        $this->assertCount(1, $calls);
        $this->assertSame('call_123', $calls[0]->getId());
        $this->assertSame('get_weather', $calls[0]->getName());
        $this->assertSame(['city' => 'Paris'], $calls[0]->getArguments());
    }

    public function testConvertCompletionWithoutContentThrows()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['choices' => [['message' => ['role' => 'assistant', 'content' => '']]]]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'chat/completions');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No completions found in the response.');
        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    public function testConvertTranscriptionToTextResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['text' => 'Hello world', 'duration' => 12]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'audio/transcriptions');
        $converted = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $converted);
        $this->assertSame('Hello world', $converted->getContent());
    }

    public function testConvertTranscriptionMissingTextThrows()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['duration' => 12]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'audio/transcriptions');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No transcription text found in the response.');
        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    public function testConvertVideoRetrieveToBinary()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], ['response_headers' => ['content-type' => 'video/mp4']]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'video/retrieve');
        $converted = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $converted);
    }

    public function testConvertImageGenerationWithMultipleImagesReturnsChoiceResult()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'images' => [
                    base64_encode('img-1'),
                    base64_encode('img-2'),
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'image/generate');
        $converted = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(ChoiceResult::class, $converted);
        $choices = $converted->getContent();
        $this->assertCount(2, $choices);
        $this->assertSame('img-1', $choices[0]->getContent());
        $this->assertSame('img-2', $choices[1]->getContent());
    }

    public function testConvertImageGenerationMissingImagesThrows()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['images' => []]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'image/generate');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No images found in the response.');
        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    public function testConvertImageWithBinaryContentTypeReturnsBinaryDirectly()
    {
        $httpClient = new MockHttpClient([
            new \Symfony\Component\HttpClient\Response\MockResponse('raw-image-bytes', [
                'response_headers' => ['content-type' => 'image/png'],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'image/upscale');
        $converted = (new ResultConverter())->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $converted);
        $this->assertSame('raw-image-bytes', $converted->getContent());
    }

    public function testConvertEmbeddingsMissingDataThrows()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['data' => []]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'embeddings');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No embeddings found in the response.');
        (new ResultConverter())->convert(new RawHttpResult($response));
    }

    public function testConvertStreamingWithReasoningAndToolCalls()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getInfo')->willReturnCallback(static function (string $key): ?string {
            return 'url' === $key ? 'https://api.venice.ai/api/v1/chat/completions' : null;
        });

        $streamData = [
            ['choices' => [['delta' => ['reasoning_content' => 'Let me think...']]]],
            ['choices' => [['delta' => ['content' => 'The answer is 42.']]]],
            ['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_42',
                'function' => ['name' => 'get_answer', 'arguments' => '{"q":"life"}'],
            ]]]]]],
        ];

        $result = new InMemoryRawResult([], $streamData, $response);
        $converted = (new ResultConverter())->convert($result, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $converted);

        $chunks = iterator_to_array($converted->getContent());

        $this->assertInstanceOf(ThinkingDelta::class, $chunks[0]);
        $this->assertSame('Let me think...', $chunks[0]->getThinking());

        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('The answer is 42.', $chunks[1]->getText());

        $toolCallChunk = $chunks[2];
        $this->assertInstanceOf(ToolCallComplete::class, $toolCallChunk);
        $calls = $toolCallChunk->getToolCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('call_42', $calls[0]->getId());
        $this->assertSame('get_answer', $calls[0]->getName());
        $this->assertSame(['q' => 'life'], $calls[0]->getArguments());
    }
}
