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

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
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
        private readonly Metric $metric = Metric::L2,
        private readonly string $lang = 'english',
        private readonly int $dimensions = 1536,
        private readonly string $vectorType = 'vector',
        // Unlike ivfflat, hnsw needs no data to build a good index, and supports every distance
        private readonly string $indexMethod = 'hnsw',
        private readonly ?string $indexOpclass = null,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        // Documents are written and read in the "[x,y,z]" format, which other pgvector types reject
        if (!\in_array($this->vectorType, ['halfvec', 'vector'], true)) {
            throw new InvalidArgumentException(\sprintf('The "%s" vector type is not supported, use "vector" or "halfvec".', $this->vectorType));
        }

        $indexOpclass = $this->indexOpclass ?? $this->getIndexOpclass($this->vectorType, $this->indexMethod);

        $this->connection->exec('CREATE EXTENSION IF NOT EXISTS vector');

        $this->connection->exec(
            \sprintf(
                'CREATE TABLE IF NOT EXISTS "%s" (
                    id UUID PRIMARY KEY,
                    metadata JSONB,
                    "%s" %s(%d) NOT NULL
                )',
                $this->tableName,
                $this->vectorFieldName,
                $this->vectorType,
                $this->dimensions,
            ),
        );
        $this->connection->exec(
            \sprintf(
                'CREATE INDEX IF NOT EXISTS "%s_%s_idx" ON "%s" USING %s ("%s" %s)',
                $this->tableName,
                $this->vectorFieldName,
                $this->tableName,
                $this->indexMethod,
                $this->vectorFieldName,
                $indexOpclass,
            ),
        );
    }

    public function drop(array $options = []): void
    {
        $this->connection->exec(\sprintf('DROP TABLE IF EXISTS "%s"', $this->tableName));
    }

    public function count(): int
    {
        $statement = $this->connection->prepare(\sprintf('SELECT COUNT(*) FROM "%s"', $this->tableName));
        $statement->execute();

        return max(0, (int) $statement->fetchColumn());
    }

    public function add(VectorDocumentInterface|array $documents): void
    {
        if ($documents instanceof VectorDocumentInterface) {
            $documents = [$documents];
        }

        $statement = $this->connection->prepare(
            \sprintf(
                'INSERT INTO "%1$s" (id, metadata, "%2$s")
                VALUES (:id, :metadata, :vector)
                ON CONFLICT (id) DO UPDATE SET metadata = EXCLUDED.metadata, "%2$s" = EXCLUDED."%2$s"',
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
        $sql = \sprintf('DELETE FROM "%s" WHERE id IN (%s)', $this->tableName, $placeholders);

        $statement = $this->connection->prepare($sql);

        foreach ($ids as $index => $id) {
            $statement->bindValue($index + 1, $id);
        }

        $statement->execute();
    }

    public function clear(array $options = []): void
    {
        $this->connection->exec(\sprintf('TRUNCATE TABLE "%s"', $this->tableName));
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
     * @return iterable<VectorDocumentInterface>
     */
    private function queryVector(VectorQuery $query, array $options): iterable
    {
        $whereClauses = [];
        $params = ['embedding' => $this->toPgvector($query->getVector())];

        if (isset($options['maxScore'])) {
            $whereClauses[] = \sprintf('("%s" %s :embedding) <= :maxScore', $this->vectorFieldName, $this->metric->getComparisonSign());
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
            SELECT id, "%s" AS embedding, metadata, ("%s" %s :embedding) AS score
            FROM "%s"
            %s
            ORDER BY score ASC
            LIMIT %d
            SQL,
            $this->vectorFieldName,
            $this->vectorFieldName,
            $this->metric->getComparisonSign(),
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
     * @return iterable<VectorDocumentInterface>
     */
    private function queryText(TextQuery $query, array $options): iterable
    {
        $texts = $query->getTexts();
        $tsqueryParts = [];
        $params = [];

        // Build OR-combined tsquery for multiple texts
        foreach ($texts as $i => $text) {
            $paramName = 'search_text_'.$i;
            $tsqueryParts[] = \sprintf("plainto_tsquery('%s', :%s)", $this->lang, $paramName);
            $params[$paramName] = $text;
        }

        $tsqueryExpression = implode(' || ', $tsqueryParts); // OR operator in PostgreSQL
        $tsvectorExpression = \sprintf("to_tsvector('%s', metadata->>'_text')", $this->lang);

        $sql = \sprintf(<<<SQL
            SELECT id, "%s" AS embedding, metadata,
                   ts_rank(%s, %s) AS score
            FROM "%s"
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
     * @return iterable<VectorDocumentInterface>
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
            $tsqueryParts[] = \sprintf("plainto_tsquery('%s', :%s)", $this->lang, $paramName);
            $params[$paramName] = $text;
        }

        $tsqueryExpression = '('.implode(' || ', $tsqueryParts).')'; // OR operator in PostgreSQL
        $tsvectorExpression = \sprintf("to_tsvector('%s', metadata->>'_text')", $this->lang);

        $where = \sprintf('WHERE %s @@ %s', $tsvectorExpression, $tsqueryExpression);

        if (isset($options['maxScore'])) {
            $where .= \sprintf(' AND ((:semantic_ratio * (1 - ("%s" %s :embedding))) + (:keyword_ratio * ts_rank(%s, %s))) >= :maxScore',
                $this->vectorFieldName,
                $this->metric->getComparisonSign(),
                $tsvectorExpression,
                $tsqueryExpression
            );
            $params['maxScore'] = $options['maxScore'];
        }

        $sql = \sprintf(<<<SQL
            SELECT id, "%s" AS embedding, metadata,
                   ((:semantic_ratio * (1 - ("%s" %s :embedding))) +
                    (:keyword_ratio * ts_rank(%s, %s))) AS score
            FROM "%s"
            %s
            ORDER BY score DESC
            LIMIT %d
            SQL,
            $this->vectorFieldName,
            $this->vectorFieldName,
            $this->metric->getComparisonSign(),
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

    private function getIndexOpclass(string $vectorType, string $indexMethod): string
    {
        if ('ivfflat' === $indexMethod && Metric::L1 === $this->metric) {
            throw new InvalidArgumentException('The "ivfflat" index method does not support the L1 distance, use "hnsw".');
        }

        return \sprintf('%s_%s_ops', $vectorType, match ($this->metric) {
            Metric::Cosine => 'cosine',
            Metric::InnerProduct => 'ip',
            Metric::L1 => 'l1',
            Metric::L2 => 'l2',
        });
    }

    private function toPgvector(VectorInterface $vector): string
    {
        return '['.implode(',', $vector->getData()).']';
    }

    /**
     * @return list<float>
     */
    private function fromPgvector(string $vector): array
    {
        return json_decode($vector, true, 512, \JSON_THROW_ON_ERROR);
    }
}
