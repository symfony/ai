<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\InMemory;

use Symfony\AI\Store\Distance\DistanceCalculator;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
class Store implements ManagedStoreInterface, StoreInterface
{
    /**
     * @var VectorDocument[]
     */
    private array $documents = [];

    public function __construct(
        private readonly DistanceCalculator $distanceCalculator = new DistanceCalculator(),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->drop();
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        array_push($this->documents, ...$documents);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        if (\is_string($ids)) {
            $ids = [$ids];
        }

        $this->documents = array_values(array_filter($this->documents, static function (VectorDocument $document) use ($ids): bool {
            return !\in_array((string) $document->getId(), $ids, true);
        }));
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
        $this->documents = [];
    }

    /**
     * @param array{maxItems?: positive-int, filter?: callable(VectorDocument): bool} $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryVector(VectorQuery $query, array $options): iterable
    {
        $documents = $this->documents;

        if (isset($options['filter'])) {
            $documents = array_values(array_filter($documents, $options['filter']));
        }

        yield from $this->distanceCalculator->calculate($documents, $query->getVector(), $options['maxItems'] ?? null);
    }

    /**
     * @param array{maxItems?: positive-int, filter?: callable(VectorDocument): bool} $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryText(TextQuery $query, array $options): iterable
    {
        $documents = array_filter($this->documents, static function (VectorDocument $doc) use ($query) {
            $text = strtolower($doc->getMetadata()->getText() ?? '');

            // OR logic: match if ANY of the search texts is found
            foreach ($query->getTexts() as $searchText) {
                if (str_contains($text, strtolower($searchText))) {
                    return true;
                }
            }

            return false;
        });

        if (isset($options['filter'])) {
            $documents = array_values(array_filter($documents, $options['filter']));
        }

        $maxItems = $options['maxItems'] ?? null;
        yield from null !== $maxItems ? \array_slice($documents, 0, $maxItems) : $documents;
    }

    /**
     * @param array{maxItems?: positive-int, filter?: callable(VectorDocument): bool} $options
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

        yield from $mergedResults;
    }
}
