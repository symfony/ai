<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Embeddings;

use Symfony\AI\Platform\Bridge\Mistral\Embeddings;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Mistral-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.mistral.ai',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Embeddings;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/embeddings', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ] + $httpOptions));
    }
}
