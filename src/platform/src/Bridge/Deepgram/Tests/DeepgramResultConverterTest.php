<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Bridge\Deepgram\DeepgramResultConverter;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeepgramResultConverterTest extends TestCase
{
    public function testSupportsOnlyDeepgramModel()
    {
        $converter = new DeepgramResultConverter(new MockHttpClient());

        $this->assertTrue($converter->supports(new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT])));
        $this->assertFalse($converter->supports(new Model('gpt-4')));
    }

    public function testRejectsNonHttpRawResult()
    {
        $converter = new DeepgramResultConverter(new MockHttpClient());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported raw result of type');

        $converter->convert(new InMemoryRawResult(['content' => 'audio']));
    }

    public function testReturnsBinaryResultForSpeak()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('audio-bytes', ['response_headers' => ['content-type' => 'audio/mpeg']]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $result = (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-bytes', $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testBinaryResultFallsBackToAudioMpegWithoutContentTypeHeader()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('audio-bytes'),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $result = (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testStreamResultYieldsBinaryDeltas()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(['chunk1', 'chunk2', 'chunk3']),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $result = (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);

        $content = '';
        foreach ($result->getContent() as $delta) {
            $this->assertInstanceOf(BinaryDelta::class, $delta);
            $content .= $delta->getData();
        }

        $this->assertSame('chunk1chunk2chunk3', $content);
    }

    public function testEndpointIsDetectedFromThePathOnly()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    'channels' => [
                        ['alternatives' => [['transcript' => 'routed as listen']]],
                    ],
                ],
            ]),
        ], 'https://proxy.corp/speakers/v1/');

        $response = $httpClient->request('POST', 'listen');

        $result = (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('routed as listen', $result->getContent());
    }

    public function testReturnsTextResultForListen()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    'channels' => [
                        ['alternatives' => [['transcript' => 'hello world']]],
                    ],
                ],
            ]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $result = (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('hello world', $result->getContent());
    }

    public function testConcatenatesMultiChannelTranscripts()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'results' => [
                    'channels' => [
                        ['alternatives' => [['transcript' => 'left channel']]],
                        ['alternatives' => [['transcript' => 'right channel']]],
                    ],
                ],
            ]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $result = (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('left channel right channel', $result->getContent());
    }

    public function testSurfacesDeepgramErrorMessageOnNon200()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(
                ['err_code' => 'INVALID_AUTH', 'err_msg' => 'Invalid credentials.'],
                ['http_code' => 401],
            ),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401 returned for "https://api.deepgram.com/v1/listen": Invalid credentials.');

        (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @param array<string, string> $body
     */
    #[DataProvider('provideErrorMessageKeys')]
    public function testSurfacesErrorMessageFromFallbackKeys(array $body, string $expectedMessage)
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($body, ['http_code' => 400]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function provideErrorMessageKeys(): iterable
    {
        yield 'error key' => [['error' => 'Bad request.'], 'HTTP 400 returned for "https://api.deepgram.com/v1/listen": Bad request.'];
        yield 'reason key' => [['reason' => 'Quota exceeded.'], 'HTTP 400 returned for "https://api.deepgram.com/v1/listen": Quota exceeded.'];
        yield 'message key' => [['message' => 'Invalid model.'], 'HTTP 400 returned for "https://api.deepgram.com/v1/listen": Invalid model.'];
        yield 'no known key' => [['foo' => 'bar'], 'HTTP 400 returned for "https://api.deepgram.com/v1/listen".'];
    }

    public function testFallsBackToStatusCodeOnNonJsonError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('plain error body', ['http_code' => 500]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Server error (HTTP 500). HTTP 500 returned for "https://api.deepgram.com/v1/speak".');

        (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @param class-string<\Throwable> $expectedException
     */
    #[DataProvider('provideTypedExceptionStatuses')]
    public function testMapsHttpStatusToTypedException(int $statusCode, string $expectedException)
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['err_msg' => 'Something went wrong.'], ['http_code' => $statusCode]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException($expectedException);

        (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @return iterable<string, array{int, class-string<\Throwable>}>
     */
    public static function provideTypedExceptionStatuses(): iterable
    {
        yield '400 bad request' => [400, BadRequestException::class];
        yield '401 unauthorized' => [401, AuthenticationException::class];
        yield '404 not found' => [404, ModelNotFoundException::class];
        yield '429 rate limited' => [429, RateLimitExceededException::class];
        yield '500 server error' => [500, ServerException::class];
        yield '503 server error' => [503, ServerException::class];
    }

    public function testFallsBackToGenericExceptionOnUnhandledStatus()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['err_msg' => 'Payment required.'], ['http_code' => 402]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Deepgram API returned an error: "Payment required.".');

        (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    public function testRejectsUnknownEndpoint()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{}', ['response_headers' => ['content-type' => 'application/json']]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'unknown');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported Deepgram endpoint');

        (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    public function testRejectsTranscriptionResponseWithoutChannels()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['request_id' => 'abc-123']),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected Deepgram transcription response: the "results.channels" entry is missing.');

        (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideDegenerateTranscriptionPayloads')]
    public function testReturnsEmptyTranscriptForDegenerateChannels(array $body)
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse($body),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'listen');

        $result = (new DeepgramResultConverter($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideDegenerateTranscriptionPayloads(): iterable
    {
        yield 'empty channels' => [['results' => ['channels' => []]]];
        yield 'non-array channel' => [['results' => ['channels' => ['junk']]]];
        yield 'missing alternatives' => [['results' => ['channels' => [['foo' => 'bar']]]]];
        yield 'empty transcript' => [['results' => ['channels' => [['alternatives' => [['transcript' => '']]]]]]];
    }
}
