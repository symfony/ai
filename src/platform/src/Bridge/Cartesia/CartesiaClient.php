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
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CartesiaClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $version,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Cartesia;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return match (true) {
            \in_array(Capability::TEXT_TO_SPEECH, $model->getCapabilities()) => $this->doTextToSpeech($model, $payload, $options),
            \in_array(Capability::SPEECH_TO_TEXT, $model->getCapabilities()) => $this->doSpeechToText($model, $payload, $options),
            default => throw new RuntimeException(\sprintf('The model "%s" is not supported.', $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function doTextToSpeech(Model $model, array|string $payload, array $options): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', 'https://api.cartesia.ai/tts/bytes', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Cartesia-Version' => $this->version,
            ],
            'json' => [
                ...$options,
                'model_id' => $model->getName(),
                'transcript' => $payload['text'],
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
        return new RawHttpResult($this->httpClient->request('POST', 'https://api.cartesia.ai/stt', [
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
