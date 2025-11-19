<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Cartesia;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cartesia\Cartesia;
use Symfony\AI\Platform\Bridge\Cartesia\CartesiaClient;
use Symfony\AI\Platform\Bridge\Cartesia\Contract\AudioNormalizer;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CartesiaClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new CartesiaClient(
            new MockHttpClient(),
            'my-api-key',
            'foo',
        );

        $this->assertTrue($client->supports(new Cartesia('sonic-3')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientCannotPerformOnInvalidModel()
    {
        $client = new CartesiaClient(
            new MockHttpClient(),
            'my-api-key',
            'foo',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The model "foo" is not supported.');
        $this->expectExceptionCode(0);
        $client->request(new Cartesia('foo', []), [
            'text' => 'bar',
        ]);
    }

    public function testClientCannotPerformTextToSpeechOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'error' => '',
            ], [
                'http_code' => 400,
            ]),
        ]);

        $client = new CartesiaClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cartesia.ai/tts/bytes".');
        $this->expectExceptionCode(400);
        $client->request(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'bar',
        ], [
            'voice' => '6ccbfb76-1fc6-48f7-b71d-91ac6298247b', // Tessa (https://play.cartesia.ai/voices/6ccbfb76-1fc6-48f7-b71d-91ac6298247b)
            'output_format' => [
                'container' => 'mp3',
                'sample_rate' => 48000,
                'bit_rate' => 192000,
            ],
        ]);
    }

    public function testClientCanPerformTextToSpeech()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient([
            new MockResponse($payload->asBinary()),
        ]);

        $client = new CartesiaClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $client->request(new Cartesia('sonic-3', [Capability::TEXT_TO_SPEECH]), [
            'text' => 'bar',
        ], [
            'voice' => '6ccbfb76-1fc6-48f7-b71d-91ac6298247b', // Tessa (https://play.cartesia.ai/voices/6ccbfb76-1fc6-48f7-b71d-91ac6298247b)
            'output_format' => [
                'container' => 'mp3',
                'sample_rate' => 48000,
                'bit_rate' => 192000,
            ],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCannotPerformSpeechToTextOnInvalidResponse()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 2).'/fixtures/audio.mp3');

        $normalizer = new AudioNormalizer();

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'error' => '',
            ], [
                'http_code' => 400,
            ]),
        ]);

        $client = new CartesiaClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "https://api.cartesia.ai/stt".');
        $this->expectExceptionCode(400);
        $client->request(new Cartesia('ink-whisper', [Capability::SPEECH_TO_TEXT]), $normalizer->normalize($payload));
    }

    public function testClientCanPerformSpeechToText()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 2).'/fixtures/audio.mp3');

        $normalizer = new AudioNormalizer();

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'text' => 'Hello there',
            ]),
        ]);

        $client = new CartesiaClient(
            $httpClient,
            'my-api-key',
            'foo',
        );

        $client->request(new Cartesia('ink-whisper', [Capability::SPEECH_TO_TEXT]), $normalizer->normalize($payload));

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
