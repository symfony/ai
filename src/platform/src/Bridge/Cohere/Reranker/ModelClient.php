<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Reranker;

use Symfony\AI\Platform\Bridge\Cohere\Reranker;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Cohere-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.cohere.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Reranker;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!\is_array($payload) || !isset($payload['query'], $payload['texts'])) {
            throw new InvalidArgumentException('Reranker payload must be an array with "query" and "texts" keys.');
        }

        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        $body = [
            'model' => $model->getName(),
            'query' => $payload['query'],
            'documents' => $payload['texts'],
        ];

        if (isset($options['top_n'])) {
            $body['top_n'] = $options['top_n'];
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v2/rerank', [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ] + $httpOptions));
    }
}
