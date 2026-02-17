<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VeniceClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Venice;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return match (true) {
            $model->supports(Capability::IMAGE_TO_VIDEO) || $model->supports(Capability::TEXT_TO_VIDEO) => $this->doVideoGeneration($model, $payload, $options),
            $model->supports(Capability::INPUT_MESSAGES) => $this->doGenerateCompletion($model, $payload, $options),
            $model->supports(Capability::TEXT_TO_IMAGE) => $this->doImageGeneration($model, $payload, $options),
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $payload, $options),
            $model->supports(Capability::SPEECH_RECOGNITION) => $this->doTranscription($model, $payload, $options),
            $model->supports(Capability::EMBEDDINGS) => $this->doGenerateEmbeddings($model, $payload, $options),
            default => throw new InvalidArgumentException('Unsupported model capability for Venice client.'),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doGenerateCompletion(Model $model, array|string $payload, array $options): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException('Payload must be an array for completion.');
        }

        if (!\array_key_exists('messages', $payload)) {
            throw new InvalidArgumentException('Payload must contain "messages" key for completion.');
        }

        if ($options['stream'] ?? false) {
            $streamOptions = (\array_key_exists('stream_options', $options) && \is_array($options['stream_options'])) ? $options['stream_options'] : [];

            if (!isset($streamOptions['include_usage'])) {
                $streamOptions['include_usage'] = true;
            }

            $options['stream_options'] = $streamOptions;
        }

        return new RawHttpResult($this->httpClient->request('POST', 'chat/completions', [
            'json' => [
                ...$options,
                'messages' => $payload['messages'],
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doTextToSpeech(Model $model, array|string $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'audio/speech', [
            'json' => [
                ...$options,
                'response_format' => 'mp3',
                'input' => \is_string($payload) ? $payload : $payload['text'],
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     */
    private function doTranscription(Model $model, array|string $payload, array $options): RawResultInterface
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array when using file-based transcription endpoint, given "%s".', \gettype($payload)));
        }

        if (!\is_array($payload['input_audio'] ?? null)) {
            throw new InvalidArgumentException('Payload must contain an "input_audio" array key for transcription.');
        }

        if (!\is_string($payload['input_audio']['path'] ?? null)) {
            throw new InvalidArgumentException('Payload "input_audio" must contain a "path" string key for transcription.');
        }

        return new RawHttpResult($this->httpClient->request('POST', 'audio/transcriptions', [
            'body' => [
                ...$options,
                'response_format' => 'json',
                'file' => fopen($payload['input_audio']['path'], 'r'),
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doImageGeneration(Model $model, array|string $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'image/generate', [
            'json' => [
                ...$options,
                'prompt' => \is_string($payload) ? $payload : $payload['prompt'],
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doGenerateEmbeddings(Model $model, array|string $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'embeddings', [
            'json' => [
                ...$options,
                'encoding_format' => 'float',
                'input' => \is_string($payload) ? $payload : $payload['text'],
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doVideoGeneration(Model $model, array|string $payload, array $options): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException('Payload must be an array for video generation.');
        }

        $finalPayload = match (true) {
            $model->supports(Capability::TEXT_TO_VIDEO) => [
                ...$options,
                'prompt' => $payload['text'] ?? $payload['prompt'] ?? throw new InvalidArgumentException('A valid input or a prompt is required for video generation.'),
            ],
            $model->supports(Capability::IMAGE_TO_VIDEO) => [
                ...$options,
                'prompt' => $payload['text'] ?? $payload['prompt'] ?? throw new InvalidArgumentException('A valid input or a prompt is required for video generation.'),
                'image_url' => $payload['image_url'] ?? throw new InvalidArgumentException('The image must be a valid URL or a data URL (ex: "data:").'),
            ],
            default => throw new InvalidArgumentException('Unsupported video generation.'),
        };

        $queuedVideoGenerationResponse = $this->httpClient->request('POST', 'video/queue', [
            'json' => [
                ...$finalPayload,
                'model' => $model->getName(),
                'duration' => $payload['duration'] ?? '5s',
                'aspect_ratio' => '16:9',
            ],
        ]);

        $payload = $queuedVideoGenerationResponse->toArray();

        $currentVideoStatusResponse = fn (): ResponseInterface => $this->httpClient->request('POST', 'video/retrieve', [
            'json' => [
                'model' => $payload['model'],
                'queue_id' => $payload['queue_id'],
            ],
        ]);

        while (json_validate($currentVideoStatusResponse()->getContent(false)) && 'PROCESSING' === $currentVideoStatusResponse()->toArray()['status']) {
            $this->clock->sleep(1);
        }

        return new RawHttpResult($this->httpClient->request('POST', 'video/retrieve', [
            'json' => [
                'model' => $payload['model'],
                'queue_id' => $payload['queue_id'],
            ],
        ]));
    }
}
