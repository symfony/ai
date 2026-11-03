<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner\Embeddings;

use Symfony\AI\Platform\Bridge\DockerModelRunner\Embeddings;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of the Docker Model Runner instance, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $baseUrl,
    ) {
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

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/engines/v1/embeddings', $this->baseUrl), [
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $payload,
            ]),
        ] + $httpOptions));
    }
}
