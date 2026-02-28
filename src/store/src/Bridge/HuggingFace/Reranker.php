<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\HuggingFace;

use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Reranker\RerankerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reranker using a self-hosted HuggingFace Text Embeddings Inference (TEI) server.
 *
 * @see https://huggingface.co/docs/text-embeddings-inference/en/quick_tour
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Reranker implements RerankerInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $endpoint,
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

        $response = $this->client->request('POST', rtrim($this->endpoint, '/').'/rerank', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => $query,
                'texts' => $texts,
                'raw_scores' => false,
                'truncate' => true,
            ],
        ]);

        /** @var list<array{index: int, score: float, text: string}> $data */
        $data = $response->toArray();

        // Sort by score descending and take topK
        usort($data, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $data = \array_slice($data, 0, $topK);

        $reranked = [];
        foreach ($data as $result) {
            $reranked[] = $documents[$result['index']]->withScore($result['score']);
        }

        return $reranked;
    }
}
