<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Together\Together;
use Symfony\AI\Platform\Bridge\Together\TogetherResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TogetherResultConverterTest extends TestCase
{
    public function testSupportsTogetherModel()
    {
        $converter = new TogetherResultConverter();

        $this->assertTrue($converter->supports(new Together('moonshotai/Kimi-K2.6')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new TogetherResultConverter();

        $this->assertFalse($converter->supports(new Model('gpt-4')));
    }

    public function testConvertTextResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello, how can I help you?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, how can I help you?', $result->getContent());
    }

    public function testConvertTextResponseWithEosFinishReason()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'The capital of Japan is Tokyo.',
                    ],
                    'finish_reason' => 'eos',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('The capital of Japan is Tokyo.', $result->getContent());
    }

    public function testConvertToolCallResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_abc123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location":"Paris"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertSame('call_abc123', $result->getContent()[0]->getId());
        $this->assertSame('get_weather', $result->getContent()[0]->getName());
        $this->assertSame(['location' => 'Paris'], $result->getContent()[0]->getArguments());
    }

    public function testConvertMultipleChoicesResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'First choice',
                    ],
                    'finish_reason' => 'stop',
                ],
                [
                    'index' => 1,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Second choice',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ChoiceResult::class, $result);
        $this->assertCount(2, $result->getContent());
    }

    public function testConvertEmbeddingsResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'object' => 'list',
            'model' => 'BAAI/bge-large-en-v1.5',
            'data' => [
                [
                    'object' => 'embedding',
                    'embedding' => [0.1, 0.2, 0.3],
                    'index' => 0,
                ],
                [
                    'object' => 'embedding',
                    'embedding' => [0.4, 0.5, 0.6],
                    'index' => 1,
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/embeddings');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertSame([0.1, 0.2, 0.3], $result->getContent()[0]->getData());
        $this->assertSame([0.4, 0.5, 0.6], $result->getContent()[1]->getData());
    }

    public function testConvertThrowsWhenEmbeddingItemIsInvalid()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'object' => 'list',
            'data' => [
                [
                    'object' => 'embedding',
                    'index' => 0,
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/embeddings');
        $converter = new TogetherResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain a valid "embedding" key.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsContentFilterException()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'code' => 'content_filter',
                'message' => 'Content filtered',
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content filtered');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsInvalidRequestException()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'type' => 'invalid_request_error',
                'message' => 'Invalid request',
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid request');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsRuntimeExceptionOnUnknownError()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'type' => 'server_error',
                'message' => 'Something went wrong',
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsAuthenticationExceptionOnUnauthorized()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'type' => 'authentication_error',
                'message' => 'Invalid API key',
            ],
        ], ['http_code' => 401]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsWhenChoicesAreMissing()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'id' => 'cmpl-123',
            'object' => 'chat.completion',
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Result does not contain choices.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertStreamResponse()
    {
        $converter = new TogetherResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['content' => 'Hello, ']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'world!']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $raw = new InMemoryRawResult(dataStream: $events);
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello, ', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('world!', $chunks[1]->getText());
        $this->assertInstanceOf(MetadataDelta::class, $chunks[2]);
        $this->assertSame('finish_reason', $chunks[2]->getKey());
        $finishReason = $chunks[2]->getValue();
        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertSame(FinishReasonCase::STOP, $finishReason->getCase());
    }

    public function testTokenUsageIsExtractedFromCompletionsResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello!',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/chat/completions');
        $converter = new TogetherResultConverter();

        $tokenUsage = $converter->getTokenUsageExtractor()->extract(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(20, $tokenUsage->getCompletionTokens());
        $this->assertSame(30, $tokenUsage->getTotalTokens());
    }

    public function testTokenUsageIsNullForEmbeddingsResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'object' => 'list',
            'data' => [
                [
                    'object' => 'embedding',
                    'embedding' => [0.1, 0.2, 0.3],
                    'index' => 0,
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.ai/v1/embeddings');
        $converter = new TogetherResultConverter();

        $this->assertNull($converter->getTokenUsageExtractor()->extract(new RawHttpResult($httpResponse)));
    }

    public function testConvertImageGenerationResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'id' => 'img-1',
            'object' => 'list',
            'model' => 'black-forest-labs/FLUX.1-schnell',
            'data' => [
                ['type' => 'b64_json', 'index' => 0, 'b64_json' => base64_encode('IMAGE_BYTES')],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.xyz/v1/images/generations');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('IMAGE_BYTES', $result->getContent());
        $this->assertSame('image/jpeg', $result->getMimeType());
    }

    public function testConvertImageGenerationResponseHonorsPngOutputFormat()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'object' => 'list',
            'data' => [
                ['type' => 'b64_json', 'index' => 0, 'b64_json' => base64_encode('PNG_BYTES')],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.xyz/v1/images/generations');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse), ['output_format' => 'png']);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/png', $result->getMimeType());
    }

    public function testConvertImageGenerationThrowsWhenMissingBase64()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'object' => 'list',
            'data' => [
                ['type' => 'url', 'index' => 0, 'url' => 'https://example.test/image.png'],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.xyz/v1/images/generations');
        $converter = new TogetherResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain base64-encoded image data');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertImageGenerationThrowsWhenNoImage()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['object' => 'list', 'data' => []]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.xyz/v1/images/generations');
        $converter = new TogetherResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain generated image data.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertSpeechResponseReturnsBinary()
    {
        $httpClient = new MockHttpClient(new MockResponse('AUDIO_BYTES'));

        $httpResponse = $httpClient->request('POST', 'https://api.together.xyz/v1/audio/speech');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse), ['response_format' => 'mp3']);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('AUDIO_BYTES', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testConvertTranscriptionResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['text' => 'Hello world']));

        $httpResponse = $httpClient->request('POST', 'https://api.together.xyz/v1/audio/transcriptions');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertRerankResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'object' => 'rerank',
            'model' => 'Salesforce/Llama-Rank-v1',
            'results' => [
                ['index' => 0, 'relevance_score' => 0.92, 'document' => ['text' => 'a']],
                ['index' => 2, 'relevance_score' => 0.13, 'document' => ['text' => 'c']],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.together.xyz/v1/rerank');
        $converter = new TogetherResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(RerankingResult::class, $result);
        $entries = $result->getContent();
        $this->assertCount(2, $entries);
        $this->assertSame(0, $entries[0]->getIndex());
        $this->assertSame(0.92, $entries[0]->getScore());
        $this->assertSame(2, $entries[1]->getIndex());
        $this->assertSame(0.13, $entries[1]->getScore());
    }

    public function testConvertRerankThrowsWhenNoResults()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['object' => 'rerank']));

        $httpResponse = $httpClient->request('POST', 'https://api.together.xyz/v1/rerank');
        $converter = new TogetherResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain reranking results.');

        $converter->convert(new RawHttpResult($httpResponse));
    }
}
