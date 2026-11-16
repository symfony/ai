<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Reranker;

use Symfony\AI\Platform\Bridge\Voyage\Reranker;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Pauline Vos <pauline.vos@mongodb.com>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Voyage-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.voyageai.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Reranker;
    }

    /**
     * @param array{query?: string, texts?: list<string>}|object|string $payload
     * @param array{top_k?: int, truncation?: bool}                     $options
     */
    public function request(Model $model, object|string|array $payload, array $options = []): RawHttpResult
    {
        if (!\is_array($payload) || !isset($payload['query'], $payload['texts'])) {
            throw new InvalidArgumentException('Reranker payload must be an array with "query" and "texts" keys.');
        }

        $body = [
            'model' => $model->getName(),
            'query' => $payload['query'],
            'documents' => $payload['texts'],
        ];

        if (isset($options['top_k'])) {
            $body['top_k'] = $options['top_k'];
        }

        if (isset($options['truncation'])) {
            $body['truncation'] = $options['truncation'];
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/rerank', [
            'auth_bearer' => $this->apiKey,
            'json' => $body,
        ]));
    }
}
