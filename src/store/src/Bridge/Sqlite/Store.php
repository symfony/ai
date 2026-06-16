<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Sqlite;

use Doctrine\DBAL\Connection;
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

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    private const RRF_K = 60;

    public function __construct(
        private readonly \PDO $connection,
        private readonly string $tableName,
        private readonly DistanceCalculator $distanceCalculator = new DistanceCalculator(),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->connection->exec(\sprintf(
            'CREATE TABLE IF NOT EXISTS %s (id TEXT PRIMARY KEY, vector TEXT NOT NULL, metadata TEXT)',
            $this->tableName,
        ));

        $this->connection->exec(\sprintf(
            'CREATE VIRTUAL TABLE IF NOT EXISTS %s_fts USING fts5(id UNINDEXED, content)',
            $this->tableName,
        ));
    }

    public static function fromPdo(\PDO $connection, string $tableName, DistanceCalculator $distanceCalculator = new DistanceCalculator()): self
    {
        return new self($connection, $tableName, $distanceCalculator);
    }

    /**
     * @throws InvalidArgumentException When DBAL connection doesn't use PDO driver
     */
    public static function fromDbal(Connection $connection, string $tableName, DistanceCalculator $distanceCalculator = new DistanceCalculator()): self
    {
        $pdo = $connection->getNativeConnection();

        if (!$pdo instanceof \PDO) {
            throw new InvalidArgumentException('Only DBAL connections using PDO driver are supported.');
        }

        return self::fromPdo($pdo, $tableName, $distanceCalculator);
    }

    public function drop(array $options = []): void
    {
        $this->connection->exec(\sprintf('DROP TABLE IF EXISTS %s_fts', $this->tableName));
        $this->connection->exec(\sprintf('DROP TABLE IF EXISTS %s', $this->tableName));
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(\sprintf(
                'INSERT OR REPLACE INTO %s (id, vector, metadata) VALUES (:id, :vector, :metadata)',
                $this->tableName,
            ));

            $ftsDeleteStatement = $this->connection->prepare(\sprintf(
                'DELETE FROM %s_fts WHERE id = :id',
                $this->tableName,
            ));

            $ftsInsertStatement = $this->connection->prepare(\sprintf(
                'INSERT INTO %s_fts (id, content) VALUES (:id, :content)',
                $this->tableName,
            ));

            foreach ($documents as $document) {
                $id = (string) $document->getId();
                $metadata = $document->getMetadata()->getArrayCopy();

                $statement->bindValue(':id', $id);
                $statement->bindValue(':vector', json_encode($document->getVector()->getData()));
                $statement->bindValue(':metadata', json_encode($metadata));
                $statement->execute();

                $ftsDeleteStatement->bindValue(':id', $id);
                $ftsDeleteStatement->execute();

                $text = $document->getMetadata()->getText();
                if (null !== $text && '' !== $text) {
                    $ftsInsertStatement->bindValue(':id', $id);
                    $ftsInsertStatement->bindValue(':content', $text);
                    $ftsInsertStatement->execute();
                }
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $this->connection->beginTransaction();

        try {
            $placeholders = implode(', ', array_fill(0, \count($ids), '?'));

            $statement = $this->connection->prepare(\sprintf(
                'DELETE FROM %s WHERE id IN (%s)',
                $this->tableName,
                $placeholders,
            ));

            $ftsStatement = $this->connection->prepare(\sprintf(
                'DELETE FROM %s_fts WHERE id IN (%s)',
                $this->tableName,
                $placeholders,
            ));

            foreach ($ids as $index => $id) {
                $statement->bindValue($index + 1, $id);
                $ftsStatement->bindValue($index + 1, $id);
            }

            $statement->execute();
            $ftsStatement->execute();

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    public function supports(string $queryClass): bool
    {
        return \in_array($queryClass, [
            VectorQuery::class,
            TextQuery::class,
            HybridQuery::class,
        ], true);
    }

    public function count(): int
    {
        $statement = $this->connection->query(\sprintf('SELECT COUNT(*) FROM %s', $this->tableName));

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{
     *     maxItems?: positive-int,
     *     filter?: callable(VectorDocument): bool,
     * } $options
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
        $documents = $this->loadAllDocuments();

        if ([] === $documents) {
            return;
        }

        if (isset($options['filter'])) {
            $documents = array_values(array_filter($documents, $options['filter']));
        }

        yield from $this->distanceCalculator->calculate($documents, $query->getVector(), $options['maxItems'] ?? null);
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
        $searchTerms = $query->getTexts();
        $ftsQuery = implode(' OR ', array_map(static fn (string $term): string => '"'.$term.'"', $searchTerms));

        $sql = \sprintf(
            'SELECT id, rank FROM %s_fts WHERE %s_fts MATCH :query ORDER BY rank',
            $this->tableName,
            $this->tableName,
        );

        if (isset($options['maxItems'])) {
            $sql .= \sprintf(' LIMIT %d', $options['maxItems']);
        }

        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':query', $ftsQuery);
        $statement->execute();

        $ftsResults = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ([] === $ftsResults) {
            return;
        }

        $matchedIds = array_column($ftsResults, 'id');
        $placeholders = implode(', ', array_fill(0, \count($matchedIds), '?'));

        $docStatement = $this->connection->prepare(\sprintf(
            'SELECT id, vector, metadata FROM %s WHERE id IN (%s)',
            $this->tableName,
            $placeholders,
        ));

        foreach ($matchedIds as $index => $id) {
            $docStatement->bindValue($index + 1, $id);
        }

        $docStatement->execute();

        $documents = [];
        foreach ($docStatement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $documents[$row['id']] = new VectorDocument(
                id: $row['id'],
                vector: new Vector(json_decode($row['vector'], true)),
                metadata: new Metadata(json_decode($row['metadata'] ?? '{}', true)),
            );
        }

        // Preserve FTS rank ordering
        $orderedDocuments = [];
        foreach ($matchedIds as $id) {
            if (isset($documents[$id])) {
                $orderedDocuments[] = $documents[$id];
            }
        }

        if (isset($options['filter'])) {
            $orderedDocuments = array_values(array_filter($orderedDocuments, $options['filter']));
        }

        $maxItems = $options['maxItems'] ?? null;
        $count = 0;

        foreach ($orderedDocuments as $document) {
            if (null !== $maxItems && $count >= $maxItems) {
                break;
            }

            yield $document;
            ++$count;
        }
    }

    /**
     * Uses Reciprocal Rank Fusion (RRF) to combine vector and text search results.
     *
     * RRF assigns each document a score of `ratio / (k + rank)` from each ranked list
     * it appears in, then sums these scores. Documents appearing in both lists receive
     * higher combined scores than those in only one, solving the null-text-score problem
     * and the no-overlap scenario where vector and text results are entirely disjoint.
     *
     * Each sub-query fetches a candidate pool of `rrfCandidatePoolSize` documents (defaults
     * to `maxItems * 10`, minimum 100). RRF merges and re-ranks the pool, then `maxItems`
     * is applied. Override via `$options['rrfCandidatePoolSize']`.
     *
     * @param array{
     *     maxItems?: positive-int,
     *     rrfCandidatePoolSize?: positive-int,
     *     filter?: callable(VectorDocument): bool,
     * } $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryHybrid(HybridQuery $query, array $options): iterable
    {
        $maxItems = $options['maxItems'] ?? null;

        // Each sub-query fetches a candidate pool larger than maxItems so RRF has
        // enough documents to rank before trimming. Falls back to no limit when
        // maxItems is not set (caller explicitly requested all results).
        $candidatePoolSize = $options['rrfCandidatePoolSize']
            ?? (null !== $maxItems ? max($maxItems * 10, 100) : null);

        $subOptions = $options;
        unset($subOptions['rrfCandidatePoolSize']);
        if (null !== $candidatePoolSize) {
            $subOptions['maxItems'] = $candidatePoolSize;
        } else {
            unset($subOptions['maxItems']);
        }

        $vectorResults = iterator_to_array($this->queryVector(
            new VectorQuery($query->getVector()),
            $subOptions,
        ));

        $textResults = iterator_to_array($this->queryText(
            new TextQuery($query->getTexts()),
            $subOptions,
        ));

        /** @var array<string, array{doc: VectorDocument, score: float}> $scores */
        $scores = [];

        foreach ($vectorResults as $rank => $doc) {
            $id = (string) $doc->getId();
            $scores[$id] = [
                'doc' => $doc,
                'score' => $query->getSemanticRatio() * (1.0 / (self::RRF_K + $rank + 1)),
            ];
        }

        foreach ($textResults as $rank => $doc) {
            $id = (string) $doc->getId();
            $rrfScore = $query->getKeywordRatio() * (1.0 / (self::RRF_K + $rank + 1));
            if (isset($scores[$id])) {
                $scores[$id]['score'] += $rrfScore;
            } else {
                $scores[$id] = ['doc' => $doc, 'score' => $rrfScore];
            }
        }

        usort($scores, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $count = 0;

        foreach ($scores as ['doc' => $doc, 'score' => $score]) {
            if (null !== $maxItems && $count >= $maxItems) {
                break;
            }

            yield new VectorDocument(
                id: $doc->getId(),
                vector: $doc->getVector(),
                metadata: $doc->getMetadata(),
                score: $score,
            );
            ++$count;
        }
    }

    /**
     * @return VectorDocument[]
     */
    private function loadAllDocuments(): array
    {
        $statement = $this->connection->query(\sprintf(
            'SELECT id, vector, metadata FROM %s',
            $this->tableName,
        ));

        $documents = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $documents[] = new VectorDocument(
                id: $row['id'],
                vector: new Vector(json_decode($row['vector'], true)),
                metadata: new Metadata(json_decode($row['metadata'] ?? '{}', true)),
            );
        }

        return $documents;
    }
}
