<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Fireworks\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Fireworks\Fireworks;
use Symfony\AI\Platform\Bridge\Fireworks\FireworksResultConverter;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\InvalidRequestException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FireworksResultConverterTest extends TestCase
{
    public function testSupportsFireworksModel()
    {
        $converter = new FireworksResultConverter();

        $this->assertTrue($converter->supports(new Fireworks('accounts/fireworks/models/kimi-k2p6')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $converter = new FireworksResultConverter();

        $this->assertFalse($converter->supports(new Model('gpt-4')));
    }

    public function testConvertTextResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello, how can I help you?'],
                    'finish_reason' => 'stop',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions');
        $converter = new FireworksResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello, how can I help you?', $result->getContent());
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
                                'function' => ['name' => 'get_weather', 'arguments' => '{"location":"Paris"}'],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions');
        $converter = new FireworksResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertSame('call_abc123', $result->getContent()[0]->getId());
        $this->assertSame('get_weather', $result->getContent()[0]->getName());
        $this->assertSame(['location' => 'Paris'], $result->getContent()[0]->getArguments());
    }

    public function testConvertEmbeddingsResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'object' => 'list',
            'data' => [
                ['object' => 'embedding', 'index' => 0, 'embedding' => [0.3, 0.4, 0.4]],
                ['object' => 'embedding', 'index' => 1, 'embedding' => [0.0, 0.0, 0.2]],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/embeddings');
        $converter = new FireworksResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(VectorResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertSame([0.3, 0.4, 0.4], $result->getContent()[0]->getData());
        $this->assertSame([0.0, 0.0, 0.2], $result->getContent()[1]->getData());
    }

    public function testConvertRerankResponse()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'object' => 'list',
            'data' => [
                ['index' => 0, 'relevance_score' => 0.95],
                ['index' => 1, 'relevance_score' => 0.42],
            ],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/rerank');
        $converter = new FireworksResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(RerankingResult::class, $result);
        $entries = $result->getContent();
        $this->assertCount(2, $entries);
        $this->assertSame(0, $entries[0]->getIndex());
        $this->assertSame(0.95, $entries[0]->getScore());
        $this->assertSame(1, $entries[1]->getIndex());
        $this->assertSame(0.42, $entries[1]->getScore());
    }

    public function testConvertImageResponse()
    {
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+P+/PwAGJgBL5gX7DQAAAABJRU5ErkJggg==';
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'base64' => ['data:image/png;base64,'.$base64],
            'finishReason' => 'SUCCESS',
            'seed' => 42,
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/workflows/accounts/fireworks/models/flux-1-schnell-fp8/text_to_image');
        $converter = new FireworksResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image/png', $result->getMimeType());
        $this->assertSame(base64_decode($base64, true), $result->getContent());
    }

    public function testConvertImageResponseReturnsMultiPartForMultipleImages()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'base64' => ['data:image/png;base64,AAAA', 'data:image/jpeg;base64,BBBB'],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/workflows/accounts/fireworks/models/flux-1-schnell-fp8/text_to_image');
        $converter = new FireworksResultConverter();

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $this->assertCount(2, $result->getContent());
    }

    public function testConvertThrowsContentFilterException()
    {
        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content filtered');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => ['code' => 'content_filter', 'message' => 'Content filtered'],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions');
        (new FireworksResultConverter())->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsExceedContextSizeExceptionOnContextOverflow()
    {
        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('This model maximum context length is 65536 tokens');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => [
                'message' => 'This model maximum context length is 65536 tokens. However, you requested 600018 tokens.',
                'type' => 'invalid_request_error',
                'code' => 'invalid_request_error',
            ],
        ], ['http_code' => 400]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions');
        (new FireworksResultConverter())->convert(new RawHttpResult($httpResponse));
    }

    public function testConvertThrowsInvalidRequestException()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Invalid request');

        $httpClient = new MockHttpClient(new JsonMockResponse([
            'error' => ['code' => 'invalid_request_error', 'message' => 'Invalid request'],
        ]));

        $httpResponse = $httpClient->request('POST', 'https://api.fireworks.ai/inference/v1/chat/completions');
        (new FireworksResultConverter())->convert(new RawHttpResult($httpResponse));
    }

    public function testStreamingTextWithoutReasoningUnchanged()
    {
        $converter = new FireworksResultConverter();

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
        $this->assertSame(FinishReasonCase::STOP, $chunks[2]->getValue()->getCase());
    }

    public function testStreamingReasoningContentYieldsThinkingComplete()
    {
        $converter = new FireworksResultConverter();

        $events = [
            ['choices' => [['index' => 0, 'delta' => ['reasoning_content' => 'Let me ']]]],
            ['choices' => [['index' => 0, 'delta' => ['reasoning_content' => 'think about this.']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'The answer ']]]],
            ['choices' => [['index' => 0, 'delta' => ['content' => 'is 42.']]]],
            ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $raw = new InMemoryRawResult(dataStream: $events);
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $thinkingDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingDelta));
        $this->assertCount(2, $thinkingDeltas);
        $this->assertSame('Let me ', $thinkingDeltas[0]->getThinking());
        $this->assertSame('think about this.', $thinkingDeltas[1]->getThinking());

        $thinkingCompletes = array_values(array_filter($chunks, static fn ($c) => $c instanceof ThinkingComplete));
        $this->assertCount(1, $thinkingCompletes);
        $this->assertSame('Let me think about this.', $thinkingCompletes[0]->getThinking());

        $textDeltas = array_values(array_filter($chunks, static fn ($c) => $c instanceof TextDelta));
        $this->assertCount(2, $textDeltas);
        $this->assertSame('The answer ', $textDeltas[0]->getText());
        $this->assertSame('is 42.', $textDeltas[1]->getText());
    }
}
