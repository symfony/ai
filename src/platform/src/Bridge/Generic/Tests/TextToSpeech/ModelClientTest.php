<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Tests\TextToSpeech;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\TextToSpeech\ModelClient;
use Symfony\AI\Platform\Bridge\Generic\TextToSpeechModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ModelClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'http://localhost:3900');

        $this->assertTrue($modelClient->supports(new TextToSpeechModel('kokoro')));
    }

    public function testItIsNotSupportingTheIncorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'http://localhost:3900');

        $this->assertFalse($modelClient->supports(new CompletionsModel('gpt-4o-mini')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:3900/v1/audio/speech', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"voice":"alloy","response_format":"wav","speed":1.1,"instructions":"Speak calmly.","model":"tts-1","input":"Hello world"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'http://localhost:3900', 'sk-api-key');
        $modelClient->request(new TextToSpeechModel('tts-1'), 'Hello world', [
            'voice' => 'alloy',
            'response_format' => 'wav',
            'speed' => 1.1,
            'instructions' => 'Speak calmly.',
        ]);
    }

    public function testItAcceptsANormalizedTextPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('{"voice":"af_heart","model":"kokoro","input":"Hello world"}', $options['body']);

            return new MockResponse();
        };
        $modelClient = new ModelClient(new MockHttpClient([$resultCallback]), 'http://localhost:8880');
        $modelClient->request(new TextToSpeechModel('kokoro'), ['type' => 'text', 'text' => 'Hello world'], ['voice' => 'af_heart']);
    }

    public function testItSendsNoAuthorizationHeaderWithoutApiKey()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertArrayNotHasKey('authorization', $options['normalized_headers']);

            return new MockResponse();
        };
        $modelClient = new ModelClient(new MockHttpClient([$resultCallback]), 'http://127.0.0.1:3900');
        $modelClient->request(new TextToSpeechModel('kokoro'), 'Hello world');
    }

    public function testItThrowsWhenPayloadHasNoText()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'http://localhost:3900');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key.');

        $modelClient->request(new TextToSpeechModel('kokoro'), ['type' => 'image_url']);
    }

    #[TestWith(['stream'])]
    #[TestWith(['stream_format'])]
    public function testItRejectsStreaming(string $option)
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'http://localhost:3900');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text to speech results is not supported yet.');

        $modelClient->request(new TextToSpeechModel('kokoro'), 'Hello world', [$option => 'sse']);
    }

    #[TestWith(['http://localhost:3900', '/v1/audio/speech', 'http://localhost:3900/v1/audio/speech'])]
    #[TestWith(['http://localhost:3900/', '/v1/audio/speech', 'http://localhost:3900/v1/audio/speech'])]
    #[TestWith(['https://api.inference.com', '/api/v1/audio/speech', 'https://api.inference.com/api/v1/audio/speech'])]
    public function testItUsesCorrectUrl(string $baseUrl, string $path, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url) use ($expectedUrl): HttpResponse {
            self::assertSame($expectedUrl, $url);

            return new MockResponse();
        };
        $modelClient = new ModelClient(new MockHttpClient([$resultCallback]), $baseUrl, null, $path);
        $modelClient->request(new TextToSpeechModel('kokoro'), 'Hello world');
    }
}
