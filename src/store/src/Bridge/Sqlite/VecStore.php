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
 * Requires SQLite with sqlite-vec extension.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @see https://github.com/asg017/sqlite-vec
 */
final class VecStore implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly \PDO $connection,
        private readonly string $tableName,
        private readonly Distance $distance = Distance::Cosine,
        private readonly int $vectorDimension = 1536,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->connection->exec(\sprintf(
            'CREATE VIRTUAL TABLE IF NOT EXISTS %s USING vec0(id TEXT PRIMARY KEY, embedding float[%d] distance_metric=%s, +metadata TEXT)',
            $this->tableName,
            $this->vectorDimension,
            $this->distance->value,
        ));

        $this->connection->exec(\sprintf(
            'CREATE VIRTUAL TABLE IF NOT EXISTS %s_fts USING fts5(id UNINDEXED, content)',
            $this->tableName,
        ));
    }

    public static function fromPdo(
        \PDO $connection,
        string $tableName,
        Distance $distance = Distance::Cosine,
        int $vectorDimension = 1536,
    ): self {
        return new self($connection, $tableName, $distance, $vectorDimension);
    }

    /**
     * @throws InvalidArgumentException When DBAL connection doesn't use PDO driver
     */
    public static function fromDbal(
        Connection $connection,
        string $tableName,
        Distance $distance = Distance::Cosine,
        int $vectorDimension = 1536,
    ): self {
        $pdo = $connection->getNativeConnection();

        if (!$pdo instanceof \PDO) {
            throw new InvalidArgumentException('Only DBAL connections using PDO driver are supported.');
        }

        return self::fromPdo($pdo, $tableName, $distance, $vectorDimension);
    }

    public static function isExtensionAvailable(\PDO $connection): bool
    {
        try {
            $result = $connection->query('SELECT vec_version()');

            return false !== $result->fetchColumn();
        } catch (\PDOException) {
            return false;
        }
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
            $deleteStatement = $this->connection->prepare(\sprintf(
                'DELETE FROM %s WHERE id = :id',
                $this->tableName,
            ));

            $insertStatement = $this->connection->prepare(\sprintf(
                'INSERT INTO %s (id, embedding, metadata) VALUES (:id, :embedding, :metadata)',
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

                // vec0 does not support INSERT OR REPLACE, so delete first
                $deleteStatement->bindValue(':id', $id);
                $deleteStatement->execute();

                $insertStatement->bindValue(':id', $id);
                $insertStatement->bindValue(':embedding', json_encode($document->getVector()->getData()));
                $insertStatement->bindValue(':metadata', json_encode($metadata));
                $insertStatement->execute();

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
            $statement = $this->connection->prepare(\sprintf(
                'DELETE FROM %s WHERE id = :id',
                $this->tableName,
            ));

            $ftsStatement = $this->connection->prepare(\sprintf(
                'DELETE FROM %s_fts WHERE id = :id',
                $this->tableName,
            ));

            foreach ($ids as $id) {
                $statement->bindValue(':id', $id);
                $statement->execute();

                $ftsStatement->bindValue(':id', $id);
                $ftsStatement->execute();
            }

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
        $maxItems = $options['maxItems'] ?? 5;
        $hasFilter = isset($options['filter']);

        // Over-fetch when filtering to compensate for post-query filtering
        $fetchLimit = $hasFilter ? $maxItems * 3 : $maxItems;

        $statement = $this->connection->prepare(\sprintf(
            'SELECT id, distance, metadata FROM %s WHERE embedding MATCH :embedding AND k = :k ORDER BY distance',
            $this->tableName,
        ));

        $statement->bindValue(':embedding', json_encode($query->getVector()->getData()));
        $statement->bindValue(':k', $fetchLimit, \PDO::PARAM_INT);
        $statement->execute();

        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ([] === $results) {
            return;
        }

        $count = 0;

        foreach ($results as $row) {
            if ($count >= $maxItems) {
                break;
            }

            $document = new VectorDocument(
                id: $row['id'],
                vector: $query->getVector(),
                metadata: new Metadata(json_decode($row['metadata'] ?? '{}', true)),
                score: (float) $row['distance'],
            );

            if ($hasFilter && !$options['filter']($document)) {
                continue;
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
    private function queryText(TextQuery $query, array $options): iterable
    {
        $searchTerms = $query->getTexts();
        $ftsQuery = implode(' OR ', array_map(static fn (string $term): string => '"'.$term.'"', $searchTerms));

        $statement = $this->connection->prepare(\sprintf(
            'SELECT id, rank FROM %s_fts WHERE %s_fts MATCH :query ORDER BY rank',
            $this->tableName,
            $this->tableName,
        ));
        $statement->bindValue(':query', $ftsQuery);
        $statement->execute();

        $ftsResults = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if ([] === $ftsResults) {
            return;
        }

        $matchedIds = array_column($ftsResults, 'id');
        $documents = $this->loadDocumentsByIds($matchedIds);

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
     * @param array{
     *     maxItems?: positive-int,
     *     filter?: callable(VectorDocument): bool,
     * } $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryHybrid(HybridQuery $query, array $options): iterable
    {
        $maxItems = $options['maxItems'] ?? 5;

        // Remove maxItems from sub-queries to get full ranked lists for RRF
        $subOptions = $options;
        unset($subOptions['maxItems']);

        $vectorResults = iterator_to_array($this->queryVector(
            new VectorQuery($query->getVector()),
            $subOptions,
        ));

        $textResults = iterator_to_array($this->queryText(
            new TextQuery($query->getTexts()),
            $subOptions,
        ));

        // Reciprocal Rank Fusion: score(d) = semanticRatio * 1/(k + vectorRank) + keywordRatio * 1/(k + textRank)
        $k = 60;
        $semanticRatio = $query->getSemanticRatio();
        $keywordRatio = $query->getKeywordRatio();

        /** @var array<string, float> $scores */
        $scores = [];
        /** @var array<string, VectorDocument> $documents */
        $documents = [];

        foreach ($vectorResults as $rank => $doc) {
            $id = (string) $doc->getId();
            $scores[$id] = $semanticRatio * (1.0 / ($k + $rank + 1));
            $documents[$id] = $doc;
        }

        foreach ($textResults as $rank => $doc) {
            $id = (string) $doc->getId();
            $scores[$id] = ($scores[$id] ?? 0.0) + $keywordRatio * (1.0 / ($k + $rank + 1));
            if (!isset($documents[$id])) {
                $documents[$id] = $doc;
            }
        }

        arsort($scores);

        if (isset($options['filter'])) {
            $filter = $options['filter'];
        }

        $count = 0;

        foreach ($scores as $id => $score) {
            if ($count >= $maxItems) {
                break;
            }

            $doc = $documents[$id];
            $document = new VectorDocument(
                id: $doc->getId(),
                vector: $doc->getVector(),
                metadata: $doc->getMetadata(),
                score: $score,
            );

            if (isset($filter) && !$filter($document)) {
                continue;
            }

            yield $document;
            ++$count;
        }
    }

    /**
     * @param string[] $ids
     *
     * @return array<string, VectorDocument>
     */
    private function loadDocumentsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, \count($ids), '?'));

        $statement = $this->connection->prepare(\sprintf(
            'SELECT id, embedding, metadata FROM %s WHERE id IN (%s)',
            $this->tableName,
            $placeholders,
        ));

        foreach ($ids as $index => $id) {
            $statement->bindValue($index + 1, $id);
        }

        $statement->execute();

        $documents = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $documents[$row['id']] = new VectorDocument(
                id: $row['id'],
                vector: new Vector(array_values(unpack('f*', $row['embedding']))),
                metadata: new Metadata(json_decode($row['metadata'] ?? '{}', true)),
            );
        }

        return $documents;
    }
}
