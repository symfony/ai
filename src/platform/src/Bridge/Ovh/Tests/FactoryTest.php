<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\Evaluation;
use Symfony\AI\Platform\Bridge\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\Ovh\Factory;
use Symfony\AI\Platform\Bridge\Ovh\ModelCatalog;
use Symfony\AI\Platform\Bridge\Ovh\TextToSpeech;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\RealtimeSessionResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class FactoryTest extends TestCase
{
    public function testItCreatesPlatformWithDefaultSettings()
    {
        $platform = Factory::createPlatform('ovh-test-api-key');

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithCustomHttpClient()
    {
        $httpClient = new MockHttpClient();
        $platform = Factory::createPlatform('ovh-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $platform = Factory::createPlatform('ovh-test-api-key', $httpClient);

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItSendsChatCompletionsToTheCompletionsEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/chat/completions', $url);

            return new MockResponse('{"choices": [{"message": {"role": "assistant", "content": "Hello"}, "finish_reason": "stop"}]}');
        });

        $result = Factory::createPlatform('ovh-test-api-key', $httpClient)->invoke('gpt-oss-120b', new MessageBag(Message::ofUser('Hi')));

        $this->assertSame('Hello', $result->asText());
    }

    public function testItSendsTranscriptionsToTheTranscriptionsEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/audio/transcriptions', $url);

            return new MockResponse('{"text": "Hello world"}');
        });

        $result = Factory::createPlatform('ovh-test-api-key', $httpClient)->invoke('whisper-large-v3', Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'));

        $this->assertSame('Hello world', $result->asText());
    }

    public function testItSendsTextToSpeechToTheHostOfTheModel()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://nvr-tts-fr-fr.endpoints.kepler.ai.cloud.ovh.net/api/v1/tts/text_to_audio', $url);

            return new MockResponse('audio');
        });

        $result = Factory::createPlatform('ovh-test-api-key', $httpClient, new ModelCatalog([
            'nvr-tts-fr-fr' => ['class' => TextToSpeech::class, 'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO]],
        ]))->invoke('nvr-tts-fr-fr', 'Bonjour', ['voice' => 'French-FR.Female-1']);

        $this->assertSame('audio', $result->asBinary());
    }

    public function testItSendsImageGenerationsToTheImagesEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/images/generations', $url);

            return new MockResponse(json_encode(['created' => 1, 'data' => [['b64_json' => base64_encode('image-bytes')]]]));
        });

        $result = Factory::createPlatform('ovh-test-api-key', $httpClient)->invoke('stable-diffusion-xl-base-v10', 'A cat in a spacesuit');

        $this->assertSame('image-bytes', $result->asBinary());
    }

    public function testItSendsEvaluationsToTheSystemOneEndpoint()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/api/v1/systemone', $url);

            return new MockResponse(json_encode(['model' => 'clef-flash', 'answers' => ['is_bug' => ['type' => 'noul', 'noul' => 0.9]], 'usage' => ['input_tokens' => 10]]));
        });

        $result = Factory::createPlatform('ovh-test-api-key', $httpClient)->invoke('clef-flash', new Evaluation('The app crashes.', [
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior', 'A question'),
        ]));

        $answers = $result->asObject();
        $this->assertInstanceOf(Answers::class, $answers);
        $this->assertSame(0.9, $answers->getNoul('is_bug')->getProbability());
    }

    public function testItOpensLiveTranscriptionSessions()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/gladia/v2/live', $url);

            return new MockResponse('{"id": "45463597", "created_at": "2026-11-25T09:04:17.210Z", "url": "wss://example.com/gladia/v2/live?token=4a39145c"}', ['http_code' => 201]);
        });

        $result = Factory::createPlatform('ovh-test-api-key', $httpClient)->invoke('solaria-3', ['sample_rate' => 16000])->getResult();

        $this->assertInstanceOf(RealtimeSessionResult::class, $result);
        $this->assertSame('wss://example.com/gladia/v2/live?token=4a39145c', $result->getClientSecret());
        $this->assertSame('solaria-3', $result->getModel());
    }
}
