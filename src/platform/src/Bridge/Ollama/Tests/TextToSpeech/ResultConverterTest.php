<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests\TextToSpeech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech\Format;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech\ResultConverter;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testSupportsTextToSpeechModel()
    {
        $converter = new ResultConverter();

        $this->assertTrue($converter->supports(new TextToSpeech('phonon')));
        $this->assertFalse($converter->supports(new Ollama('llama3.2')));
    }

    public function testItReturnsWavAudioAsBinaryByDefault()
    {
        $result = (new ResultConverter())->convert($this->createRawResult('fake-wav-bytes'));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('fake-wav-bytes', $result->getContent());
        $this->assertSame('audio/wav', $result->getMimeType());
    }

    public function testItReturnsPcmAudioAsBinary()
    {
        $result = (new ResultConverter())->convert($this->createRawResult('fake-pcm-bytes'), [
            'response_format' => Format::PCM,
        ]);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('fake-pcm-bytes', $result->getContent());
        $this->assertSame('audio/pcm', $result->getMimeType());
    }

    public function testItDoesNotGuessTheMimeTypeOfAnUnknownFormat()
    {
        $result = (new ResultConverter())->convert($this->createRawResult('fake-audio-bytes'), [
            'response_format' => 'mp3',
        ]);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertNull($result->getMimeType());
    }

    public function testItUsesTheResponseContentTypeOverTheOption()
    {
        $httpClient = new MockHttpClient(
            new MockResponse('fake-audio-bytes', ['response_headers' => ['content-type' => ['audio/pcm; charset=binary']]]),
            'http://127.0.0.1:11434',
        );

        $result = (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')), [
            'response_format' => Format::WAV,
        ]);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('audio/pcm', $result->getMimeType());
    }

    public function testItThrowsOnServerError()
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Ollama is overloaded');

        $httpClient = new MockHttpClient(
            new JsonMockResponse(['error' => 'Ollama is overloaded'], ['http_code' => 500]),
            'http://127.0.0.1:11434',
        );

        (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')));
    }

    public function testItThrowsOnBadRequestWithTheOpenAiCompatibleError()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('voice "nope" not found; available voices: default');

        $httpClient = new MockHttpClient(
            new JsonMockResponse([
                'error' => [
                    'message' => 'voice "nope" not found; available voices: default',
                    'type' => 'invalid_request_error',
                    'param' => null,
                    'code' => null,
                ],
            ], ['http_code' => 400]),
            'http://127.0.0.1:11434',
        );

        (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')));
    }

    public function testItThrowsOnBadRequestWithTheOllamaError()
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('unknown voice "foo"; available voices: default, zoey');

        $httpClient = new MockHttpClient(
            new JsonMockResponse(['error' => 'unknown voice "foo"; available voices: default, zoey'], ['http_code' => 400]),
            'http://127.0.0.1:11434',
        );

        (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')));
    }

    public function testItThrowsOnUnexpectedSuccessStatusCode()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Ollama Text-to-Speech API returned an error: ""');

        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 204]), 'http://127.0.0.1:11434');

        (new ResultConverter())->convert(new RawHttpResult($httpClient->request('POST', '/v1/audio/speech')));
    }

    private function createRawResult(string $content): RawHttpResult
    {
        $httpClient = new MockHttpClient(new MockResponse($content), 'http://127.0.0.1:11434');

        return new RawHttpResult($httpClient->request('POST', '/v1/audio/speech'));
    }
}
