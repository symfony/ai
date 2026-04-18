<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\Venice\Venice;
use Symfony\AI\Platform\Bridge\Venice\VeniceClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class VeniceClientTest extends TestCase
{
    public function testClientCanTriggerCompletion()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'choices' => [
                    [
                        'finish_reason' => 'stop',
                        'index' => 0,
                        'logprobs' => null,
                        'message' => [
                            'content' => 'Hello! How can I help you?',
                            'reasoning_content' => null,
                            'role' => 'assistant',
                            'tool_calls' => [],
                        ],
                        'stop_reason' => null,
                    ],
                ],
                'model' => 'llama-3.3-70b',
                'object' => 'chat.completion',
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 8,
                    'total_tokens' => 18,
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(new Venice('llama-3.3-70b', [
            Capability::INPUT_MESSAGES,
        ]), ['messages' => [['role' => 'user', 'content' => 'Hello']]]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerCompletionAsStream()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            if (!\is_string($options['body'] ?? null)) {
                $this->fail('Expected body to be a string.');
            }

            $body = json_decode($options['body'], true);

            if (!\is_array($body)) {
                $this->fail('Failed to decode request body.');
            }

            $this->assertSame('POST', $method);
            $this->assertStringContainsString('chat/completions', $url);
            $this->assertTrue($body['stream']);

            $streamOptions = $body['stream_options'] ?? [];

            if (!\is_array($streamOptions)) {
                $this->fail('Expected stream_options to be an array.');
            }

            $this->assertTrue($streamOptions['include_usage']);

            return new JsonMockResponse([
                'id' => 'chatcmpl-8bac04777b7842a988f69cfb8d952d54',
                'object' => 'chat.completion',
                'created' => (new \DateTimeImmutable())->getTimestamp(),
                'model' => 'venice-uncensored',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => ['content' => 'Hi'],
                    ],
                ],
            ]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(new Venice('llama-3.3-70b', [
            Capability::INPUT_MESSAGES,
        ]), ['messages' => [['role' => 'user', 'content' => 'Hello']]], ['stream' => true]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerImageGeneration()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            if (!\is_string($options['body'] ?? null)) {
                $this->fail('Expected body to be a string.');
            }

            $body = json_decode($options['body'], true);

            if (!\is_array($body)) {
                $this->fail('Failed to decode request body.');
            }

            $this->assertSame('POST', $method);
            $this->assertStringContainsString('image/generate', $url);
            $this->assertSame('A cat on a roof', $body['prompt']);
            $this->assertSame('fluently-xl', $body['model']);

            return new JsonMockResponse([
                'data' => [
                    [
                        'url' => 'https://venice.ai/images/generated/123.png',
                    ],
                ],
            ]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(new Venice('fluently-xl', [
            Capability::TEXT_TO_IMAGE,
            Capability::INPUT_TEXT,
        ]), 'A cat on a roof');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerTextToSpeech()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            if (!\is_string($options['body'] ?? null)) {
                $this->fail('Expected body to be a string.');
            }

            $body = json_decode($options['body'], true);

            if (!\is_array($body)) {
                $this->fail('Failed to decode request body.');
            }

            $this->assertSame('POST', $method);
            $this->assertStringContainsString('audio/speech', $url);
            $this->assertSame('Hello world', $body['input']);
            $this->assertSame('mp3', $body['response_format']);
            $this->assertSame('tts-kokoro', $body['model']);

            return new JsonMockResponse([]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(new Venice('tts-kokoro', [
            Capability::TEXT_TO_SPEECH,
            Capability::INPUT_TEXT,
        ]), 'Hello world');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerTextRecognition()
    {
        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'));

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'text' => 'Hello world',
                'duration' => 100,
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(new Venice('tts-kokoro', [
            Capability::SPEECH_RECOGNITION,
            Capability::INPUT_AUDIO,
            Capability::OUTPUT_TEXT,
        ]), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerEmbeddings()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    [
                        'embedding' => [
                            0.0023064255,
                            -0.009327292,
                            0.015797377,
                        ],
                        'index' => 0,
                        'object' => 'embedding',
                    ],
                ],
                'model' => 'text-embedding-bge-m3',
                'object' => 'list',
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(new Venice('text-embedding-bge-m3', [
            Capability::EMBEDDINGS,
            Capability::INPUT_TEXT,
            Capability::OUTPUT_EMBEDDINGS,
        ]), 'foo');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
