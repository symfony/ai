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
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface, ResetInterface
{
    /**
     * @var VectorDocumentInterface[]
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

    public function add(VectorDocumentInterface|array $documents): void
    {
        if ($documents instanceof VectorDocumentInterface) {
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

        $this->documents = array_values(array_filter($this->documents, static function (VectorDocumentInterface $document) use ($ids): bool {
            return !\in_array((string) $document->getId(), $ids, true);
        }));
    }

    public function clear(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->documents = [];
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
     *     limit?: positive-int,
     *     filter?: callable(VectorDocumentInterface): bool
     * } $options If limit is provided, only the top N results will be returned.
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

    public function reset(): void
    {
        $this->drop();
    }

    public function count(): int
    {
        return \count($this->documents);
    }

    /**
     * @param array{limit?: positive-int, filter?: callable(VectorDocumentInterface): bool} $options
     *
     * @return iterable<VectorDocumentInterface>
     */
    private function queryVector(VectorQuery $query, array $options): iterable
    {
        $documents = $this->documents;

        if (isset($options['filter'])) {
            $documents = array_values(array_filter($documents, $options['filter']));
        }

        yield from $this->distanceCalculator->calculate($documents, $query->getVector(), $options['limit'] ?? null);
    }

    /**
     * @param array{limit?: positive-int, filter?: callable(VectorDocumentInterface): bool} $options
     *
     * @return iterable<VectorDocumentInterface>
     */
    private function queryText(TextQuery $query, array $options): iterable
    {
        $documents = array_filter($this->documents, static function (VectorDocumentInterface $doc) use ($query) {
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

        $limit = $options['limit'] ?? null;
        yield from null !== $limit ? \array_slice($documents, 0, $limit) : $documents;
    }

    /**
     * @param array{limit?: positive-int, filter?: callable(VectorDocumentInterface): bool} $options
     *
     * @return iterable<VectorDocumentInterface>
     */
    private function queryHybrid(HybridQuery $query, array $options): iterable
    {
        $texts = array_map('strtolower', $query->getTexts());
        $semanticRatio = $query->getSemanticRatio();

        $vectorResults = iterator_to_array($this->queryVector(
            new VectorQuery($query->getVector()),
            array_diff_key($options, ['limit' => true]),
        ), false);

        $distances = array_map(static fn (VectorDocumentInterface $doc): float => $doc->getScore() ?? 0.0, $vectorResults);
        $minDistance = [] !== $distances ? min($distances) : 0.0;
        $distanceRange = [] !== $distances ? max($distances) - $minDistance : 0.0;

        $mergedResults = [];
        foreach ($vectorResults as $doc) {
            $docText = strtolower($doc->getMetadata()->getText() ?? '');
            $matchedTexts = 0;
            foreach ($texts as $text) {
                if (str_contains($docText, $text)) {
                    ++$matchedTexts;
                }
            }

            if (0.0 === $semanticRatio && 0 === $matchedTexts) {
                continue;
            }

            $vectorDistance = $distanceRange > 0.0 ? (($doc->getScore() ?? 0.0) - $minDistance) / $distanceRange : 0.0;
            $textDistance = [] !== $texts ? 1.0 - $matchedTexts / \count($texts) : 1.0;

            $mergedResults[] = $doc->withScore($semanticRatio * $vectorDistance + $query->getKeywordRatio() * $textDistance);
        }

        usort($mergedResults, static fn (VectorDocumentInterface $a, VectorDocumentInterface $b): int => $a->getScore() <=> $b->getScore());

        $limit = $options['limit'] ?? null;
        yield from null !== $limit ? \array_slice($mergedResults, 0, $limit) : $mergedResults;
    }
}
