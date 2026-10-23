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
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DecartClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Decart-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.decart.ai/v1',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Decart;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        return match (true) {
            \in_array(Capability::TEXT_TO_IMAGE, $model->getCapabilities(), true),
            \in_array(Capability::TEXT_TO_VIDEO, $model->getCapabilities(), true) => $this->generate($model, $payload, $options, $httpOptions),
            \in_array(Capability::IMAGE_TO_IMAGE, $model->getCapabilities(), true),
            \in_array(Capability::IMAGE_TO_VIDEO, $model->getCapabilities(), true),
            \in_array(Capability::VIDEO_TO_VIDEO, $model->getCapabilities(), true) => $this->edit($model, $payload, $options, $httpOptions),
            default => throw new InvalidArgumentException(\sprintf('The "%s" model is not supported.', $model->getName())),
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     * @param array<string, mixed>     $httpOptions
     */
    private function generate(Model $model, array|string $payload, array $options, array $httpOptions): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/generate/%s', $this->baseUrl, $model->getName()), [
            'headers' => [
                'x-api-key' => $this->apiKey,
            ],
            'body' => [
                'prompt' => \is_string($payload) ? $payload : $payload['text'],
                ...$options,
            ],
        ] + $httpOptions));
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     * @param array<string, mixed>     $httpOptions
     */
    private function edit(Model $model, array|string $payload, array $options, array $httpOptions): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/generate/%s', $this->baseUrl, $model->getName()), [
            'headers' => [
                'x-api-key' => $this->apiKey,
            ],
            'body' => [
                'prompt' => $options['prompt'],
                'data' => fopen($payload['input_image']['path'] ?? $payload['input_video']['path'], 'r'),
                ...$options,
            ],
        ] + $httpOptions));
    }
}
