<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Distance\DistanceCalculator;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly CacheInterface&CacheItemPoolInterface $cache,
        private readonly DistanceCalculator $distanceCalculator = new DistanceCalculator(),
        private readonly string $cacheKey = '_vectors',
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        if ($this->cache->hasItem($this->cacheKey)) {
            return;
        }

        $this->cache->get($this->cacheKey, static fn (): array => []);
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $existingVectors = $this->cache->get($this->cacheKey, static fn (): array => []);

        $newVectors = array_map(static fn (VectorDocument $document): array => [
            'id' => $document->getId(),
            'vector' => $document->getVector()->getData(),
            'metadata' => $document->getMetadata()->getArrayCopy(),
        ], $documents);

        $cacheItem = $this->cache->getItem($this->cacheKey);

        $cacheItem->set([
            ...$existingVectors,
            ...$newVectors,
        ]);

        $this->cache->save($cacheItem);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $cacheItem = $this->cache->getItem($this->cacheKey);

        if (!$cacheItem->isHit()) {
            return;
        }

        if ([] === $existingVectors = $cacheItem->get()) {
            return;
        }

        if (\is_string($ids)) {
            $ids = [$ids];
        }

        $filteredVectors = array_filter($existingVectors, static fn (array $document) => !\in_array($document['id'], $ids, true));

        $cacheItem->set(array_values($filteredVectors));
        $this->cache->save($cacheItem);
    }

    public function supports(string $queryClass): bool
    {
        return \in_array($queryClass, [
            VectorQuery::class,
            TextQuery::class,
            HybridQuery::class,
        ], true);
    }

    /**
     * @param array{
     *     maxItems?: positive-int,
     *     filter?: callable(VectorDocument): bool
     * } $options If maxItems is provided, only the top N results will be returned.
     *            If filter is provided, only documents matching the filter will be considered.
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        return match (true) {
            $query instanceof VectorQuery => $this->queryVector($query, $options),
            $query instanceof TextQuery => $this->queryText($query, $options),
            $query instanceof HybridQuery => $this->queryHybrid($query, $options),
            default => throw new UnsupportedQueryTypeException($query::class, $this),
        };
    }

    public function drop(array $options = []): void
    {
        $this->cache->clear();
    }

    /**
     * @param array{
     *     maxItems?: positive-int,
     *     filter?: callable(VectorDocument): bool,
     * } $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryVector(VectorQuery $query, array $options): iterable
    {
        $documents = $this->cache->get($this->cacheKey, static fn (): array => []);

        if ([] === $documents) {
            return;
        }

        $vectorDocuments = array_map(static fn (array $document): VectorDocument => new VectorDocument(
            id: $document['id'],
            vector: new Vector($document['vector']),
            metadata: new Metadata($document['metadata']),
        ), $documents);

        if (isset($options['filter'])) {
            $vectorDocuments = array_values(array_filter($vectorDocuments, $options['filter']));
        }

        yield from $this->distanceCalculator->calculate($vectorDocuments, $query->getVector(), $options['maxItems'] ?? null);
    }

    /**
     * @param array{
     *     maxItems?: positive-int,
     *     filter?: callable(VectorDocument): bool,
     * } $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryText(TextQuery $query, array $options): iterable
    {
        $documents = $this->cache->get($this->cacheKey, static fn (): array => []);

        if ([] === $documents) {
            return;
        }

        $vectorDocuments = array_map(static fn (array $document): VectorDocument => new VectorDocument(
            id: $document['id'],
            vector: new Vector($document['vector']),
            metadata: new Metadata($document['metadata']),
        ), $documents);

        if (isset($options['filter'])) {
            $vectorDocuments = array_values(array_filter($vectorDocuments, $options['filter']));
        }

        $filteredDocuments = array_filter($vectorDocuments, static function (VectorDocument $doc) use ($query) {
            $text = strtolower($doc->getMetadata()->getText() ?? '');

            foreach ($query->getTexts() as $searchText) {
                if (str_contains($text, strtolower($searchText))) {
                    return true;
                }
            }

            return false;
        });

        $maxItems = $options['maxItems'] ?? null;
        $count = 0;

        foreach ($filteredDocuments as $document) {
            if (null !== $maxItems && $count >= $maxItems) {
                break;
            }

            yield $document;
            ++$count;
        }
    }

    /**
     * @param array{
     *     maxItems?: positive-int,
     *     filter?: callable(VectorDocument): bool,
     * } $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryHybrid(HybridQuery $query, array $options): iterable
    {
        $vectorResults = iterator_to_array($this->queryVector(
            new VectorQuery($query->getVector()),
            $options
        ));

        $textResults = iterator_to_array($this->queryText(
            new TextQuery($query->getTexts()),
            $options
        ));

        $mergedResults = [];
        $seenIds = [];

        foreach ($vectorResults as $doc) {
            $id = (string) $doc->getId();
            if (!isset($seenIds[$id])) {
                $mergedResults[] = new VectorDocument(
                    id: $doc->getId(),
                    vector: $doc->getVector(),
                    metadata: $doc->getMetadata(),
                    score: null !== $doc->getScore() ? $doc->getScore() * $query->getSemanticRatio() : null,
                );
                $seenIds[$id] = true;
            }
        }

        foreach ($textResults as $doc) {
            $id = (string) $doc->getId();
            if (!isset($seenIds[$id])) {
                $mergedResults[] = $doc;
                $seenIds[$id] = true;
            }
        }

        if (isset($options['filter'])) {
            $mergedResults = array_values(array_filter($mergedResults, $options['filter']));
        }

        $maxItems = $options['maxItems'] ?? null;
        $count = 0;

        foreach ($mergedResults as $document) {
            if (null !== $maxItems && $count >= $maxItems) {
                break;
            }

            yield $document;
            ++$count;
        }
    }
}
