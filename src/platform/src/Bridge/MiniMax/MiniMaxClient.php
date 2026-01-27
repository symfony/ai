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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MiniMaxClient implements ModelClientInterface
{
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
        return match (true) {
            $model->supports(Capability::INPUT_MESSAGES) => $this->doTextGeneration($model, $payload, $options),
            $model->supports(Capability::TEXT_TO_SPEECH_ASYNC),
            $model->supports(Capability::TEXT_TO_SPEECH) => $this->doSpeechGeneration($model, $payload, $options),
            $model->supports(Capability::TEXT_TO_IMAGE),
            $model->supports(Capability::IMAGE_TO_IMAGE) => $this->doImageGeneration($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('The "%s" model is not supported.', $model->getName())),
        };
    }

    private function doTextGeneration(Model $model, array|string $payload, array $options): RawResultInterface
    {
        if (!\is_array($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload is not an array, given "%s".', get_debug_type($payload)));
        }

        if (\array_key_exists('model', $payload)) {
            unset($payload['model']);
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/text/chatcompletion_v2', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                'messages' => $payload['messages'],
                ...$options,
            ],
        ]));
    }

    private function doSpeechGeneration(Model $model, array|string $payload, array $options): RawResultInterface
    {
        if (!\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('The payload is not a string, given "%s".', get_debug_type($payload)));
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/%s', $this->endpoint, $model->supports(Capability::TEXT_TO_SPEECH_ASYNC) ? 't2a_async_v2' : 't2a_v2'), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                'text' => $payload,
                ...$options,
            ],
        ]));
    }

    private function doImageGeneration(Model $model, array|string $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/image_generation', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'model' => $model->getName(),
                'prompt' => $options['prompt'] ?? $payload['prompt'] ?? throw new InvalidArgumentException('A prompt is required when using text-to-image and/or image-to-image.'),
            ],
        ]));
    }
}
