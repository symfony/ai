<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MiniMaxClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.minimax.io/v1',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof MiniMax;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        return match (true) {
            $model->supports(Capability::INPUT_MESSAGES) => $this->doTextGeneration($model, $payload, $options, $httpOptions),
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doSpeechGeneration($model, $payload, $options, $httpOptions),
            $model->supports(Capability::TEXT_TO_IMAGE),
            $model->supports(Capability::IMAGE_TO_IMAGE) => $this->doImageGeneration($model, $payload, $options, $httpOptions),
            $model->supports(Capability::MUSIC) => $this->doMusicGeneration($model, $payload, $options, $httpOptions),
            $model->supports(Capability::TEXT_TO_VIDEO),
            $model->supports(Capability::IMAGE_TO_VIDEO),
            $model->supports(Capability::VIDEO_WITH_SUBJECT) => $this->doVideoGeneration($model, $payload, $options, $httpOptions),
            default => throw new InvalidArgumentException(\sprintf('The "%s" model is not supported.', $model->getName())),
        };
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     * @param array<string, mixed>        $httpOptions
     */
    private function doTextGeneration(Model $model, array|string $payload, array $options, array $httpOptions): RawResultInterface
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload is not an array, given "%s".', get_debug_type($payload)));
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/chat/completions', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody([
                ...$options,
                'model' => $model->getName(),
                'messages' => $payload['messages'],
            ]),
        ] + $httpOptions));
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     * @param array<string, mixed>        $httpOptions
     */
    private function doSpeechGeneration(Model $model, array|string $payload, array $options, array $httpOptions): RawResultInterface
    {
        $text = $this->extractText($payload);
        $async = (bool) ($options['async'] ?? false);
        unset($options['async']);

        $json = $options;
        $json['model'] = $model->getName();
        $json['text'] = $text;

        if (!$async && !\array_key_exists('output_format', $json)) {
            $json['output_format'] = 'hex';
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/%s', $this->endpoint, $async ? 't2a_async_v2' : 't2a_v2'), [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody($json),
        ] + $httpOptions));
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     * @param array<string, mixed>        $httpOptions
     */
    private function doImageGeneration(Model $model, array|string $payload, array $options, array $httpOptions): RawResultInterface
    {
        $json = $options;
        $json['model'] = $model->getName();
        $json['prompt'] = $this->extractText($payload);

        if (!\array_key_exists('response_format', $json)) {
            $json['response_format'] = 'base64';
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/image_generation', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody($json),
        ] + $httpOptions));
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     * @param array<string, mixed>        $httpOptions
     */
    private function doMusicGeneration(Model $model, array|string $payload, array $options, array $httpOptions): RawResultInterface
    {
        if (!\array_key_exists('lyrics', $options)) {
            throw new InvalidArgumentException('The "lyrics" option is required when generating music.');
        }

        $json = $options;
        $json['model'] = $model->getName();
        $json['prompt'] = $this->extractText($payload);

        if (!\array_key_exists('output_format', $json)) {
            $json['output_format'] = 'hex';
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/music_generation', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody($json),
        ] + $httpOptions));
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     * @param array<string, mixed>        $httpOptions
     */
    private function doVideoGeneration(Model $model, array|string $payload, array $options, array $httpOptions): RawResultInterface
    {
        $json = $options;
        $json['model'] = $model->getName();
        $json['prompt'] = $this->extractText($payload);

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/video_generation', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody($json),
        ] + $httpOptions));
    }

    /**
     * Extracts the plain text from either a raw string payload or the array shape produced by the
     * Text content normalizer (`['type' => 'text', 'text' => '...']`).
     *
     * @param array<string, mixed>|string $payload
     */
    private function extractText(array|string $payload): string
    {
        if (\is_string($payload)) {
            return $payload;
        }

        if (\array_key_exists('text', $payload) && \is_string($payload['text'])) {
            return $payload['text'];
        }

        throw new InvalidArgumentException('The payload must be a string or contain a "text" key.');
    }
}
