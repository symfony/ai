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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Bridge\Deepgram\ResultConverter;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testSupportsOnlyDeepgramModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT])));
    }

    public function testReturnsBinaryResultForRestSpeak()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('audio-bytes', ['response_headers' => ['content-type' => 'audio/mpeg']]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $result = (new ResultConverter($httpClient))->convert(new RawHttpResult($response));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio-bytes', $result->getContent());
    }

    public function testReturnsStreamResultForRestSpeakWithStreamOption()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(['chunk1', 'chunk2', 'chunk3']),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $result = (new ResultConverter($httpClient))->convert(new RawHttpResult($response), ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $result);
    }

    public function testReturnsTextResultForRestListen()
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

        $result = (new ResultConverter($httpClient))->convert(new RawHttpResult($response));

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

        $result = (new ResultConverter($httpClient))->convert(new RawHttpResult($response));

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
        $this->expectExceptionMessage('The Deepgram API returned an error: "Invalid credentials.".');

        (new ResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    public function testFallsBackToStatusCodeOnNonJsonError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('plain error body', ['http_code' => 500]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Deepgram API returned a non-successful status code "500".');

        (new ResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    public function testRejectsUnknownEndpoint()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{}', ['response_headers' => ['content-type' => 'application/json']]),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'unknown');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported Deepgram endpoint');

        (new ResultConverter($httpClient))->convert(new RawHttpResult($response));
    }

    public function testInMemoryBinaryResult()
    {
        $raw = new InMemoryRawResult(['content' => 'binary-audio', 'metadata' => null]);

        $result = (new ResultConverter())->convert($raw);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('binary-audio', $result->getContent());
    }

    public function testInMemoryTranscriptResult()
    {
        $raw = new InMemoryRawResult([
            'transcript' => 'hello there',
            'results' => ['channels' => [['alternatives' => [['transcript' => 'hello there']]]]],
            'metadata' => null,
        ]);

        $result = (new ResultConverter())->convert($raw);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('hello there', $result->getContent());
    }

    public function testInMemoryFallsBackToChannelShape()
    {
        $raw = new InMemoryRawResult([
            'channel' => ['alternatives' => [['transcript' => 'just one alt']]],
        ]);

        $result = (new ResultConverter())->convert($raw);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('just one alt', $result->getContent());
    }

    public function testStreamRequiresHttpClient()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('chunked-audio'),
        ], 'https://api.deepgram.com/v1/');

        $response = $httpClient->request('POST', 'speak');

        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Streaming responses require an HTTP client');

        $converter->convert(new RawHttpResult($response), ['stream' => true]);
    }
}
