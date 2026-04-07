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
        $payload = new VenicePayload($payload);

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
     * @param array<string, mixed> $options
     */
    private function doGenerateCompletion(Model $model, VenicePayload $payload, array $options): RawResultInterface
    {
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
                'messages' => $payload->asCompletionPayload(),
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function doTextToSpeech(Model $model, VenicePayload $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'audio/speech', [
            'json' => [
                ...$options,
                'response_format' => 'mp3',
                'input' => $payload->asTextToSpeechPayload(),
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function doTranscription(Model $model, VenicePayload $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'audio/transcriptions', [
            'body' => [
                ...$options,
                'response_format' => 'json',
                'file' => fopen($payload->asSpeechToTextPayload(), 'r'),
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doImageGeneration(Model $model, VenicePayload $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'image/generate', [
            'json' => [
                ...$options,
                'prompt' => $payload->asImageGeneration(),
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function doGenerateEmbeddings(Model $model, VenicePayload $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'embeddings', [
            'json' => [
                ...$options,
                'encoding_format' => 'float',
                'input' => $payload->asEmbeddingsPayload(),
                'model' => $model->getName(),
            ],
        ]));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function doVideoGeneration(Model $model, VenicePayload $payload, array $options): RawResultInterface
    {
        $finalPayload = $payload->asVideoGenerationPayload($model, $options);

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
