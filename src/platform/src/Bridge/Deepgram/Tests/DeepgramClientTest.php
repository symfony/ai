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
use Symfony\AI\Platform\Bridge\Deepgram\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Bridge\Deepgram\DeepgramClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeepgramClientTest extends TestCase
{
    public function testSupportsOnlyDeepgramModel()
    {
        $client = new DeepgramClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT])));
        $this->assertFalse($client->supports(new Model('gpt-4')));
    }

    public function testClientCannotPerformActionUsingUnsupportedClient()
    {
        $client = new DeepgramClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" is not supported, please check the Deepgram API.');
        $client->request(new Deepgram('foo', [Capability::INPUT_IMAGE]), []);
    }

    public function testTextToSpeechSendsModelAsQueryParamAndTextInBody()
    {
        $capturedMethod = '';
        $capturedUrl = '';
        $capturedBody = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedBody) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $body = $options['body'] ?? '';
            $capturedBody = \is_string($body) ? $body : '';

            return new MockResponse('binary-mp3-payload', ['response_headers' => ['content-type' => 'audio/mpeg']]);
        }, 'https://api.deepgram.com/v1/');

        $client = new DeepgramClient($httpClient);
        $client->request(new Deepgram('aura-2-thalia-en', [Capability::TEXT_TO_SPEECH]), ['text' => 'Hello']);

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/v1/speak', parse_url($capturedUrl, \PHP_URL_PATH));
        $this->assertStringContainsString('model=aura-2-thalia-en', $capturedUrl);
        $this->assertSame('{"text":"Hello"}', $capturedBody);
    }

    public function testTextToSpeechForwardsExtraOptionsAsQueryParams()
    {
        $capturedUrl = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new MockResponse('audio');
        }, 'https://api.deepgram.com/v1/');

        $client = new DeepgramClient($httpClient);
        $client->request(
            new Deepgram('aura-2-thalia-en', [Capability::TEXT_TO_SPEECH]),
            ['text' => 'Hi'],
            ['encoding' => 'linear16', 'sample_rate' => 24000]
        );

        $this->assertStringContainsString('encoding=linear16', $capturedUrl);
        $this->assertStringContainsString('sample_rate=24000', $capturedUrl);
    }

    public function testStreamOptionIsNotForwardedToTheApi()
    {
        $capturedUrl = '';
        $capturedBuffer = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBuffer) {
            $capturedUrl = $url;
            $capturedBuffer = $options['buffer'] ?? null;

            return new MockResponse('audio');
        }, 'https://api.deepgram.com/v1/');

        $client = new DeepgramClient($httpClient);
        $client->request(
            new Deepgram('aura-2-thalia-en', [Capability::TEXT_TO_SPEECH]),
            ['text' => 'Hi'],
            ['stream' => true, 'encoding' => 'linear16'],
        );

        $this->assertStringContainsString('encoding=linear16', $capturedUrl);
        $this->assertStringNotContainsString('stream', $capturedUrl);
        $this->assertFalse($capturedBuffer);
    }

    public function testSpeechToTextStreamsAudioFromPath()
    {
        $capturedMethod = '';
        $capturedUrl = '';
        $capturedBody = null;
        $contentTypeHeader = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedBody, &$contentTypeHeader) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedBody = $options['body'] ?? null;
            $headers = $options['headers'] ?? [];
            if (\is_array($headers)) {
                foreach ($headers as $header) {
                    if (\is_string($header) && str_starts_with(strtolower($header), 'content-type:')) {
                        $contentTypeHeader = $header;
                        break;
                    }
                }
            }

            return new MockResponse('{"results":{"channels":[{"alternatives":[{"transcript":"hello"}]}]}}');
        }, 'https://api.deepgram.com/v1/');

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(__DIR__.'/Fixtures/audio.mp3'));

        $client = new DeepgramClient($httpClient);
        $client->request(new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]), $payload);

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/v1/listen', parse_url($capturedUrl, \PHP_URL_PATH));
        $this->assertIsResource($capturedBody);
        rewind($capturedBody);
        $this->assertSame((string) file_get_contents(__DIR__.'/Fixtures/audio.mp3'), stream_get_contents($capturedBody));
        $this->assertStringContainsString('audio/mpeg', $contentTypeHeader);
    }

    public function testSpeechToTextFallsBackToDecodedBase64WithoutPath()
    {
        $capturedBody = null;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedBody) {
            $capturedBody = $options['body'] ?? null;

            return new MockResponse('{"results":{"channels":[{"alternatives":[{"transcript":"hello"}]}]}}');
        }, 'https://api.deepgram.com/v1/');

        $client = new DeepgramClient($httpClient);
        $client->request(
            new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]),
            [
                'type' => 'input_audio',
                'input_audio' => ['data' => base64_encode('raw-audio-bytes'), 'format' => 'mp3'],
            ],
        );

        $this->assertSame('raw-audio-bytes', $capturedBody);
    }

    public function testSpeechToTextWithUrlInput()
    {
        $capturedMethod = '';
        $capturedUrl = '';
        $capturedBody = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedBody) {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $body = $options['body'] ?? '';
            $capturedBody = \is_string($body) ? $body : '';

            return new MockResponse('{"results":{"channels":[{"alternatives":[{"transcript":"hi"}]}]}}');
        }, 'https://api.deepgram.com/v1/');

        $client = new DeepgramClient($httpClient);
        $client->request(
            new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]),
            [
                'type' => 'input_audio',
                'input_audio' => ['url' => 'https://example.com/audio.mp3'],
            ],
        );

        $this->assertSame('POST', $capturedMethod);
        $this->assertSame('/v1/listen', parse_url($capturedUrl, \PHP_URL_PATH));
        $this->assertSame('{"url":"https://example.com/audio.mp3"}', $capturedBody);
    }

    public function testSpeechToTextRejectsDataUrlScheme()
    {
        $client = new DeepgramClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text URL must use "http" or "https" scheme, "data" given.');

        $client->request(
            new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]),
            [
                'type' => 'input_audio',
                'input_audio' => ['url' => 'data:mp3;base64,AAAA'],
            ],
        );
    }

    public function testSpeechToTextRejectsFileScheme()
    {
        $client = new DeepgramClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The speech-to-text URL must use "http" or "https" scheme, "file" given.');

        $client->request(
            new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]),
            [
                'type' => 'input_audio',
                'input_audio' => ['url' => 'file:///etc/passwd'],
            ],
        );
    }

    public function testSpeechToTextForwardsOptionsAsQueryParams()
    {
        $capturedUrl = '';
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new MockResponse('{}');
        }, 'https://api.deepgram.com/v1/');

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(__DIR__.'/Fixtures/audio.mp3'));

        $client = new DeepgramClient($httpClient);
        $client->request(
            new Deepgram('nova-3', [Capability::SPEECH_TO_TEXT]),
            $payload,
            ['smart_format' => 'true', 'language' => 'en'],
        );

        $this->assertStringContainsString('smart_format=true', $capturedUrl);
        $this->assertStringContainsString('language=en', $capturedUrl);
        $this->assertStringContainsString('model=nova-3', $capturedUrl);
    }
}
