<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\MiniMax;
use Symfony\AI\Platform\Bridge\MiniMax\MiniMaxResultConverter;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MiniMaxResultConverterTest extends TestCase
{
    public function testItSupportsMiniMaxModels()
    {
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $this->assertTrue($converter->supports(new MiniMax('MiniMax-M2', [Capability::INPUT_MESSAGES])));
        $this->assertFalse($converter->supports(new Model('gpt-4')));
    }

    public function testItConvertsTextGeneration()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'index' => 0,
                    'message' => [
                        'content' => 'Generated text',
                        'role' => 'assistant',
                    ],
                ],
            ],
            'model' => 'MiniMax-M2',
            'object' => 'chat.completion',
        ]));

        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $result = $converter->convert($raw);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Generated text', $result->getContent());
    }

    public function testItConvertsTextGenerationAsStream()
    {
        $events = [
            ['object' => 'chat.completion.chunk', 'choices' => [['index' => 0, 'delta' => ['content' => 'Generated ']]]],
            ['object' => 'chat.completion.chunk', 'choices' => [['index' => 0, 'delta' => ['content' => 'text']]]],
            ['object' => 'chat.completion.chunk', 'choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']]],
        ];

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');
        $result = $converter->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $chunks = iterator_to_array($result->getContent());

        $this->assertCount(3, $chunks);
        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame('Generated ', $chunks[0]->getText());
        $this->assertSame('text', $chunks[1]->getText());
        $this->assertInstanceOf(MetadataDelta::class, $chunks[2]);
        $this->assertSame('finish_reason', $chunks[2]->getKey());
        $this->assertTrue($chunks[2]->getValue()->is(FinishReasonCase::STOP));
    }

    public function testItConvertsSynchronousSpeech()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => [
                'audio' => bin2hex('FAKE_AUDIO'),
                'status' => 2,
            ],
        ]));

        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/t2a_v2'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $result = $converter->convert($raw);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_AUDIO', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testItConvertsAsynchronousSpeechByPollingTheTask()
    {
        $createClient = new MockHttpClient(new JsonMockResponse(['task_id' => '123', 'file_id' => '456']));
        $raw = new RawHttpResult($createClient->request('POST', 'https://api.minimax.io/v1/t2a_async_v2'));

        $pollClient = new MockHttpClient([
            new JsonMockResponse(['status' => 'Processing']),
            new JsonMockResponse(['status' => 'Success', 'file_id' => '456']),
            new JsonMockResponse(['file' => ['download_url' => 'https://cdn.minimax.io/audio.mp3']]),
            new MockResponse('FAKE_ASYNC_AUDIO'),
        ]);

        $converter = new MiniMaxResultConverter($pollClient, 'key', 'https://api.minimax.io/v1', new MockClock());
        $result = $converter->convert($raw);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_ASYNC_AUDIO', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
        $this->assertSame(4, $pollClient->getRequestsCount());
    }

    public function testItConvertsImageGenerationAsBinary()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => [
                'image_base64' => [base64_encode('FAKE_IMAGE')],
            ],
        ]));

        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/image_generation'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $result = $converter->convert($raw);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_IMAGE', $result->getContent());
        $this->assertSame('image/jpeg', $result->getMimeType());
    }

    public function testItConvertsMultipleImagesAsChoiceResult()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => [
                'image_base64' => [base64_encode('FIRST'), base64_encode('SECOND')],
            ],
        ]));

        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/image_generation'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $result = $converter->convert($raw);

        $this->assertInstanceOf(ChoiceResult::class, $result);
        $this->assertCount(2, $result->getContent());
        $this->assertSame('FIRST', $result->getContent()[0]->getContent());
        $this->assertSame('SECOND', $result->getContent()[1]->getContent());
    }

    public function testItConvertsMusicGeneration()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => [
                'audio' => bin2hex('FAKE_MUSIC'),
            ],
        ]));

        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/music_generation'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $result = $converter->convert($raw);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_MUSIC', $result->getContent());
    }

    public function testItConvertsVideoGenerationByPollingTheTask()
    {
        $createClient = new MockHttpClient(new JsonMockResponse(['task_id' => '789']));
        $raw = new RawHttpResult($createClient->request('POST', 'https://api.minimax.io/v1/video_generation'));

        $pollClient = new MockHttpClient([
            new JsonMockResponse(['status' => 'Preparing']),
            new JsonMockResponse(['status' => 'Success', 'file_id' => '999']),
            new JsonMockResponse(['file' => ['download_url' => 'https://cdn.minimax.io/video.mp4']]),
            new MockResponse('FAKE_VIDEO'),
        ]);

        $converter = new MiniMaxResultConverter($pollClient, 'key', 'https://api.minimax.io/v1', new MockClock());
        $result = $converter->convert($raw);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_VIDEO', $result->getContent());
        $this->assertSame('video/mp4', $result->getMimeType());
    }

    public function testItThrowsAuthenticationExceptionOnUnauthorized()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['message' => 'Invalid API key.'], ['http_code' => 401]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key.');

        $converter->convert($raw);
    }

    public function testItThrowsRateLimitExceededExceptionOnTooManyRequests()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['message' => 'Slow down.'], ['http_code' => 429]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $this->expectException(RateLimitExceededException::class);

        $converter->convert($raw);
    }

    public function testItThrowsServerExceptionOnServerError()
    {
        $httpClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 503');

        $converter->convert($raw);
    }

    public function testItThrowsServerExceptionBeforeStreaming()
    {
        $httpClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');

        $this->expectException(ServerException::class);

        $converter->convert($raw, ['stream' => true]);
    }

    public function testItThrowsServerExceptionWhilePollingAsynchronousTask()
    {
        $createClient = new MockHttpClient(new JsonMockResponse(['task_id' => '123', 'file_id' => '456']));
        $raw = new RawHttpResult($createClient->request('POST', 'https://api.minimax.io/v1/t2a_async_v2'));

        $pollClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));

        $converter = new MiniMaxResultConverter($pollClient, 'key', 'https://api.minimax.io/v1', new MockClock());

        $this->expectException(ServerException::class);

        $converter->convert($raw);
    }

    public function testItThrowsIncompleteStreamWhenFinishReasonIsMissing()
    {
        $events = [
            ['object' => 'chat.completion.chunk', 'choices' => [['index' => 0, 'delta' => ['content' => 'Generated ']]]],
            // stream cut off: no chunk carrying a finish_reason
        ];

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new MiniMaxResultConverter(new MockHttpClient(), 'key');
        $result = $converter->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('The MiniMax stream ended before a finish reason.');

        iterator_to_array($result->getContent());
    }
}
