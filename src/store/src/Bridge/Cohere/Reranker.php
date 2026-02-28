<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Cohere;

use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Reranker\RerankerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reranker using the Cohere Rerank API.
 *
 * @see https://docs.cohere.com/reference/rerank
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Reranker implements RerankerInterface
{
    private const API_URL = 'https://api.cohere.com/v1/rerank';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $apiKey,
        private readonly string $model = 'rerank-v3.5',
    ) {
    }

    public function rerank(string $query, array $documents, int $topK = 5): array
    {
        if ([] === $documents) {
            return [];
        }

        $texts = array_map(
            static fn (VectorDocument $doc): string => $doc->getMetadata()->getText() ?? $doc->getMetadata()->getSource() ?? '',
            $documents,
        );

        $response = $this->client->request('POST', self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'query' => $query,
                'documents' => $texts,
                'top_n' => $topK,
            ],
        ]);

        /** @var array{results: list<array{index: int, relevance_score: float}>} $data */
        $data = $response->toArray();

        $reranked = [];
        foreach ($data['results'] as $result) {
            $reranked[] = $documents[$result['index']]->withScore($result['relevance_score']);
        }

        return $reranked;
    }
}
