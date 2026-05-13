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
use Symfony\AI\Platform\Bridge\Bifrost\Audio\Task;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionModel;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TranscriptionModelClientTest extends TestCase
{
    public function testItSupportsTranscriptionModelOnly()
    {
        $client = new TranscriptionModelClient(new MockHttpClient());

        $this->assertTrue($client->supports(new TranscriptionModel('openai/whisper-1')));
        $this->assertFalse($client->supports(new Model('test-model')));
    }

    public function testItHitsTranscriptionEndpointByDefault()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('http://localhost:8080/v1/audio/transcriptions', $url);

                return new MockResponse('{"text":"Hello world!"}', ['http_code' => 200]);
            },
        ]);

        $client = new TranscriptionModelClient(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $client->request(new TranscriptionModel('openai/whisper-1'), ['file' => 'fake-audio']);

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItHitsTranslationEndpointWhenRequested()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url): MockResponse {
                self::assertSame('http://localhost:8080/v1/audio/translations', $url);

                return new MockResponse('{"text":"Hello world!"}', ['http_code' => 200]);
            },
        ]);

        $client = new TranscriptionModelClient(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $client->request(
            new TranscriptionModel('openai/whisper-1'),
            ['file' => 'fake-audio'],
            ['task' => Task::TRANSLATION],
        );

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItSetsVerboseJsonResponseFormatWhenVerbose()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url, array $options): JsonMockResponse {
                $bodyOption = $options['body'] ?? null;
                self::assertInstanceOf(\Closure::class, $bodyOption);
                $body = $bodyOption();
                self::assertIsString($body);
                self::assertStringContainsString('verbose_json', $body);

                return new JsonMockResponse(['text' => 'Hello world!']);
            },
        ]);

        $client = new TranscriptionModelClient(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $client->request(
            new TranscriptionModel('openai/whisper-1'),
            ['file' => 'fake-audio'],
            ['verbose' => true],
        );

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItForwardsBearerTokenWhenProvided()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url, array $options): MockResponse {
                $headers = $options['normalized_headers'] ?? [];
                self::assertIsArray($headers);
                self::assertArrayHasKey('authorization', $headers);
                self::assertIsArray($headers['authorization']);
                self::assertSame('Authorization: Bearer sk-bf-test', $headers['authorization'][0]);

                return new MockResponse('{"text":"Hello"}', ['http_code' => 200]);
            },
        ]);

        $client = new TranscriptionModelClient(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080', [
            'auth_bearer' => 'sk-bf-test',
        ]));
        $client->request(new TranscriptionModel('openai/whisper-1'), ['file' => 'fake-audio']);

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItFailsWhenPayloadIsString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array');

        $client = new TranscriptionModelClient(new MockHttpClient());
        $client->request(new TranscriptionModel('openai/whisper-1'), 'fake-audio');
    }
}
