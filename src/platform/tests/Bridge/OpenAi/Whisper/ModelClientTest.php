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
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\ModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper\Task;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ModelClient::class)]
#[Small]
final class ModelClientTest extends TestCase
{
    public function testItSupportsWhisperModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');
        $model = new Whisper();

        $this->assertTrue($client->supports($model, Action::CHAT));
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

        $client = new ModelClient($httpClient, 'test-key');
        $model = new Whisper();
        $payload = ['file' => 'audio-data'];

        $client->request($model, Action::CHAT, $payload);

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

        $client = new ModelClient($httpClient, 'test-key');
        $model = new Whisper();
        $payload = ['file' => 'audio-data'];
        $options = ['task' => Task::TRANSCRIPTION];

        $client->request($model, Action::CHAT, $payload, $options);

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

        $client = new ModelClient($httpClient, 'test-key');
        $model = new Whisper();
        $payload = ['file' => 'audio-data'];
        $options = ['task' => Task::TRANSLATION];

        $client->request($model, Action::CHAT, $payload, $options);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
