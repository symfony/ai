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
use Symfony\AI\Platform\Bridge\ElevenLabs\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ElevenLabsClient::class)]
#[UsesClass(ElevenLabs::class)]
#[UsesClass(Model::class)]
#[UsesClass(Audio::class)]
#[UsesClass(AudioNormalizer::class)]
#[UsesClass(RawHttpResult::class)]
final class ElevenLabsClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'my-api-key',
            'https://api.elevenlabs.io/v1',
        );

        $this->assertTrue($client->supports(new ElevenLabs()));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testClientCannotPerformWithInvalidModel()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'bar',
                    'can_do_text_to_speech' => false,
                ],
            ]),
            new JsonMockResponse([]),
        ]);
        $normalizer = new AudioNormalizer();

        $client = new ElevenLabsClient(
            $mockHttpClient,
            'my-api-key',
            'https://api.elevenlabs.io/v1',
        );

        $payload = $normalizer->normalize(Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model information could not be retrieved from the ElevenLabs API. Your model might not be supported. Try to use another one.');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs('foo'), $payload);
    }

    public function testClientCannotPerformSpeechToTextRequestWithInvalidPayload()
    {
        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'my-api-key',
            'https://api.elevenlabs.io/v1',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be an array, received "string".');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs(ElevenLabs::ELEVEN_MULTILINGUAL_V2), 'foo');
    }

    public function testClientCanPerformSpeechToTextRequest()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'text' => 'foo',
            ]),
        ]);
        $normalizer = new AudioNormalizer();

        $client = new ElevenLabsClient(
            $httpClient,
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $payload = $normalizer->normalize(Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3'));

        $client->request(new ElevenLabs(ElevenLabs::SCRIBE_V1), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCannotPerformTextToSpeechRequestWithoutValidPayload()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => ElevenLabs::ELEVEN_MULTILINGUAL_V2,
                    'can_do_text_to_speech' => true,
                ],
            ]),
            new JsonMockResponse([]),
        ]);

        $client = new ElevenLabsClient(
            $mockHttpClient,
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs(options: [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]), []);
    }

    public function testClientCanPerformTextToSpeechRequest()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => ElevenLabs::ELEVEN_MULTILINGUAL_V2,
                    'can_do_text_to_speech' => true,
                ],
            ]),
            new MockResponse($payload->asBinary()),
        ]);

        $client = new ElevenLabsClient(
            $httpClient,
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $client->request(new ElevenLabs(options: [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]), [
            'text' => 'foo',
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestAsStream()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => ElevenLabs::ELEVEN_MULTILINGUAL_V2,
                    'can_do_text_to_speech' => true,
                ],
            ]),
            new MockResponse($payload->asBinary()),
        ]);

        $client = new ElevenLabsClient(
            $httpClient,
            'https://api.elevenlabs.io/v1',
            'my-api-key',
        );

        $result = $client->request(new ElevenLabs(options: [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
            'stream' => true,
        ]), [
            'text' => 'foo',
        ]);

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }
}
