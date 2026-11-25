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
use Symfony\AI\Platform\Bridge\Ovh\Factory;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
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
}
