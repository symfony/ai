<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres;

use Doctrine\DBAL\Connection;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
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
 * Requires PostgreSQL with pgvector extension.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @see https://github.com/pgvector/pgvector
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly \PDO $connection,
        private readonly string $tableName,
        private readonly string $vectorFieldName = 'embedding',
        private readonly Distance $distance = Distance::L2,
    ) {
    }

    /**
     * @param array{vector_type?: string, vector_size?: positive-int, index_method?: string, index_opclass?: string} $options
     *
     * Good configuration $options are:
     * - For Mistral: ['vector_size' => 1024]
     * - For Gemini: ['vector_type' => 'halfvec', 'vector_size' => 3072, 'index_method' => 'hnsw', 'index_opclass' => 'halfvec_cosine_ops']
     */
    public function setup(array $options = []): void
    {
        $this->connection->exec('CREATE EXTENSION IF NOT EXISTS vector');

        $this->connection->exec(
            \sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    id UUID PRIMARY KEY,
                    metadata JSONB,
                    %s %s(%d) NOT NULL
                )',
                $this->tableName,
                $this->vectorFieldName,
                $options['vector_type'] ?? 'vector',
                $options['vector_size'] ?? 1536,
            ),
        );
        $this->connection->exec(
            \sprintf(
                'CREATE INDEX IF NOT EXISTS %s_%s_idx ON %s USING %s (%s %s)',
                $this->tableName,
                $this->vectorFieldName,
                $this->tableName,
                $options['index_method'] ?? 'ivfflat',
                $this->vectorFieldName,
                $options['index_opclass'] ?? 'vector_cosine_ops',
            ),
        );
    }

    public function drop(array $options = []): void
    {
        $this->connection->exec(\sprintf('DROP TABLE IF EXISTS %s', $this->tableName));
    }

    public static function fromPdo(
        \PDO $connection,
        string $tableName,
        string $vectorFieldName = 'embedding',
        Distance $distance = Distance::L2,
    ): self {
        return new self($connection, $tableName, $vectorFieldName, $distance);
    }

    public static function fromDbal(
        Connection $connection,
        string $tableName,
        string $vectorFieldName = 'embedding',
        Distance $distance = Distance::L2,
    ): self {
        $pdo = $connection->getNativeConnection();

        if (!$pdo instanceof \PDO) {
            throw new InvalidArgumentException('Only DBAL connections using PDO driver are supported.');
        }

        return self::fromPdo($pdo, $tableName, $vectorFieldName, $distance);
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $statement = $this->connection->prepare(
            \sprintf(
                'INSERT INTO %1$s (id, metadata, %2$s)
                VALUES (:id, :metadata, :vector)
                ON CONFLICT (id) DO UPDATE SET metadata = EXCLUDED.metadata, %2$s = EXCLUDED.%2$s',
                $this->tableName,
                $this->vectorFieldName,
            ),
        );

        foreach ($documents as $document) {
            $statement->bindValue(':id', $document->getId());
            $statement->bindValue(':metadata', json_encode($document->getMetadata()->getArrayCopy(), \JSON_THROW_ON_ERROR));
            $statement->bindValue(':vector', $this->toPgvector($document->getVector()));

            $statement->execute();
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

        $placeholders = implode(',', array_fill(0, \count($ids), '?'));
        $sql = \sprintf('DELETE FROM %s WHERE id IN (%s)', $this->tableName, $placeholders);

        $statement = $this->connection->prepare($sql);

        foreach ($ids as $index => $id) {
            $statement->bindValue($index + 1, $id);
        }

        $statement->execute();
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
     * @param array{limit?: positive-int, maxScore?: float} $options
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
     * @param array{limit?: positive-int, maxScore?: float, where?: string, params?: array<string, mixed>} $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryVector(VectorQuery $query, array $options): iterable
    {
        $whereClauses = [];
        $params = ['embedding' => $this->toPgvector($query->getVector())];

        if (isset($options['maxScore'])) {
            $whereClauses[] = \sprintf('(%s %s :embedding) <= :maxScore', $this->vectorFieldName, $this->distance->getComparisonSign());
            $params['maxScore'] = $options['maxScore'];
        }

        if (isset($options['where'])) {
            // Only wrap in parentheses if combining with other conditions
            $whereClauses[] = [] !== $whereClauses ? '('.$options['where'].')' : $options['where'];
        }

        if (isset($options['params'])) {
            $params = array_merge($params, $options['params']);
        }

        $where = [] !== $whereClauses ? 'WHERE '.implode(' AND ', $whereClauses) : '';

        $sql = \sprintf(<<<SQL
            SELECT id, %s AS embedding, metadata, (%s %s :embedding) AS score
            FROM %s
            %s
            ORDER BY score ASC
            LIMIT %d
            SQL,
            $this->vectorFieldName,
            $this->vectorFieldName,
            $this->distance->getComparisonSign(),
            $this->tableName,
            $where,
            $options['limit'] ?? 5,
        );

        $statement = $this->connection->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue(':'.$key, $value);
        }

        $statement->execute();

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            yield new VectorDocument(
                id: $result['id'],
                vector: new Vector($this->fromPgvector($result['embedding'])),
                metadata: new Metadata(json_decode($result['metadata'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR)),
                score: $result['score'],
            );
        }
    }

    /**
     * @param array{limit?: positive-int} $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryText(TextQuery $query, array $options): iterable
    {
        $texts = $query->getTexts();
        $tsqueryParts = [];
        $params = [];

        // Build OR-combined tsquery for multiple texts
        foreach ($texts as $i => $text) {
            $paramName = 'search_text_'.$i;
            $tsqueryParts[] = "plainto_tsquery('english', :{$paramName})";
            $params[$paramName] = $text;
        }

        $tsqueryExpression = implode(' || ', $tsqueryParts); // OR operator in PostgreSQL
        $tsvectorExpression = "to_tsvector('english', metadata->>'_text')";

        $sql = \sprintf(<<<SQL
            SELECT id, %s AS embedding, metadata,
                   ts_rank(%s, %s) AS score
            FROM %s
            WHERE %s @@ (%s)
            ORDER BY score DESC
            LIMIT %d
            SQL,
            $this->vectorFieldName,
            $tsvectorExpression,
            $tsqueryExpression,
            $this->tableName,
            $tsvectorExpression,
            $tsqueryExpression,
            $options['limit'] ?? 5,
        );

        $statement = $this->connection->prepare($sql);
        foreach ($params as $paramName => $value) {
            $statement->bindValue(':'.$paramName, $value);
        }

        $statement->execute();

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            yield new VectorDocument(
                id: $result['id'],
                vector: new Vector($this->fromPgvector($result['embedding'])),
                metadata: new Metadata(json_decode($result['metadata'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR)),
                score: $result['score'],
            );
        }
    }

    /**
     * @param array{limit?: positive-int, maxScore?: float} $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryHybrid(HybridQuery $query, array $options): iterable
    {
        $texts = $query->getTexts();
        $tsqueryParts = [];
        $params = [
            'embedding' => $this->toPgvector($query->getVector()),
            'semantic_ratio' => $query->getSemanticRatio(),
            'keyword_ratio' => $query->getKeywordRatio(),
        ];

        // Build OR-combined tsquery for multiple texts
        foreach ($texts as $i => $text) {
            $paramName = 'search_text_'.$i;
            $tsqueryParts[] = "plainto_tsquery('english', :{$paramName})";
            $params[$paramName] = $text;
        }

        $tsqueryExpression = '('.implode(' || ', $tsqueryParts).')'; // OR operator in PostgreSQL
        $tsvectorExpression = "to_tsvector('english', metadata->>'_text')";

        $where = \sprintf('WHERE %s @@ %s', $tsvectorExpression, $tsqueryExpression);

        if (isset($options['maxScore'])) {
            $where .= \sprintf(' AND ((:semantic_ratio * (1 - (%s %s :embedding))) + (:keyword_ratio * ts_rank(%s, %s))) >= :maxScore',
                $this->vectorFieldName,
                $this->distance->getComparisonSign(),
                $tsvectorExpression,
                $tsqueryExpression
            );
            $params['maxScore'] = $options['maxScore'];
        }

        $sql = \sprintf(<<<SQL
            SELECT id, %s AS embedding, metadata,
                   ((:semantic_ratio * (1 - (%s %s :embedding))) +
                    (:keyword_ratio * ts_rank(%s, %s))) AS score
            FROM %s
            %s
            ORDER BY score DESC
            LIMIT %d
            SQL,
            $this->vectorFieldName,
            $this->vectorFieldName,
            $this->distance->getComparisonSign(),
            $tsvectorExpression,
            $tsqueryExpression,
            $this->tableName,
            $where,
            $options['limit'] ?? 5,
        );

        $statement = $this->connection->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue(':'.$key, $value);
        }

        $statement->execute();

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            yield new VectorDocument(
                id: $result['id'],
                vector: new Vector($this->fromPgvector($result['embedding'])),
                metadata: new Metadata(json_decode($result['metadata'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR)),
                score: $result['score'],
            );
        }
    }

    private function toPgvector(VectorInterface $vector): string
    {
        return '['.implode(',', $vector->getData()).']';
    }

    /**
     * @return float[]
     */
    private function fromPgvector(string $vector): array
    {
        return json_decode($vector, true, 512, \JSON_THROW_ON_ERROR);
    }
}
