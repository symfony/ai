<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Tests\OpenAi;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\WhisperModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WhisperModelClientTest extends TestCase
{
    #[TestWith(['test.azure.com', 'https://test.azure.com/openai/deployments/whspr/audio/transcriptions?api-version=2023-12'])]
    #[TestWith(['https://test.azure.com', 'https://test.azure.com/openai/deployments/whspr/audio/transcriptions?api-version=2023-12'])]
    #[TestWith(['https://test.azure.com/', 'https://test.azure.com/openai/deployments/whspr/audio/transcriptions?api-version=2023-12'])]
    #[TestWith(['http://localhost:8080', 'http://localhost:8080/openai/deployments/whspr/audio/transcriptions?api-version=2023-12'])]
    public function testItNormalizesTheBaseUrl(string $baseUrl, string $expectedUrl)
    {
        $httpClient = new MockHttpClient([function (string $method, string $url) use ($expectedUrl): MockResponse {
            $this->assertSame($expectedUrl, $url);

            return new MockResponse('{"text": "Hello World"}');
        }]);

        $client = new WhisperModelClient($httpClient, $baseUrl, 'whspr', '2023-12', 'test-key');
        $client->request(new Whisper('whisper-1'), ['file' => 'audio-data']);
    }

    public function testItThrowsExceptionWhenDeploymentIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The deployment must not be empty.');

        new WhisperModelClient(new MockHttpClient(), 'test.azure.com', '', 'api-version', 'api-key');
    }

    public function testItThrowsExceptionWhenApiVersionIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API version must not be empty.');

        new WhisperModelClient(new MockHttpClient(), 'test.azure.com', 'deployment', '', 'api-key');
    }

    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new WhisperModelClient(new MockHttpClient(), 'test.azure.com', 'deployment', 'api-version', '');
    }

    public function testItAcceptsValidParameters()
    {
        $client = new WhisperModelClient(new MockHttpClient(), 'test.azure.com', 'valid-deployment', '2023-12-01', 'valid-api-key');

        $this->assertInstanceOf(WhisperModelClient::class, $client);
    }

    public function testItSupportsWhisperModel()
    {
        $client = new WhisperModelClient(
            new MockHttpClient(),
            'test.openai.azure.com',
            'whisper-deployment',
            '2023-12-01-preview',
            'test-key'
        );
        $model = new Whisper('whisper-1');

        $this->assertTrue($client->supports($model));
    }

    public function testItUsesTranscriptionEndpointByDefault()
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://test.azure.com/openai/deployments/whspr/audio/transcriptions?api-version=2023-12', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new WhisperModelClient($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $client->request(new Whisper('whisper-1'), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTranscriptionEndpointWhenTaskIsSpecified()
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://test.azure.com/openai/deployments/whspr/audio/transcriptions?api-version=2023-12', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new WhisperModelClient($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $client->request(new Whisper('whisper-1'), ['file' => 'audio-data'], ['task' => Task::TRANSCRIPTION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTranslationEndpointWhenTaskIsSpecified()
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://test.azure.com/openai/deployments/whspr/audio/translations?api-version=2023-12', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new WhisperModelClient($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $client->request(new Whisper('whisper-1'), ['file' => 'audio-data'], ['task' => Task::TRANSLATION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testHttpOptionsAreSentWithTheRequest()
    {
        $httpClient = new MockHttpClient([
            static function (string $method, string $url, array $options): MockResponse {
                self::assertSame(30.0, $options['max_duration']);
                $body = '';
                while ('' !== ($chunk = $options['body'](8192))) {
                    $body .= $chunk;
                }
                self::assertStringContainsString("name=\"file\"\r\n\r\naudio-data", $body);
                self::assertStringNotContainsString('overridden', $body);
                self::assertStringNotContainsString('http_options', $body);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new WhisperModelClient($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $client->request(new Whisper('whisper-1'), ['file' => 'audio-data'], ['http_options' => ['max_duration' => 30, 'body' => 'overridden']]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
