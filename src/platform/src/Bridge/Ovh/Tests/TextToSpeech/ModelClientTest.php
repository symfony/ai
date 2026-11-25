<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\TextToSpeech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\TextToSpeech;
use Symfony\AI\Platform\Bridge\Ovh\TextToSpeech\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsTextToSpeechModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new TextToSpeech('nvr-tts-en-us')));
        $this->assertFalse($client->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItSendsExpectedRequestToTheHostOfTheModel()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://nvr-tts-en-us.endpoints.kepler.ai.cloud.ovh.net/api/v1/tts/text_to_audio', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertContains('Content-Type: application/json', $options['headers']);

            $this->assertSame([
                'text' => 'Hello world',
                'language_code' => 'en-US',
                'encoding' => 1,
                'sample_rate_hz' => 16000,
                'voice_name' => 'English-US.Male-1',
            ], json_decode($options['body'], true));

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new TextToSpeech('nvr-tts-en-us'), 'Hello world', ['voice' => 'English-US.Male-1']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItGuessesTheLanguageFromTheModelName()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('https://nvr-tts-de-de.endpoints.kepler.ai.cloud.ovh.net/api/v1/tts/text_to_audio', $url);
            $this->assertSame('de-DE', json_decode($options['body'], true)['language_code']);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key'))->request(new TextToSpeech('nvr-tts-de-de'), 'Hallo', ['voice' => 'German-DE.Male-1']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItAcceptsAnArrayPayloadAndOptionsOverridingTheDefaults()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('Hello world', $body['text']);
            $this->assertSame('en-GB', $body['language_code']);
            $this->assertSame(4, $body['encoding']);
            $this->assertSame(22050, $body['sample_rate_hz']);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key'))->request(new TextToSpeech('nvr-tts-en-us'), ['text' => 'Hello world'], [
            'voice' => 'English-US.Male-1',
            'language_code' => 'en-GB',
            'encoding' => 4,
            'sample_rate_hz' => 22050,
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItHonorsCustomDomain()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://nvr-tts-es-es.example.com/api/v1/tts/text_to_audio', $url);

            return new MockResponse();
        });

        (new ModelClient($httpClient, 'test-key', 'example.com/'))->request(new TextToSpeech('nvr-tts-es-es'), 'Hola', ['voice' => 'Spanish-ES-Female-1']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRequiresTheVoiceOption()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "voice" option is required for text-to-speech requests.');

        $client->request(new TextToSpeech('nvr-tts-en-us'), 'Hello world');
    }

    public function testItRejectsAnArrayPayloadWithoutText()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be a string or an array with a "text" key.');

        $client->request(new TextToSpeech('nvr-tts-en-us'), ['foo' => 'bar'], ['voice' => 'English-US.Male-1']);
    }

    public function testItRequiresTheLanguageWhenTheModelNameDoesNotTellIt()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The language of the model "custom-voice" is unknown, pass it with the "language_code" option.');

        $client->request(new TextToSpeech('custom-voice'), 'Hello world', ['voice' => 'English-US.Male-1']);
    }
}
