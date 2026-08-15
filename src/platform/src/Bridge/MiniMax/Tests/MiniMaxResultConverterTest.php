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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\MiniMax;
use Symfony\AI\Platform\Bridge\MiniMax\MiniMaxResultConverter;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\JobResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
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
        $converter = new MiniMaxResultConverter();

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
        $converter = new MiniMaxResultConverter();

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

        $converter = new MiniMaxResultConverter();
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
        $converter = new MiniMaxResultConverter();

        $result = $converter->convert($raw);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_AUDIO', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testItConvertsAsynchronousSpeechIntoAJobHandle()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['task_id' => '123', 'file_id' => '456']));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/t2a_async_v2'));

        $result = (new MiniMaxResultConverter())->convert($raw);

        $this->assertInstanceOf(JobResult::class, $result);

        $handle = $result->getContent();
        $this->assertSame('123', $handle->getId());
        $this->assertSame('query/t2a_async_query_v2', $handle->get('query_path'));
        $this->assertSame('audio/mpeg', $handle->get('mime_type'));
        $this->assertSame('mp3', $handle->get('archive_member'), 'the async endpoint delivers a tar the job client has to unpack');
        $this->assertSame('456', $handle->get('file_id'));

        // Converting must not touch the network anymore - that is the job client's business.
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItConvertsImageGenerationAsBinary()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => [
                'image_base64' => [base64_encode('FAKE_IMAGE')],
            ],
        ]));

        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/image_generation'));
        $converter = new MiniMaxResultConverter();

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
        $converter = new MiniMaxResultConverter();

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
        $converter = new MiniMaxResultConverter();

        $result = $converter->convert($raw);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('FAKE_MUSIC', $result->getContent());
    }

    public function testItConvertsVideoGenerationIntoAJobHandle()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['task_id' => '789']));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/video_generation'));

        $result = (new MiniMaxResultConverter())->convert($raw);

        $this->assertInstanceOf(JobResult::class, $result);

        $handle = $result->getContent();
        $this->assertSame('789', $handle->getId());
        $this->assertSame('query/video_generation', $handle->get('query_path'));
        $this->assertSame('video/mp4', $handle->get('mime_type'));
        $this->assertNull($handle->get('archive_member'), 'video is downloaded as-is');
        $this->assertNull($handle->get('file_id'));
    }

    /**
     * MiniMax reports a rejected request with HTTP 200 and the reason in `base_resp`. All three
     * payloads below were captured from api.minimax.io.
     *
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideRejectedRequests')]
    public function testItThrowsWhenTheProviderRejectedTheRequestWithHttpOk(string $endpoint, array $body, string $expectedMessage)
    {
        $httpClient = new MockHttpClient(new JsonMockResponse($body));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/'.$endpoint));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new MiniMaxResultConverter())->convert($raw);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideRejectedRequests(): iterable
    {
        yield 'async task on an empty account' => [
            't2a_async_v2',
            [
                'task_id' => 0,
                'task_token' => '',
                'file_id' => 0,
                'usage_characters' => 0,
                'base_resp' => ['status_code' => 1008, 'status_msg' => 'insufficient balance'],
            ],
            'MiniMax rejected the request: "insufficient balance" (status code "1008").',
        ];

        yield 'synchronous speech with an unknown voice' => [
            't2a_v2',
            ['base_resp' => ['status_code' => 2054, 'status_msg' => 'voice id not exist']],
            'MiniMax rejected the request: "voice id not exist" (status code "2054").',
        ];

        yield 'image generation with an unsupported model' => [
            'image_generation',
            [
                'id' => '',
                'data' => [],
                'base_resp' => ['status_code' => 2013, 'status_msg' => 'invalid params, unsupported model: nope-01'],
            ],
            'MiniMax rejected the request: "invalid params, unsupported model: nope-01" (status code "2013").',
        ];
    }

    public function testItAcceptsAResponseReportingSuccessInBaseResp()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'data' => ['audio' => bin2hex('FAKE_AUDIO')],
            'base_resp' => ['status_code' => 0, 'status_msg' => 'success'],
        ]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/t2a_v2'));

        $this->assertSame('FAKE_AUDIO', (new MiniMaxResultConverter())->convert($raw)->getContent());
    }

    public function testItThrowsWhenTheAsynchronousResponseHasNoTaskIdentifier()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['base_resp' => ['status_code' => 0, 'status_msg' => 'success']]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/video_generation'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not contain a task identifier');

        (new MiniMaxResultConverter())->convert($raw);
    }

    public function testItThrowsAuthenticationExceptionOnUnauthorized()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['message' => 'Invalid API key.'], ['http_code' => 401]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key.');

        $converter->convert($raw);
    }

    public function testItThrowsRateLimitExceededExceptionOnTooManyRequests()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['message' => 'Slow down.'], ['http_code' => 429]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter();

        $this->expectException(RateLimitExceededException::class);

        $converter->convert($raw);
    }

    public function testItThrowsServerExceptionOnServerError()
    {
        $httpClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter();

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error (HTTP 503');

        $converter->convert($raw);
    }

    public function testItThrowsServerExceptionBeforeStreaming()
    {
        $httpClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));
        $raw = new RawHttpResult($httpClient->request('POST', 'https://api.minimax.io/v1/chat/completions'));
        $converter = new MiniMaxResultConverter();

        $this->expectException(ServerException::class);

        $converter->convert($raw, ['stream' => true]);
    }

    public function testItThrowsIncompleteStreamWhenFinishReasonIsMissing()
    {
        $events = [
            ['object' => 'chat.completion.chunk', 'choices' => [['index' => 0, 'delta' => ['content' => 'Generated ']]]],
            // stream cut off: no chunk carrying a finish_reason
        ];

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $converter = new MiniMaxResultConverter();
        $result = $converter->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('The MiniMax stream ended before a finish reason.');

        iterator_to_array($result->getContent());
    }
}
