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
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

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

    public function testItReturnsImmediatelyOnSynchronousResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'status' => 'success',
                'output' => ['text' => 'Hello world'],
            ]),
        ]);

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());
        $result = $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3');

        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertSame('Hello world', $result->getData()['output']['text']);
    }

    public function testItPollsUntilJobCompletes()
    {
        $responses = [
            new JsonMockResponse([
                'status' => 'processing',
                'public_id' => 'job-123',
                'output' => null,
            ]),
            new JsonMockResponse([
                'status' => 'processing',
                'public_id' => 'job-123',
                'output' => null,
            ]),
            new JsonMockResponse([
                'status' => 'success',
                'public_id' => 'job-123',
                'output' => ['text' => 'Polled result'],
            ]),
        ];

        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$requests, &$responses) {
            $requests[] = [$method, $url];

            return array_shift($responses);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());
        $result = $client->request(new SpeechToText('audio/speech_to_text_async/google'), 'https://example.com/audio.mp3');

        $this->assertSame(3, $httpClient->getRequestsCount());
        $this->assertSame(['POST', 'https://api.edenai.run/v3/universal-ai/async'], $requests[0]);
        $this->assertSame(['GET', 'https://api.edenai.run/v3/universal-ai/async/job-123'], $requests[1]);
        $this->assertSame(['GET', 'https://api.edenai.run/v3/universal-ai/async/job-123'], $requests[2]);
        $this->assertSame('Polled result', $result->getData()['output']['text']);
    }

    public function testItSendsFilePayloadToAsyncEndpoint()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);
            self::assertSame('audio/speech_to_text_async/openai', $body['model']);
            self::assertSame('https://example.com/audio.mp3', $body['input']['file']);
            self::assertSame('en', $body['input']['language']);

            return new JsonMockResponse(['status' => 'success', 'output' => ['text' => '']]);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());
        $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3', ['language' => 'en']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItPollsPendingJobsToo()
    {
        $responses = [
            new JsonMockResponse(['status' => 'pending', 'public_id' => 'job-123']),
            new JsonMockResponse(['status' => 'success', 'public_id' => 'job-123', 'output' => ['text' => 'Done']]),
        ];

        $httpClient = new MockHttpClient(static function () use (&$responses) {
            return array_shift($responses);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());
        $result = $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3');

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertSame('Done', $result->getData()['output']['text']);
    }

    public function testItThrowsWhenTheJobNeverCompletes()
    {
        $httpClient = new MockHttpClient(static fn () => new JsonMockResponse(['status' => 'processing', 'public_id' => 'job-123']));

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock(), null, 3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI speech-to-text job "job-123" did not complete in time.');

        try {
            $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3');
        } finally {
            // The initial submission plus one request per allowed poll.
            $this->assertSame(4, $httpClient->getRequestsCount());
        }
    }

    public function testItThrowsWhenAPendingJobCarriesNoPublicId()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['status' => 'processing']));

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI async job response does not contain a public_id.');

        $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3');
    }

    public function testItMapsAnErrorStatusOnTheSubmissionOntoAPlatformException()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['detail' => 'Invalid token'], ['http_code' => 401]));

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid token');

        $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3');
    }

    /**
     * A gateway in front of the API can answer a poll with an empty body, which must not
     * escape as an HttpClient decoding exception.
     */
    public function testItMapsAnUndecodableBodyOntoAPlatformException()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 200]));

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not decode the Eden AI asynchronous job response.');

        $client->request(new SpeechToText('audio/speech_to_text_async/openai'), 'https://example.com/audio.mp3');
    }

    public function testItRejectsANonSensicalPollBudget()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The maximum number of polls must be at least 1, "0" given.');

        new ModelClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key', new MockClock(), null, 0);
    }

    public function testItRejectsANonSensicalPollInterval()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The poll interval must be at least 1 second, "0" given.');

        new ModelClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key', new MockClock(), null, 10, 0);
    }

    public function testItKeepsWebhookOptionsAtRootLevel()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) {
            $body = json_decode($options['body'], true);
            self::assertSame('https://example.com/webhook', $body['webhook_receiver']);
            self::assertSame(['job' => 'transcription'], $body['user_webhook_parameters']);
            self::assertArrayNotHasKey('webhook_receiver', $body['input']);
            self::assertArrayNotHasKey('user_webhook_parameters', $body['input']);

            return new JsonMockResponse(['status' => 'success', 'output' => ['text' => '']]);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());
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

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key', new MockClock());
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
