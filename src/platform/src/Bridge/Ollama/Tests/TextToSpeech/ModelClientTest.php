<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests\TextToSpeech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech;
use Symfony\AI\Platform\Bridge\Ollama\TextToSpeech\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ModelClientTest extends TestCase
{
    public function testSupportsTextToSpeechModel()
    {
        $client = new ModelClient(new MockHttpClient());

        $this->assertTrue($client->supports(new TextToSpeech('phonon')));
        $this->assertFalse($client->supports(new Ollama('llama3.2')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testItRequestsSpeechWithAStringPayload()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:11434/v1/audio/speech', $url);
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);

            $body = json_decode($options['body'], true);
            $this->assertSame('phonon', $body['model']);
            $this->assertSame('Hello from Ollama.', $body['input']);
            $this->assertSame('Zoey', $body['voice']);
            $this->assertSame('wav', $body['response_format']);
            $this->assertSame(1, $body['speed']);

            return new MockResponse('fake-audio-bytes');
        }, 'http://127.0.0.1:11434');

        (new ModelClient($httpClient))->request(new TextToSpeech('phonon'), 'Hello from Ollama.', [
            'voice' => 'Zoey',
            'response_format' => 'wav',
            'speed' => 1,
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItRequestsSpeechWithAnArrayPayload()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): HttpResponse {
            $body = json_decode($options['body'], true);
            $this->assertSame('phonon', $body['model']);
            $this->assertSame('Hello from Ollama.', $body['input']);
            $this->assertArrayNotHasKey('voice', $body);

            return new MockResponse('fake-audio-bytes');
        }, 'http://127.0.0.1:11434');

        (new ModelClient($httpClient))->request(new TextToSpeech('phonon'), ['type' => 'text', 'text' => 'Hello from Ollama.']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItFailsWithoutATextPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must contain a "text" key.');

        (new ModelClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), ['type' => 'text']);
    }

    public function testItFailsWithANonStringTextPayload()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload "text" key must be a string.');

        (new ModelClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), ['text' => ['Hello']]);
    }

    public function testItFailsWithStreamingOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text to speech results is not supported yet.');

        (new ModelClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), 'Hello from Ollama.', [
            'stream' => true,
        ]);
    }

    public function testItFailsWithStreamFormatOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Streaming text to speech results is not supported yet.');

        (new ModelClient(new MockHttpClient()))->request(new TextToSpeech('phonon'), 'Hello from Ollama.', [
            'stream_format' => 'audio',
        ]);
    }
}
