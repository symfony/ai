<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\SpeechToText;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsSpeechToTextModelOnly()
    {
        $client = new ModelClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->assertTrue($client->supports(new SpeechToText('audio/speech_to_text_async/openai')));
        $this->assertFalse($client->supports(new Ocr('ocr/ocr/google')));
    }

    public function testItSubmitsToTheAsynchronousEndpoint()
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$method, $url];
            $body = json_decode($options['body'], true);
            $this->assertSame('audio/speech_to_text_async/openai', $body['model']);
            $this->assertSame('https://example.com/audio.mp3', $body['input']['file']);
            $this->assertSame('en', $body['input']['language']);

            return new JsonMockResponse(['status' => 'success', 'output' => ['text' => 'Hello world']]);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $result = $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3', ['language' => 'en']);

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame(['POST', 'https://api.edenai.run/v3/universal-ai/async'], $requests[0]);
        $this->assertSame('Hello world', $result->getData()['output']['text']);
    }

    /**
     * Submitting is a single request: a job that is still running is handed to the caller as a
     * handle by the result converter, it is not waited for here.
     */
    public function testItDoesNotPollARunningJob()
    {
        $httpClient = new MockHttpClient(static fn () => new JsonMockResponse(
            ['status' => 'processing', 'public_id' => 'job-123'],
            ['http_code' => 202],
        ));

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $result = $client->request(new SpeechToText('audio/speech_to_text_async/google'), 'https://example.com/audio.mp3');

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame('processing', $result->getData()['status']);
    }

    public function testItKeepsWebhookOptionsAtRootLevel()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);
            $this->assertSame('https://example.com/webhook', $body['webhook_receiver']);
            $this->assertSame(['job' => 'transcription'], $body['user_webhook_parameters']);
            $this->assertArrayNotHasKey('webhook_receiver', $body['input']);
            $this->assertArrayNotHasKey('user_webhook_parameters', $body['input']);

            return new JsonMockResponse(['status' => 'processing', 'public_id' => 'job-123'], ['http_code' => 202]);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3', [
            'webhook_receiver' => 'https://example.com/webhook',
            'user_webhook_parameters' => ['job' => 'transcription'],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItUploadsBinaryPayloadBeforeSubmittingTheJob()
    {
        $responses = [
            new JsonMockResponse(['file_id' => 'file-42']),
            new JsonMockResponse(['status' => 'success', 'output' => ['text' => '']]),
        ];

        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses) {
            $requests[] = [$method, $url, $options['body'] ?? null];

            return array_shift($responses);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new SpeechToText('audio/speech_to_text_async/openai'), [
            'file_data' => [
                'data' => base64_encode('binary-audio'),
                'filename' => 'audio.mp3',
                'format' => 'audio/mpeg',
            ],
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertSame('https://api.edenai.run/v3/upload', $requests[0][1]);

        $body = json_decode(\is_string($requests[1][2]) ? $requests[1][2] : '', true);
        $this->assertSame('file-42', $body['input']['file']);
        $this->assertArrayNotHasKey('file_data', $body['input']);
    }
}
