<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cartesia;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CartesiaClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Cartesia-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $version,
        string $baseUrl = 'https://api.cartesia.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Cartesia;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $options = PlatformOptions::toProviderOptions($options);

        return match (true) {
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doTextToSpeech($model, $payload, $options),
            $model->supports(Capability::SPEECH_TO_TEXT) => $this->doSpeechToText($model, $payload, $options),
            default => throw new RuntimeException(\sprintf('The model "%s" is not supported.', $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doTextToSpeech(Model $model, array|string $payload, array $options): RawHttpResult
    {
        $text = \is_string($payload) ? $payload : ($payload['text'] ?? throw new RuntimeException('The payload must contain a "text" key.'));

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/tts/bytes', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Cartesia-Version' => $this->version,
            ],
            'json' => [
                ...$options,
                'model_id' => $model->getName(),
                'transcript' => $text,
                'voice' => [
                    'mode' => 'id',
                    'id' => $options['voice'],
                ],
                'output_format' => $options['output_format'],
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doSpeechToText(Model $model, array|string $payload, array $options): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/stt', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Cartesia-Version' => $this->version,
            ],
            'body' => [
                ...$options,
                'model' => $model->getName(),
                'file' => fopen($payload['input_audio']['path'], 'r'),
                'timestamp_granularities[]' => 'word',
            ],
        ]));
    }
}
