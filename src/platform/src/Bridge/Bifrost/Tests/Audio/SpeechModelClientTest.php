<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Tests\Audio;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\SpeechModel;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\SpeechModelClient;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\Voice;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechModelClientTest extends TestCase
{
    public function testItSupportsSpeechModelOnly()
    {
        $client = new SpeechModelClient(new MockHttpClient());

        $this->assertTrue($client->supports(new SpeechModel('openai/tts-1')));
        $this->assertFalse($client->supports(new Model('test-model')));
    }

    public function testItSendsExpectedRequest()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url, array $options): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('http://localhost:8080/v1/audio/speech', $url);

                $headers = $options['normalized_headers'] ?? [];
                self::assertIsArray($headers);
                self::assertArrayHasKey('authorization', $headers);
                self::assertIsArray($headers['authorization']);
                self::assertSame('Authorization: Bearer sk-bf-test', $headers['authorization'][0]);

                $rawBody = $options['body'] ?? null;
                self::assertIsString($rawBody);
                $body = json_decode($rawBody, true);
                self::assertIsArray($body);
                self::assertSame('openai/tts-1', $body['model']);
                self::assertSame('Hello world!', $body['input']);
                self::assertSame(Voice::ALLOY, $body['voice']);

                return new MockResponse('binary-audio', ['http_code' => 200]);
            },
        ]);

        $client = new SpeechModelClient(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080', [
            'auth_bearer' => 'sk-bf-test',
        ]));
        $client->request(new SpeechModel('openai/tts-1'), 'Hello world!', ['voice' => Voice::ALLOY]);

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItAcceptsArrayPayloadWithTextKey()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url, array $options): MockResponse {
                $rawBody = $options['body'] ?? null;
                self::assertIsString($rawBody);
                $body = json_decode($rawBody, true);
                self::assertIsArray($body);
                self::assertSame('Hi there', $body['input']);

                return new MockResponse('binary-audio', ['http_code' => 200]);
            },
        ]);

        $client = new SpeechModelClient(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $client->request(new SpeechModel('openai/tts-1'), ['text' => 'Hi there'], ['voice' => Voice::NOVA]);

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItPostsToRelativePath()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertStringEndsWith('/v1/audio/speech', $url);

                return new MockResponse('', ['http_code' => 200]);
            },
        ]);

        $client = new SpeechModelClient($mock);
        $client->request(new SpeechModel('openai/tts-1'), 'Hi', ['voice' => Voice::CORAL]);

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItFailsWhenVoiceMissing()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "voice" option is required for text-to-speech requests.');

        $client = new SpeechModelClient(new MockHttpClient());
        $client->request(new SpeechModel('openai/tts-1'), 'Hello world!');
    }

    public function testItFailsWhenStreamingRequested()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text-to-speech results is not supported yet.');

        $client = new SpeechModelClient(new MockHttpClient());
        $client->request(new SpeechModel('openai/tts-1'), 'Hello world!', [
            'voice' => Voice::ALLOY,
            'stream' => true,
        ]);
    }

    public function testItFailsWhenPayloadShapeIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be a string or contain a string "text" key.');

        $client = new SpeechModelClient(new MockHttpClient());
        $client->request(new SpeechModel('openai/tts-1'), ['foo' => 'bar'], ['voice' => Voice::ALLOY]);
    }
}
