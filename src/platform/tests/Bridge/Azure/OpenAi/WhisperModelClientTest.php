<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Azure\OpenAi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\WhisperModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(WhisperModelClient::class)]
#[Small]
final class WhisperModelClientTest extends TestCase
{
    #[TestWith(['http://test.azure.com', 'The base URL must not contain the protocol.'])]
    #[TestWith(['https://test.azure.com', 'The base URL must not contain the protocol.'])]
    #[TestWith(['http://test.azure.com:8080', 'The base URL must not contain the protocol.'])]
    #[TestWith(['https://test.azure.com:443', 'The base URL must not contain the protocol.'])]
    public function testItThrowsExceptionWhenBaseUrlContainsProtocol(string $invalidUrl, string $expectedMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new WhisperModelClient(new MockHttpClient(), $invalidUrl, 'deployment', 'api-version', 'api-key');
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
        $model = new Whisper(Whisper::WHISPER_1);

        $this->assertTrue($client->supports($model));
    }

    public function testItUsesTranscriptionEndpointByDefault()
    {
        $httpClient = new MockHttpClient([
            function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://test.azure.com/openai/deployments/whspr/audio/transcriptions?api-version=2023-12', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new WhisperModelClient($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $client->request(new Whisper(Whisper::WHISPER_1), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTranscriptionEndpointWhenTaskIsSpecified()
    {
        $httpClient = new MockHttpClient([
            function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://test.azure.com/openai/deployments/whspr/audio/transcriptions?api-version=2023-12', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new WhisperModelClient($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $client->request(new Whisper(Whisper::WHISPER_1), ['file' => 'audio-data'], ['task' => Task::TRANSCRIPTION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUsesTranslationEndpointWhenTaskIsSpecified()
    {
        $httpClient = new MockHttpClient([
            function ($method, $url): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://test.azure.com/openai/deployments/whspr/audio/translations?api-version=2023-12', $url);

                return new MockResponse('{"text": "Hello World"}');
            },
        ]);

        $client = new WhisperModelClient($httpClient, 'test.azure.com', 'whspr', '2023-12', 'test-key');
        $client->request(new Whisper(Whisper::WHISPER_1), ['file' => 'audio-data'], ['task' => Task::TRANSLATION]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
