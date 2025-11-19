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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ElevenLabsClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new ElevenLabsClient(
            new MockHttpClient(),
            'my-api-key',
        );

        $this->assertTrue($client->supports(new ElevenLabs('eleven_multilingual_v2')));
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
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be an array, received "string".');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs('eleven_multilingual_v2'), 'foo');
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
            'my-api-key',
        );

        $payload = $normalizer->normalize(Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3'));

        $client->request(new ElevenLabs('scribe_v1', [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformSpeechToTextRequestWithExperimentalModel()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'text' => 'foo',
            ]),
        ]);
        $normalizer = new AudioNormalizer();

        $client = new ElevenLabsClient(
            $httpClient,
            'my-api-key',
        );

        $payload = $normalizer->normalize(Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3'));

        $client->request(new ElevenLabs('scribe_v1_experimental', [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCannotPerformTextToSpeechRequestWithoutValidPayload()
    {
        $mockHttpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'eleven_multilingual_v2',
                    'can_do_text_to_speech' => true,
                ],
            ]),
            new JsonMockResponse([]),
        ]);

        $client = new ElevenLabsClient(
            $mockHttpClient,
            'my-api-key',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key');
        $this->expectExceptionCode(0);
        $client->request(new ElevenLabs('eleven_multilingual_v2', options: [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]), []);
    }

    public function testClientCanPerformTextToSpeechRequest()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'eleven_multilingual_v2',
                    'can_do_text_to_speech' => true,
                ],
            ]),
            new MockResponse($payload->asBinary()),
        ]);

        $client = new ElevenLabsClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new ElevenLabs('eleven_multilingual_v2', options: [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]), [
            'text' => 'foo',
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestWhenVoiceKeyIsProvidedAsRequestOption()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'eleven_multilingual_v2',
                    'can_do_text_to_speech' => true,
                ],
            ]),
            new MockResponse($payload->asBinary()),
        ]);

        $client = new ElevenLabsClient(
            $httpClient,
            'my-api-key',
        );

        $client->request(new ElevenLabs('eleven_multilingual_v2'), [
            'text' => 'foo',
        ], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestAsStream()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'eleven_multilingual_v2',
                    'can_do_text_to_speech' => true,
                ],
            ]),
            new MockResponse($payload->asBinary()),
        ]);

        $client = new ElevenLabsClient(
            $httpClient,
            'my-api-key',
        );

        $result = $client->request(new ElevenLabs('eleven_multilingual_v2', options: [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
            'stream' => true,
        ]), [
            'text' => 'foo',
        ]);

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformTextToSpeechRequestAsStreamVoiceKeyIsProvidedAsRequestOption()
    {
        $payload = Audio::fromFile(\dirname(__DIR__, 5).'/fixtures/audio.mp3');

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                [
                    'model_id' => 'eleven_multilingual_v2',
                    'can_do_text_to_speech' => true,
                ],
            ]),
            new MockResponse($payload->asBinary()),
        ]);

        $client = new ElevenLabsClient(
            $httpClient,
            'my-api-key',
        );

        $result = $client->request(new ElevenLabs('eleven_multilingual_v2'), [
            'text' => 'foo',
        ], [
            'voice' => 'Dslrhjl3ZpzrctukrQSN',
            'stream' => true,
        ]);

        $this->assertInstanceOf(RawHttpResult::class, $result);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }
}
