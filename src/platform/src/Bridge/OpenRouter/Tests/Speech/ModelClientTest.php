<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Speech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\OpenRouter\Speech\ModelClient;
use Symfony\AI\Platform\Bridge\OpenRouter\SpeechModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsSpeechModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new SpeechModel('openai/gpt-4o-mini-tts-2025-12-15')));
    }

    public function testItDoesNotSupportNonSpeechModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new CompletionsModel('openrouter/auto')));
    }

    public function testItSendsExpectedRequestForStringPayload()
    {
        $httpClient = new MockHttpClient(function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://openrouter.ai/api/v1/audio/speech', $url);
            $this->assertContains('Authorization: Bearer test-key', $options['headers']);
            $this->assertContains('Content-Type: application/json', $options['headers']);

            $body = json_decode($options['body'], true);
            $this->assertSame('openai/gpt-4o-mini-tts-2025-12-15', $body['model']);
            $this->assertSame('Hello world', $body['input']);
            $this->assertSame('alloy', $body['voice']);
            $this->assertArrayNotHasKey('response_format', $body);
            $this->assertArrayNotHasKey('speed', $body);
            $this->assertArrayNotHasKey('provider', $body);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new SpeechModel('openai/gpt-4o-mini-tts-2025-12-15'), 'Hello world', [
            'voice' => 'alloy',
        ]);
    }

    public function testItSendsExpectedRequestForArrayPayload()
    {
        $httpClient = new MockHttpClient(function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('Hello world', $body['input']);
            $this->assertSame('alloy', $body['voice']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new SpeechModel('openai/gpt-4o-mini-tts-2025-12-15'), ['text' => 'Hello world'], [
            'voice' => 'alloy',
        ]);
    }

    public function testItSendsOptionalParameters()
    {
        $httpClient = new MockHttpClient(function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('mp3', $body['response_format']);
            $this->assertSame(1.25, $body['speed']);
            $this->assertSame(['options' => ['order' => ['openai']]], $body['provider']);

            return new MockResponse();
        });

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new SpeechModel('openai/gpt-4o-mini-tts-2025-12-15'), 'Hello world', [
            'voice' => 'alloy',
            'response_format' => 'mp3',
            'speed' => 1.25,
            'provider' => ['options' => ['order' => ['openai']]],
        ]);
    }

    public function testItThrowsExceptionForMissingVoiceOption()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "voice" option is required for Speech requests.');

        $client->request(new SpeechModel('openai/gpt-4o-mini-tts-2025-12-15'), 'Hello world');
    }

    public function testItThrowsExceptionForArrayPayloadWithoutTextKey()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be a string or an array with a "text" key.');

        $client->request(new SpeechModel('openai/gpt-4o-mini-tts-2025-12-15'), ['invalid' => 'payload'], [
            'voice' => 'alloy',
        ]);
    }
}
