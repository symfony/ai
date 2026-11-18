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
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VeniceClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Venice;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        $payload = new VenicePayload($payload);

        return match (true) {
            $model->supports(Capability::IMAGE_TO_VIDEO) || $model->supports(Capability::TEXT_TO_VIDEO) || $model->supports(Capability::VIDEO_TO_VIDEO) => $this->doVideoGeneration($model, $payload, $options, $httpOptions),
            $model->supports(Capability::INPUT_MESSAGES) => $this->doGenerateCompletion($model, $payload, $options, $httpOptions),
            $model->supports(Capability::IMAGE_TO_IMAGE) => $this->doImageEdit($model, $payload, $options, $httpOptions),
            $model->supports(Capability::TEXT_TO_IMAGE) => $this->doImageGeneration($model, $payload, $options, $httpOptions),
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $payload, $options, $httpOptions),
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToText($model, $payload, $options, $httpOptions),
            $model->supports(Capability::EMBEDDINGS) => $this->doGenerateEmbeddings($model, $payload, $options, $httpOptions),
            default => throw new InvalidArgumentException('Unsupported model capability for Venice client.'),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doGenerateCompletion(Model $model, VenicePayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        if ($options['stream'] ?? false) {
            $streamOptions = (\array_key_exists('stream_options', $options) && \is_array($options['stream_options'])) ? $options['stream_options'] : [];

            if (!isset($streamOptions['include_usage'])) {
                $streamOptions['include_usage'] = true;
            }

            $options['stream_options'] = $streamOptions;
        }

        if (isset($options['venice_parameters']) && $options['venice_parameters'] instanceof VeniceParameters) {
            $options['venice_parameters'] = $options['venice_parameters']->toArray();
        }

        return new RawHttpResult($this->httpClient->request('POST', 'chat/completions', [
            'json' => [
                ...$options,
                'messages' => $payload->asCompletionPayload(),
                'model' => $model->getName(),
            ],
        ] + $httpOptions));
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doTextToSpeech(Model $model, VenicePayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'audio/speech', [
            'json' => [
                'response_format' => 'mp3',
                ...$options,
                'input' => $payload->asTextToSpeechPayload(),
                'model' => $model->getName(),
            ],
        ] + $httpOptions));
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doSpeechToText(Model $model, VenicePayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'audio/transcriptions', [
            'body' => [
                'response_format' => 'json',
                ...$options,
                'file' => fopen($payload->asSpeechToTextPayload(), 'r'),
                'model' => $model->getName(),
            ],
        ] + $httpOptions));
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doImageGeneration(Model $model, VenicePayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'image/generate', [
            'json' => [
                ...$options,
                'prompt' => $payload->asImageGeneration(),
                'model' => $model->getName(),
            ],
        ] + $httpOptions));
    }

    /**
     * Routes Venice "image edit" / "upscale" / "background-remove" requests to the
     * appropriate endpoint depending on the option `mode` (`edit` | `upscale` | `background-remove`).
     * Defaults to `edit`. The image comes from a normalized `Image` or from a raw `image` string
     * holding a base64 payload, a data URL or an HTTP URL.
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doImageEdit(Model $model, VenicePayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        $mode = \is_string($options['mode'] ?? null) ? $options['mode'] : 'edit';
        unset($options['mode']);

        return match ($mode) {
            'upscale' => new RawHttpResult($this->httpClient->request('POST', 'image/upscale', [
                'json' => [
                    ...$options,
                    ...$payload->asUpscalePayload(),
                    'model' => $model->getName(),
                ],
            ] + $httpOptions)),
            'background-remove' => new RawHttpResult($this->httpClient->request('POST', 'image/background-remove', [
                'json' => [
                    ...$options,
                    'image' => $payload->asImageEditPayload()['image'],
                ],
            ] + $httpOptions)),
            default => new RawHttpResult($this->httpClient->request('POST', 'image/edit', [
                'json' => [
                    ...$options,
                    ...$payload->asImageEditPayload($options, requirePrompt: true),
                    'model' => $model->getName(),
                ],
            ] + $httpOptions)),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doGenerateEmbeddings(Model $model, VenicePayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'embeddings', [
            'json' => [
                'encoding_format' => 'float',
                ...$options,
                'input' => $payload->asEmbeddingsPayload(),
                'model' => $model->getName(),
            ],
        ] + $httpOptions));
    }

    /**
     * Queues the generation and answers with its queue id; resolving it is the job of
     * {@see VeniceJobClient}.
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $httpOptions
     */
    private function doVideoGeneration(Model $model, VenicePayload $payload, array $options, array $httpOptions): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', 'video/queue', [
            'json' => [
                ...$payload->asVideoGenerationPayload($model, $options),
                'model' => $model->getName(),
            ],
        ] + $httpOptions));
    }
}
