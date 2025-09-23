<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Whisper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\ModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ModelClient::class)]
#[Small]
final class ModelClientTest extends TestCase
{
    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new ModelClient(new MockHttpClient(), '');
    }

    #[TestWith(['api-key-without-prefix'])]
    #[TestWith(['pk-api-key'])]
    #[TestWith(['SK-api-key'])]
    #[TestWith(['skapikey'])]
    #[TestWith(['sk api-key'])]
    #[TestWith(['sk'])]
    public function testItThrowsExceptionWhenApiKeyDoesNotStartWithSk(string $invalidApiKey)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must start with "sk-".');

        new ModelClient(new MockHttpClient(), $invalidApiKey);
    }

    public function testItAcceptsValidApiKey()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItSupportsWhisperModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');
        $this->assertTrue($client->supports(new Whisper(Whisper::WHISPER_1)));
    }

    public function testItUsesTranscriptionEndpointByDefault()
    {
        $httpClient = new MockHttpClient([
            function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://api.openai.com/v1/audio/transcriptions', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new ModelClient($httpClient, 'sk-test-key');
        $client->request(new Whisper(Whisper::WHISPER_1), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTranscriptionEndpointWhenTaskIsSpecified()
    {
        $httpClient = new MockHttpClient([
            function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://api.openai.com/v1/audio/transcriptions', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new ModelClient($httpClient, 'sk-test-key');
        $client->request(new Whisper(Whisper::WHISPER_1), ['file' => 'audio-data'], ['task' => Task::TRANSCRIPTION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTranslationEndpointWhenTaskIsSpecified()
    {
        $httpClient = new MockHttpClient([
            function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://api.openai.com/v1/audio/translations', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new ModelClient($httpClient, 'sk-test-key');
        $client->request(new Whisper(Whisper::WHISPER_1), ['file' => 'audio-data'], ['task' => Task::TRANSLATION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/audio/transcriptions'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/audio/transcriptions'])]
    #[TestWith([null, 'https://api.openai.com/v1/audio/transcriptions'])]
    public function testItUsesCorrectRegionUrlForTranscription(?string $region, string $expectedUrl)
    {
        $httpClient = new MockHttpClient([
            function ($method, $url) use ($expectedUrl): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame($expectedUrl, $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new ModelClient($httpClient, 'sk-test-key', $region);
        $client->request(new Whisper(Whisper::WHISPER_1), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/audio/translations'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/audio/translations'])]
    #[TestWith([null, 'https://api.openai.com/v1/audio/translations'])]
    public function testItUsesCorrectRegionUrlForTranslation(?string $region, string $expectedUrl)
    {
        $httpClient = new MockHttpClient([
            function ($method, $url) use ($expectedUrl): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame($expectedUrl, $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new ModelClient($httpClient, 'sk-test-key', $region);
        $client->request(new Whisper(Whisper::WHISPER_1), ['file' => 'audio-data'], ['task' => Task::TRANSLATION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
