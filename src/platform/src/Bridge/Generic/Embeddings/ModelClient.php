<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Embeddings;

use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This generic implementation is based on OpenAI's initial embeddings endpoint, that got later adopted by other
 * providers as well. It can be used by any bridge or directly with the generic Factory.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of an OpenAI-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly ?HttpClientInterface $httpClient,
        string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly string $path = '/v1/embeddings',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof EmbeddingsModel;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$this->path, [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ] + $httpOptions));
    }
}
