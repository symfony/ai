<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\ElevenLabs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsClient;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\AudioNormalizer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;

#[CoversClass(ElevenLabsClient::class)]
#[UsesClass(ElevenLabs::class)]
#[UsesClass(Model::class)]
#[UsesClass(Audio::class)]
#[UsesClass(AudioNormalizer::class)]
final class ElevenLabsClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $this->assertTrue($client->supports(new ElevenLabs()));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientCannotPerformSpeechToTextRequestWithoutModel()
    {
        $normalizer = new AudioNormalizer();

        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $payload = $normalizer->normalize(Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model option is required.');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs(ElevenLabs::SPEECH_TO_TEXT), $payload);
    }

    public function testClientCannotPerformSpeechToTextRequestWithInvalidPayload()
    {
        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be an array, received "string".');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs(ElevenLabs::SPEECH_TO_TEXT, options: [
            'model' => 'bar',
        ]), 'foo');
    }

    public function testClientCanPerformSpeechToTextRequest()
    {
        $httpClient = new MockHttpClient();
        $normalizer = new AudioNormalizer();

        $client = new ElevenLabsClient(
            $httpClient,
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $payload = $normalizer->normalize(Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3'));

        $client->request(new ElevenLabs(ElevenLabs::SPEECH_TO_TEXT, options: [
            'model' => 'bar',
        ]), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCannotPerformTextToSpeechRequestWithoutModel()
    {
        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model option is required.');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs(), []);
    }

    public function testClientCannotPerformTextToSpeechRequestWithInvalidPayload()
    {
        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be an array, received "string".');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs(options: [
            'model' => 'bar',
        ]), 'foo');
    }

    public function testClientCannotPerformTextToSpeechRequestWithoutValidPayload()
    {
        $normalizer = new AudioNormalizer();

        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs(options: [
            'model' => 'bar',
        ]), []);
    }

    public function testClientCanPerformTextToSpeechRequest()
    {
        $httpClient = new MockHttpClient();

        $client = new ElevenLabsClient(
            $httpClient,
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $client->request(new ElevenLabs(options: [
            'model' => 'bar',
        ]), [
            'text' => 'foo',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
