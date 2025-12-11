<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart;

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
final class DecartClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $hostUrl = 'https://api.decart.ai/v1',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Decart;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return match (true) {
            \in_array(Capability::TEXT_TO_IMAGE, $model->getCapabilities(), true),
            \in_array(Capability::TEXT_TO_VIDEO, $model->getCapabilities(), true) => $this->generate($model, $payload, $options),
            \in_array(Capability::IMAGE_TO_IMAGE, $model->getCapabilities(), true),
            \in_array(Capability::IMAGE_TO_VIDEO, $model->getCapabilities(), true),
            \in_array(Capability::VIDEO_TO_VIDEO, $model->getCapabilities(), true) => $this->edit($model, $payload, $options),
            default => throw new InvalidArgumentException(\sprintf('The "%s" model is not supported.', $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function generate(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/generate/%s', $this->hostUrl, $model->getName()), [
            'headers' => [
                'x-api-key' => $this->apiKey,
            ],
            'body' => [
                'prompt' => \is_string($payload) ? $payload : $payload['text'],
                ...$options,
            ],
        ]));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function edit(Model $model, array|string $payload, array $options): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/generate/%s', $this->hostUrl, $model->getName()), [
            'headers' => [
                'x-api-key' => $this->apiKey,
            ],
            'body' => [
                'prompt' => $options['prompt'],
                'data' => fopen($payload['input_image']['path'] ?? $payload['input_video']['path'], 'r'),
                ...$options,
            ],
        ]));
    }
}
