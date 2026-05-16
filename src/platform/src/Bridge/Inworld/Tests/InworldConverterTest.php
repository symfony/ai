<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Inworld\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Inworld\Inworld;
use Symfony\AI\Platform\Bridge\Inworld\InworldResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\Delta\BinaryDelta;
use Symfony\AI\Platform\Result\Stream\NdjsonStream;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class InworldConverterTest extends TestCase
{
    public function testSupportsModel()
    {
        $converter = new InworldResultConverter();

        $this->assertTrue($converter->supports(new Inworld('inworld-tts-2')));
        $this->assertFalse($converter->supports(new Model('any-model')));
    }

    public function testConvertTextToSpeechResponse()
    {
        $audio = 'binary-audio-content';
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'audioContent' => base64_encode($audio),
                'usage' => [
                    'processedCharactersCount' => 11,
                    'modelId' => 'inworld-tts-2',
                ],
            ]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.inworld.ai/tts/v1/voice'));

        $converter = new InworldResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame($audio, $result->getContent());
        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testConvertTextToSpeechResponseThrowsWhenAudioMissing()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'audioContent' => '',
            ]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.inworld.ai/tts/v1/voice'));

        $converter = new InworldResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Inworld API returned an empty audio content.');
        $this->expectExceptionCode(0);
        $converter->convert($rawResult);
    }

    public function testConvertTextToSpeechAsStreamResponse()
    {
        $chunk1 = 'first-chunk-bytes';
        $chunk2 = 'second-chunk-bytes';

        $body = json_encode([
            'result' => [
                'audioContent' => base64_encode($chunk1),
                'usage' => ['processedCharactersCount' => 5, 'modelId' => 'inworld-tts-2'],
            ],
        ])."\n".json_encode([
            'result' => [
                'audioContent' => base64_encode($chunk2),
                'usage' => ['processedCharactersCount' => 6, 'modelId' => 'inworld-tts-2'],
            ],
        ])."\n";

        $httpClient = new EventSourceHttpClient(new MockHttpClient([new MockResponse($body)]));
        $response = $httpClient->request('POST', 'https://api.inworld.ai/tts/v1/voice:stream');
        $rawResult = new RawHttpResult($response, new NdjsonStream());

        $converter = new InworldResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(StreamResult::class, $result);

        $deltas = [];

        foreach ($result->getContent() as $delta) {
            $this->assertInstanceOf(BinaryDelta::class, $delta);
            $deltas[] = $delta;
        }

        $this->assertCount(2, $deltas);
        $this->assertSame($chunk1, $deltas[0]->getData());
        $this->assertSame('audio/mpeg', $deltas[0]->getMimeType());
        $this->assertSame($chunk2, $deltas[1]->getData());
    }

    public function testConvertTextToSpeechStreamSkipsEmptyAudioContent()
    {
        $body = json_encode([
            'result' => [
                'audioContent' => '',
                'usage' => ['processedCharactersCount' => 0, 'modelId' => 'inworld-tts-2'],
            ],
        ])."\n".json_encode([
            'result' => [
                'audioContent' => base64_encode('useful-chunk'),
            ],
        ])."\n";

        $httpClient = new EventSourceHttpClient(new MockHttpClient([new MockResponse($body)]));
        $response = $httpClient->request('POST', 'https://api.inworld.ai/tts/v1/voice:stream');
        $rawResult = new RawHttpResult($response, new NdjsonStream());

        $converter = new InworldResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(StreamResult::class, $result);

        $deltas = iterator_to_array($result->getContent(), false);

        $this->assertCount(1, $deltas);
        $this->assertInstanceOf(BinaryDelta::class, $deltas[0]);
        $this->assertSame('useful-chunk', $deltas[0]->getData());
    }

    public function testConvertSpeechToTextResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'transcription' => [
                    'transcript' => 'Hello there',
                    'isFinal' => true,
                ],
            ]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.inworld.ai/stt/v1/transcribe'));

        $converter = new InworldResultConverter();
        $result = $converter->convert($rawResult);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello there', $result->getContent());
    }

    public function testConvertSpeechToTextResponseThrowsWhenTranscriptMissing()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'transcription' => [],
            ]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.inworld.ai/stt/v1/transcribe'));

        $converter = new InworldResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Inworld API returned an invalid transcription payload.');
        $this->expectExceptionCode(0);
        $converter->convert($rawResult);
    }

    public function testConvertThrowsExceptionWithDetailedErrorMessage()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 5,
                'message' => 'Unknown voice: John not found!',
                'details' => [],
            ], ['http_code' => 400]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.inworld.ai/tts/v1/voice'));

        $converter = new InworldResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown voice: John not found!');
        $this->expectExceptionCode(0);
        $converter->convert($rawResult);
    }

    public function testConvertThrowsExceptionFromNestedErrorPayload()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'error' => [
                    'code' => 3,
                    'message' => 'Invalid argument',
                ],
            ], ['http_code' => 400]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.inworld.ai/tts/v1/voice'));

        $converter = new InworldResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid argument');
        $this->expectExceptionCode(0);
        $converter->convert($rawResult);
    }

    public function testConvertThrowsExceptionWithoutErrorMessage()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 500]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.inworld.ai/tts/v1/voice'));

        $converter = new InworldResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Inworld API returned a non-successful status code "500".');
        $this->expectExceptionCode(0);
        $converter->convert($rawResult);
    }

    public function testConvertThrowsForUnknownUrl()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ]);
        $rawResult = new RawHttpResult($httpClient->request('POST', 'https://api.inworld.ai/unknown/endpoint'));

        $converter = new InworldResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported Inworld response.');
        $this->expectExceptionCode(0);
        $converter->convert($rawResult);
    }
}
