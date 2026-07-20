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
use Symfony\AI\Platform\Bridge\Venice\VeniceParameters;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

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

        $client->request(new Venice('nvidia/parakeet-tdt-0.6b-v3', [
            Capability::SPEECH_TO_TEXT,
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

    public function testClientPassesVeniceParametersAsArray()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = $this->decodeJsonBody($options);
            $veniceParams = $body['venice_parameters'] ?? null;
            $this->assertIsArray($veniceParams);

            $this->assertSame('on', $veniceParams['enable_web_search'] ?? null);
            $this->assertSame('alan-watts', $veniceParams['character_slug'] ?? null);

            return new JsonMockResponse([
                'choices' => [['message' => ['content' => 'Hi']]],
            ]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(
            new Venice('venice-uncensored', [Capability::INPUT_MESSAGES]),
            ['messages' => [['role' => 'user', 'content' => 'Hello']]],
            ['venice_parameters' => ['enable_web_search' => 'on', 'character_slug' => 'alan-watts']],
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientPassesVeniceParametersAsTypedObject()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = $this->decodeJsonBody($options);
            $veniceParams = $body['venice_parameters'] ?? null;
            $this->assertIsArray($veniceParams);

            $this->assertSame('auto', $veniceParams['enable_web_search'] ?? null);
            $this->assertTrue($veniceParams['enable_web_citations'] ?? null);
            $this->assertTrue($veniceParams['strip_thinking_response'] ?? null);

            return new JsonMockResponse([
                'choices' => [['message' => ['content' => 'Hi']]],
            ]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(
            new Venice('venice-uncensored', [Capability::INPUT_MESSAGES]),
            ['messages' => [['role' => 'user', 'content' => 'Hello']]],
            ['venice_parameters' => new VeniceParameters(
                enableWebSearch: VeniceParameters::WEB_SEARCH_AUTO,
                enableWebCitations: true,
                stripThinkingResponse: true,
            )],
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCustomTtsResponseFormat()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = $this->decodeJsonBody($options);

            $this->assertSame('opus', $body['response_format'] ?? null);
            $this->assertSame('en-US', $body['language'] ?? null);

            return new JsonMockResponse([]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(
            new Venice('tts-kokoro', [Capability::TEXT_TO_SPEECH, Capability::INPUT_TEXT]),
            'Hello',
            ['response_format' => 'opus', 'language' => 'en-US'],
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCustomEmbeddingsEncodingFormat()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $body = $this->decodeJsonBody($options);

            $this->assertSame('base64', $body['encoding_format'] ?? null);
            $this->assertSame(512, $body['dimensions'] ?? null);

            return new JsonMockResponse([
                'data' => [['embedding' => [0.1, 0.2]]],
            ]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(
            new Venice('text-embedding-bge-m3', [Capability::EMBEDDINGS, Capability::INPUT_TEXT]),
            'foo',
            ['encoding_format' => 'base64', 'dimensions' => 512],
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerTextToVideoAndPollUntilReady()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['model' => 'seedance-1-5-pro-text-to-video', 'queue_id' => 'q-1']),
            new JsonMockResponse(['status' => 'PROCESSING']),
            new JsonMockResponse(['status' => 'PROCESSING']),
            new MockResponse('binary-video-bytes', ['response_headers' => ['content-type' => 'video/mp4']]),
        ], 'https://api.venice.ai/api/v1/');

        $clock = new MockClock();
        $client = new VeniceClient($httpClient, $clock);

        $client->request(
            new Venice('seedance-1-5-pro-text-to-video', [Capability::TEXT_TO_VIDEO]),
            ['prompt' => 'Sunset over a beach'],
            ['polling_interval_seconds' => 1],
        );

        $this->assertSame(4, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerImageToVideoWithAspectRatioOverride()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            if (str_contains($url, 'video/queue')) {
                $body = $this->decodeJsonBody($options);
                $this->assertSame('1:1', $body['aspect_ratio'] ?? null);
                $this->assertSame('https://example.com/img.png', $body['image_url'] ?? null);

                return new JsonMockResponse(['model' => 'seedance-1-5-pro-image-to-video', 'queue_id' => 'q-2']);
            }

            return new MockResponse('video-mp4', ['response_headers' => ['content-type' => 'video/mp4']]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient, new MockClock());

        $client->request(
            new Venice('seedance-1-5-pro-image-to-video', [Capability::IMAGE_TO_VIDEO]),
            ['prompt' => 'Slow zoom', 'image_url' => 'https://example.com/img.png'],
            ['aspect_ratio' => '1:1'],
        );

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerVideoToVideo()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['model' => 'runway-gen4-aleph', 'queue_id' => 'q-3']),
            new MockResponse('v2v', ['response_headers' => ['content-type' => 'video/mp4']]),
        ], 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient, new MockClock());

        $client->request(
            new Venice('runway-gen4-aleph', [Capability::VIDEO_TO_VIDEO]),
            ['prompt' => 'Restyle', 'video_url' => 'https://example.com/source.mp4'],
        );

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testClientVideoPollingTimesOut()
    {
        $responses = [new JsonMockResponse(['model' => 'seedance-1-5-pro-text-to-video', 'queue_id' => 'q-x'])];
        for ($i = 0; $i < 5; ++$i) {
            $responses[] = new JsonMockResponse(['status' => 'PROCESSING']);
        }

        $httpClient = new MockHttpClient($responses, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient, new MockClock());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Video generation timed out after 3 polling attempts.');

        $client->request(
            new Venice('seedance-1-5-pro-text-to-video', [Capability::TEXT_TO_VIDEO]),
            ['prompt' => 'X'],
            ['max_polling_attempts' => 3, 'polling_interval_seconds' => 0],
        );
    }

    public function testClientThrowsForUnsupportedCapability()
    {
        $client = new VeniceClient(new MockHttpClient([], 'https://api.venice.ai/api/v1/'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported model capability for Venice client.');

        $client->request(new Venice('unknown-model', [Capability::INPUT_TEXT]), 'hello');
    }

    public function testClientCanTriggerImageEdit()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertStringContainsString('image/edit', $url);
            $body = $this->decodeJsonBody($options);
            $this->assertSame('https://example.com/in.png', $body['image'] ?? null);
            $this->assertSame('Make it sepia', $body['prompt'] ?? null);
            $this->assertSame('firered-image-edit', $body['model'] ?? null);

            return new MockResponse('edited-bytes', ['response_headers' => ['content-type' => 'image/png']]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(
            new Venice('firered-image-edit', [Capability::IMAGE_TO_IMAGE, Capability::INPUT_IMAGE, Capability::OUTPUT_IMAGE]),
            ['image' => 'https://example.com/in.png', 'prompt' => 'Make it sepia'],
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerImageUpscale()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertStringContainsString('image/upscale', $url);
            $body = $this->decodeJsonBody($options);
            $this->assertSame('https://example.com/in.png', $body['image'] ?? null);
            $this->assertSame(2, $body['scale'] ?? null);

            return new MockResponse('upscaled-bytes', ['response_headers' => ['content-type' => 'image/png']]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(
            new Venice('upscaler', [Capability::IMAGE_TO_IMAGE, Capability::INPUT_IMAGE, Capability::OUTPUT_IMAGE]),
            ['image' => 'https://example.com/in.png'],
            ['mode' => 'upscale', 'scale' => 2],
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanTriggerBackgroundRemove()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->assertStringContainsString('image/background-remove', $url);

            return new MockResponse('transparent-bytes', ['response_headers' => ['content-type' => 'image/png']]);
        }, 'https://api.venice.ai/api/v1/');

        $client = new VeniceClient($httpClient);

        $client->request(
            new Venice('bria-bg-remover', [Capability::IMAGE_TO_IMAGE, Capability::INPUT_IMAGE, Capability::OUTPUT_IMAGE]),
            ['image' => 'data:image/png;base64,xyz'],
            ['mode' => 'background-remove'],
        );

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function decodeJsonBody(array $options): array
    {
        $body = $options['body'] ?? null;

        if (!\is_string($body)) {
            $this->fail('Expected request body to be a string.');
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            $this->fail('Expected request body to decode as a JSON object.');
        }

        $result = [];
        foreach ($decoded as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
