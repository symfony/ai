<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests\SpeechToText;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Ovh\SpeechToText;
use Symfony\AI\Platform\Bridge\Ovh\SpeechToText\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsSpeechToTextModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new SpeechToText('whisper-large-v3')));
    }

    public function testItDoesNotSupportCompletionsModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new CompletionsModel('gpt-oss-120b')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://oai.endpoints.kepler.ai.cloud.ovh.net/v1/audio/transcriptions', $url);
            $this->assertStringContainsString('Bearer test-key', $options['normalized_headers']['authorization'][0]);
            $this->assertStringContainsString('multipart/form-data', $options['normalized_headers']['content-type'][0]);

            return new MockResponse('{"text": "Hello world"}');
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new SpeechToText('whisper-large-v3'), ['file' => 'audio-data'], ['language' => 'en']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItSendsTheModelNameAndTheOptionsInTheBody()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = '';
            while ('' !== $chunk = $options['body']()) {
                $body .= $chunk;
            }

            $this->assertStringContainsString("name=\"model\"\r\n\r\nwhisper-large-v3-turbo", $body);
            $this->assertStringContainsString("name=\"language\"\r\n\r\nfr", $body);
            $this->assertStringNotContainsString('ignored', $body);

            return new MockResponse('{"text": "Hello world"}');
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new SpeechToText('whisper-large-v3-turbo'), ['file' => 'audio-data', 'model' => 'ignored'], ['language' => 'fr']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItHonorsCustomBaseUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('https://example.com/v1/audio/transcriptions', $url);

            return new MockResponse('{"text": "Hello world"}');
        });

        $client = new ModelClient($httpClient, 'test-key', 'https://example.com/');
        $client->request(new SpeechToText('whisper-large-v3'), ['file' => 'audio-data']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStringPayloadThrowsException()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $client->request(new SpeechToText('whisper-large-v3'), 'string payload');
    }
}
