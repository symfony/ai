<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store;

use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Reranker\RerankerInterface;

/**
 * Combines vector and text retrieval using Reciprocal Rank Fusion (RRF),
 * with optional cross-encoder reranking.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class HybridRetriever implements RetrieverInterface
{
    public function __construct(
        private readonly RetrieverInterface $vectorRetriever,
        private readonly RetrieverInterface $textRetriever,
        private readonly ?RerankerInterface $reranker = null,
        private readonly int $rrfK = 60,
        private readonly int $candidateCount = 20,
        private readonly int $topK = 5,
    ) {
    }

    public function retrieve(string $query, array $options = []): iterable
    {
        $candidateCount = $options['candidateCount'] ?? $this->candidateCount;
        $topK = $options['topK'] ?? $this->topK;

        $vectorResults = iterator_to_array(
            $this->vectorRetriever->retrieve($query, ['limit' => $candidateCount]),
        );

        $textResults = iterator_to_array(
            $this->textRetriever->retrieve($query, ['limit' => $candidateCount]),
        );

        $merged = $this->reciprocalRankFusion($vectorResults, $textResults);

        if (null !== $this->reranker) {
            return $this->reranker->rerank($query, $merged, $topK);
        }

        return \array_slice($merged, 0, $topK);
    }

    /**
     * @param list<VectorDocument> $list1
     * @param list<VectorDocument> $list2
     *
     * @return list<VectorDocument>
     */
    private function reciprocalRankFusion(array $list1, array $list2): array
    {
        /** @var array<string, float> $scores */
        $scores = [];

        /** @var array<string, VectorDocument> $documentsById */
        $documentsById = [];

        foreach ($list1 as $rank => $document) {
            $id = (string) $document->getId();
            $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / ($this->rrfK + $rank + 1);
            $documentsById[$id] = $document;
        }

        foreach ($list2 as $rank => $document) {
            $id = (string) $document->getId();
            $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / ($this->rrfK + $rank + 1);
            $documentsById[$id] = $document;
        }

        arsort($scores);

        $result = [];
        foreach (array_keys($scores) as $id) {
            $result[] = $documentsById[$id]->withScore($scores[$id]);
        }

        return $result;
    }
}
