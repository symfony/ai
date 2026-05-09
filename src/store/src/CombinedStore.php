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
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;

/**
 * Combines vector and text stores using Reciprocal Rank Fusion (RRF).
 *
 * Decomposes HybridQuery into VectorQuery and TextQuery, queries both
 * sub-stores independently, and merges results using RRF scoring.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CombinedStore implements StoreInterface
{
    public function __construct(
        private readonly StoreInterface $vectorStore,
        private readonly StoreInterface $textStore,
        private readonly int $rrfK = 60,
    ) {
    }

    public function add(VectorDocument|array $documents): void
    {
        $this->vectorStore->add($documents);

        if ($this->textStore !== $this->vectorStore) {
            $this->textStore->add($documents);
        }
    }

    public function remove(string|array $ids, array $options = []): void
    {
        $this->vectorStore->remove($ids, $options);

        if ($this->textStore !== $this->vectorStore) {
            $this->textStore->remove($ids, $options);
        }
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        if ($query instanceof HybridQuery) {
            return $this->hybridQuery($query, $options);
        }

        if ($query instanceof VectorQuery && $this->vectorStore->supports(VectorQuery::class)) {
            return $this->vectorStore->query($query, $options);
        }

        if ($query instanceof TextQuery && $this->textStore->supports(TextQuery::class)) {
            return $this->textStore->query($query, $options);
        }

        throw new UnsupportedQueryTypeException($query::class, $this);
    }

    public function supports(string $queryClass): bool
    {
        if (HybridQuery::class === $queryClass) {
            return $this->vectorStore->supports(VectorQuery::class)
                && $this->textStore->supports(TextQuery::class);
        }

        return $this->vectorStore->supports($queryClass)
            || $this->textStore->supports($queryClass);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<VectorDocument>
     */
    private function hybridQuery(HybridQuery $query, array $options): array
    {
        $vectorResults = array_values(iterator_to_array(
            $this->vectorStore->query(new VectorQuery($query->getVector()), $options),
        ));

        $textResults = array_values(iterator_to_array(
            $this->textStore->query(new TextQuery($query->getText()), $options),
        ));

        return $this->reciprocalRankFusion($vectorResults, $textResults);
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
