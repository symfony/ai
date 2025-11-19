<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\TextToSpeech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech\ModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech\ResultConverter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClientTest extends TestCase
{
    public function testSupportsTextToSpeechModel()
    {
        $converter = new ResultConverter();
        $model = new TextToSpeech('tts-1');

        $this->assertTrue($converter->supports($model));
    }

    public function testDoesntSupportOtherModels()
    {
        $converter = new ResultConverter();
        $model = new Model('test-model');

        $this->assertFalse($converter->supports($model));
    }

    public function testHappyCase()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/audio/speech', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            $expectedBody = '{"voice":"alloy","instruction":"Speak like a pirate","model":"tts-1","input":"Hello World!"}';
            self::assertSame($expectedBody, $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new TextToSpeech('tts-1'), 'Hello World!', [
            'voice' => 'alloy',
            'instruction' => 'Speak like a pirate',
        ]);
    }

    public function testFailsWithoutVoiceOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "voice" option is required for TextToSpeech requests.');

        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new TextToSpeech('tts-1'), 'Hello World!', [
            'instruction' => 'Speak like a pirate',
        ]);
    }

    public function testFailsWithStreamingOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text to speech results is not supported yet.');

        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new TextToSpeech('tts-1'), 'Hello World!', [
            'voice' => 'alloy',
            'stream' => true,
        ]);
    }
}
