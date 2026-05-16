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
use Symfony\AI\Platform\Bridge\Inworld\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\Inworld\Inworld;
use Symfony\AI\Platform\Bridge\Inworld\InworldClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class InworldClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new InworldClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Inworld('inworld-tts-2')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientCannotPerformWithUnsupportedModel()
    {
        $client = new InworldClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" does not support text-to-speech or speech-to-text, please check the model information.');
        $this->expectExceptionCode(0);
        $client->request(new Inworld('foo', []), [
            'text' => 'bar',
        ]);
    }

    public function testClientCannotPerformTextToSpeechWithoutVoice()
    {
        $client = new InworldClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The voice option is required.');
        $this->expectExceptionCode(0);
        $client->request(new Inworld('inworld-tts-2', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'foo',
        ]);
    }

    public function testClientCannotPerformTextToSpeechWithoutTextPayload()
    {
        $client = new InworldClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key.');
        $this->expectExceptionCode(0);
        $client->request(new Inworld('inworld-tts-2', [Capability::TEXT_TO_SPEECH]), [], [
            'voice' => 'Dennis',
        ]);
    }

    public function testClientCanPerformTextToSpeech()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.inworld.ai/tts/v1/voice', $url);
            $this->assertIsString($options['body']);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->assertIsArray($body);
            $this->assertSame('Hello world', $body['text']);
            $this->assertSame('Dennis', $body['voiceId']);
            $this->assertSame('inworld-tts-2', $body['modelId']);
            $this->assertSame([
                'audioEncoding' => 'MP3',
                'sampleRateHertz' => 48000,
            ], $body['audioConfig']);
            $this->assertArrayNotHasKey('voice', $body);
            $this->assertArrayNotHasKey('stream', $body);

            return new JsonMockResponse([
                'audioContent' => base64_encode('binary-audio'),
            ]);
        }, 'https://api.inworld.ai/');

        $client = new InworldClient($httpClient);

        $result = $client->request(new Inworld('inworld-tts-2', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'Hello world',
        ], [
            'voice' => 'Dennis',
        ]);

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechWithStringPayload()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.inworld.ai/tts/v1/voice', $url);
            $this->assertIsString($options['body']);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->assertIsArray($body);
            $this->assertSame('foo', $body['text']);

            return new JsonMockResponse([
                'audioContent' => base64_encode('audio'),
            ]);
        }, 'https://api.inworld.ai/');

        $client = new InworldClient($httpClient);

        $client->request(new Inworld('inworld-tts-2', [Capability::TEXT_TO_SPEECH]), 'foo', [
            'voice' => 'Dennis',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechWithCustomAudioConfig()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertIsString($options['body']);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->assertIsArray($body);
            $this->assertSame([
                'audioEncoding' => 'LINEAR16',
                'sampleRateHertz' => 24000,
                'speakingRate' => 1.2,
            ], $body['audioConfig']);
            $this->assertSame('en-US', $body['language']);

            return new JsonMockResponse([
                'audioContent' => base64_encode('audio'),
            ]);
        }, 'https://api.inworld.ai/');

        $client = new InworldClient($httpClient);

        $client->request(new Inworld('inworld-tts-2', [Capability::TEXT_TO_SPEECH]), 'foo', [
            'voice' => 'Dennis',
            'audioConfig' => [
                'audioEncoding' => 'LINEAR16',
                'sampleRateHertz' => 24000,
                'speakingRate' => 1.2,
            ],
            'language' => 'en-US',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechAsStream()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.inworld.ai/tts/v1/voice:stream', $url);
            $this->assertIsString($options['body']);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->assertIsArray($body);
            $this->assertArrayNotHasKey('stream', $body);

            return new MockResponse(json_encode(['result' => ['audioContent' => base64_encode('chunk')]])."\n");
        }, 'https://api.inworld.ai/');

        $client = new InworldClient($httpClient);

        $result = $client->request(new Inworld('inworld-tts-2', [Capability::TEXT_TO_SPEECH]), 'foo', [
            'voice' => 'Dennis',
            'stream' => true,
        ]);

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechWithVoiceFromModelOptions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertIsString($options['body']);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->assertIsArray($body);
            $this->assertSame('Dennis', $body['voiceId']);

            return new JsonMockResponse([
                'audioContent' => base64_encode('audio'),
            ]);
        }, 'https://api.inworld.ai/');

        $client = new InworldClient($httpClient);

        $client->request(new Inworld('inworld-tts-2', [Capability::TEXT_TO_SPEECH], [
            'voice' => 'Dennis',
        ]), 'foo');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCannotPerformSpeechToTextWithInvalidPayload()
    {
        $client = new InworldClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array for speech-to-text request, got "string".');
        $this->expectExceptionCode(0);
        $client->request(new Inworld('inworld/inworld-stt-1', [Capability::SPEECH_TO_TEXT]), 'foo');
    }

    public function testClientCannotPerformSpeechToTextWithoutInputAudio()
    {
        $client = new InworldClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input audio is required for speech-to-text request.');
        $this->expectExceptionCode(0);
        $client->request(new Inworld('inworld/inworld-stt-1', [Capability::SPEECH_TO_TEXT]), [
            'foo' => 'bar',
        ]);
    }

    public function testClientCannotPerformSpeechToTextWithEmptyAudioData()
    {
        $client = new InworldClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "input_audio" entry must contain a non-empty base64 "data" key.');
        $this->expectExceptionCode(0);
        $client->request(new Inworld('inworld/inworld-stt-1', [Capability::SPEECH_TO_TEXT]), [
            'input_audio' => [
                'data' => '',
            ],
        ]);
    }

    public function testClientCanPerformSpeechToText()
    {
        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'));

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.inworld.ai/stt/v1/transcribe', $url);
            $this->assertIsString($options['body']);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->assertIsArray($body);
            $this->assertIsArray($body['transcribeConfig']);
            $this->assertIsArray($body['audioData']);
            $this->assertSame('inworld/inworld-stt-1', $body['transcribeConfig']['modelId']);
            $this->assertSame('AUTO_DETECT', $body['transcribeConfig']['audioEncoding']);
            $this->assertIsString($body['audioData']['content']);
            $this->assertNotSame('', $body['audioData']['content']);
            $this->assertNotFalse(base64_decode($body['audioData']['content'], true));

            return new JsonMockResponse([
                'transcription' => ['transcript' => 'Hello world'],
            ]);
        }, 'https://api.inworld.ai/');

        $client = new InworldClient($httpClient);

        $client->request(new Inworld('inworld/inworld-stt-1', [Capability::SPEECH_TO_TEXT]), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testSpeechToTextOptionsAreNestedUnderTranscribeConfig()
    {
        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'));

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertIsString($options['body']);
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $this->assertIsArray($body);
            $this->assertIsArray($body['transcribeConfig']);
            $this->assertSame('en-US', $body['transcribeConfig']['language']);
            $this->assertSame(16000, $body['transcribeConfig']['sampleRateHertz']);
            $this->assertTrue($body['transcribeConfig']['includeWordTimestamps']);
            $this->assertSame('LINEAR16', $body['transcribeConfig']['audioEncoding']);
            $this->assertArrayNotHasKey('language', $body);
            $this->assertArrayNotHasKey('sampleRateHertz', $body);
            $this->assertArrayNotHasKey('includeWordTimestamps', $body);
            $this->assertArrayNotHasKey('audioEncoding', $body);

            return new JsonMockResponse([
                'transcription' => ['transcript' => 'Hello'],
            ]);
        }, 'https://api.inworld.ai/');

        $client = new InworldClient($httpClient);

        $client->request(new Inworld('inworld/inworld-stt-1', [Capability::SPEECH_TO_TEXT]), $payload, [
            'language' => 'en-US',
            'sampleRateHertz' => 16000,
            'includeWordTimestamps' => true,
            'audioEncoding' => 'LINEAR16',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
