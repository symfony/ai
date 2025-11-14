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
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Hybrid Search Store for PostgreSQL/Supabase
 * Combines pgvector (semantic) + PostgreSQL Full-Text Search (ts_rank_cd) using RRF.
 *
 * Uses Reciprocal Rank Fusion (RRF) to combine vector similarity and full-text search,
 * following the same approach as Supabase hybrid search implementation.
 *
 * Requirements:
 * - PostgreSQL with pgvector extension
 * - A 'content' text field for full-text search
 *
 * @see https://supabase.com/docs/guides/ai/hybrid-search
 *
 * @author Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 */
final class HybridStore implements ManagedStoreInterface, StoreInterface
{
    /**
     * @param string     $vectorFieldName  Name of the vector field
     * @param string     $contentFieldName Name of the text field for FTS
     * @param float      $semanticRatio    Ratio between semantic (vector) and keyword (FTS) search (0.0 to 1.0)
     *                                     - 0.0 = 100% keyword search (FTS)
     *                                     - 0.5 = balanced hybrid search
     *                                     - 1.0 = 100% semantic search (vector only) - default
     * @param Distance   $distance         Distance metric for vector similarity
     * @param string     $language         PostgreSQL text search configuration (default: 'simple')
     *                                     - 'simple': Works for ALL languages, no stemming (recommended for multilingual content)
     *                                     - 'english', 'french', 'spanish', etc.: Language-specific stemming/stopwords
     * @param int        $rrfK             RRF (Reciprocal Rank Fusion) constant for hybrid search (default: 60)
     *                                     Higher values = more equal weighting between results
     * @param float|null $defaultMaxScore  Default maximum distance threshold for vector search (default: null = no filter)
     *                                     Only applies to pure vector search (semanticRatio = 1.0)
     *                                     Prevents returning irrelevant results with high distance scores
     *                                     Example: 0.8 means only return documents with distance < 0.8
     */
    public function __construct(
        private \PDO $connection,
        private string $tableName,
        private string $vectorFieldName = 'embedding',
        private string $contentFieldName = 'content',
        private float $semanticRatio = 1.0,
        private Distance $distance = Distance::L2,
        private string $language = 'simple',
        private int $rrfK = 60,
        private ?float $defaultMaxScore = null,
    ) {
        if ($semanticRatio < 0.0 || $semanticRatio > 1.0) {
            throw new InvalidArgumentException(\sprintf('The semantic ratio must be between 0.0 and 1.0, "%s" given.', $semanticRatio));
        }
    }

    public function setup(array $options = []): void
    {
        // Enable pgvector extension
        $this->connection->exec('CREATE EXTENSION IF NOT EXISTS vector');

        // Create table with vector field, content field for FTS, and tsvector field
        $this->connection->exec(
            \sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    id UUID PRIMARY KEY,
                    metadata JSONB,
                    %s TEXT NOT NULL,
                    %s %s(%d) NOT NULL,
                    content_tsv tsvector GENERATED ALWAYS AS (to_tsvector(\'%s\', %s)) STORED
                )',
                $this->tableName,
                $this->contentFieldName,
                $this->vectorFieldName,
                $options['vector_type'] ?? 'vector',
                $options['vector_size'] ?? 1536,
                $this->language,
                $this->contentFieldName,
            ),
        );

        // Create vector index
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

        // Create GIN index for full-text search
        $this->connection->exec(
            \sprintf(
                'CREATE INDEX IF NOT EXISTS %s_content_tsv_idx ON %s USING gin(content_tsv)',
                $this->tableName,
                $this->tableName,
            ),
        );
    }

    public function drop(): void
    {
        $this->connection->exec(\sprintf('DROP TABLE IF EXISTS %s', $this->tableName));
    }

    public function add(VectorDocument ...$documents): void
    {
        $statement = $this->connection->prepare(
            \sprintf(
                'INSERT INTO %1$s (id, metadata, %2$s, %3$s)
                VALUES (:id, :metadata, :content, :vector)
                ON CONFLICT (id) DO UPDATE SET
                    metadata = EXCLUDED.metadata,
                    %2$s = EXCLUDED.%2$s,
                    %3$s = EXCLUDED.%3$s',
                $this->tableName,
                $this->contentFieldName,
                $this->vectorFieldName,
            ),
        );

        foreach ($documents as $document) {
            $operation = [
                'id' => $document->id->toRfc4122(),
                'metadata' => json_encode($document->metadata->getArrayCopy(), \JSON_THROW_ON_ERROR),
                'content' => $document->metadata->getText() ?? '',
                'vector' => $this->toPgvector($document->vector),
            ];

            $statement->execute($operation);
        }
    }

    /**
     * Hybrid search combining vector similarity and full-text search.
     *
     * @param array{
     *   q?: string,
     *   semanticRatio?: float,
     *   limit?: int,
     *   where?: string,
     *   params?: array<string, mixed>,
     *   maxScore?: float
     * } $options
     */
    public function query(Vector $vector, array $options = []): array
    {
        $semanticRatio = $options['semanticRatio'] ?? $this->semanticRatio;

        if ($semanticRatio < 0.0 || $semanticRatio > 1.0) {
            throw new InvalidArgumentException(\sprintf('The semantic ratio must be between 0.0 and 1.0, "%s" given.', $semanticRatio));
        }

        $queryText = $options['q'] ?? '';
        $limit = $options['limit'] ?? 5;

        // Build WHERE clause
        $where = [];
        $params = [];

        // Use maxScore from options, or defaultMaxScore if configured
        $maxScore = $options['maxScore'] ?? $this->defaultMaxScore;

        // Ensure embedding param is set if maxScore is used (regardless of semanticRatio)
        if ($semanticRatio > 0.0 || null !== $maxScore) {
            $params['embedding'] = $this->toPgvector($vector);
        }

        if (null !== $maxScore) {
            $where[] = "({$this->vectorFieldName} {$this->distance->getComparisonSign()} :embedding) <= :maxScore";
            $params['maxScore'] = $maxScore;
        }

        if (isset($options['where']) && '' !== $options['where']) {
            $where[] = '('.$options['where'].')';
        }

        $whereClause = $where ? 'WHERE '.implode(' AND ', $where) : '';

        // Choose query strategy based on semanticRatio and query text
        if (1.0 === $semanticRatio || '' === $queryText) {
            // Pure vector search
            $sql = $this->buildVectorOnlyQuery($whereClause, $limit);
        } elseif (0.0 === $semanticRatio) {
            // Pure full-text search
            $sql = $this->buildFtsOnlyQuery($whereClause, $limit);
            $params['query'] = $queryText;
        } else {
            // Hybrid search with weighted combination
            $sql = $this->buildHybridQuery($whereClause, $limit, $semanticRatio);
            $params['query'] = $queryText;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute([...$params, ...($options['params'] ?? [])]);

        $documents = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            $documents[] = new VectorDocument(
                id: Uuid::fromString($result['id']),
                vector: new Vector($this->fromPgvector($result['embedding'])),
                metadata: new Metadata(json_decode($result['metadata'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR)),
                score: $result['score'],
            );
        }

        return $documents;
    }

    private function buildVectorOnlyQuery(string $whereClause, int $limit): string
    {
        return \sprintf(<<<SQL
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
            $whereClause,
            $limit,
        );
    }

    private function buildFtsOnlyQuery(string $whereClause, int $limit): string
    {
        // Add FTS match filter to ensure only relevant documents are returned
        $ftsFilter = \sprintf("content_tsv @@ websearch_to_tsquery('%s', :query)", $this->language);
        $whereClause = $this->addFilterToWhereClause($whereClause, $ftsFilter);

        return \sprintf(<<<SQL
            SELECT id, %s AS embedding, metadata,
                   (1.0 / (1.0 + ts_rank_cd(content_tsv, websearch_to_tsquery('%s', :query)))) AS score
            FROM %s
            %s
            ORDER BY score ASC
            LIMIT %d
            SQL,
            $this->vectorFieldName,
            $this->language,
            $this->tableName,
            $whereClause,
            $limit,
        );
    }

    private function buildHybridQuery(string $whereClause, int $limit, float $semanticRatio): string
    {
        // Add FTS filter for the fts_scores CTE
        $ftsFilter = \sprintf("content_tsv @@ websearch_to_tsquery('%s', :query)", $this->language);
        $ftsWhereClause = $this->addFilterToWhereClause($whereClause, $ftsFilter);

        // RRF (Reciprocal Rank Fusion) - Same approach as Supabase
        // Formula: COALESCE(1.0 / (k + rank), 0.0) * weight
        // Lower score is better (like distance)
        return \sprintf(<<<SQL
            WITH vector_scores AS (
                SELECT id, %s AS embedding, metadata,
                       ROW_NUMBER() OVER (ORDER BY %s %s :embedding) AS rank_ix
                FROM %s
                %s
            ),
            fts_scores AS (
                SELECT id,
                       ROW_NUMBER() OVER (ORDER BY ts_rank_cd(content_tsv, websearch_to_tsquery('%s', :query)) DESC) AS rank_ix
                FROM %s
                %s
            )
            SELECT v.id, v.embedding, v.metadata,
                   (
                       COALESCE(1.0 / (%d + v.rank_ix), 0.0) * %f +
                       COALESCE(1.0 / (%d + f.rank_ix), 0.0) * %f
                   ) AS score
            FROM vector_scores v
            FULL OUTER JOIN fts_scores f ON v.id = f.id
            WHERE v.id IS NOT NULL OR f.id IS NOT NULL
            ORDER BY score DESC
            LIMIT %d
            SQL,
            $this->vectorFieldName,
            $this->vectorFieldName,
            $this->distance->getComparisonSign(),
            $this->tableName,
            $whereClause,
            $this->language,
            $this->tableName,
            $ftsWhereClause,
            $this->rrfK,
            $semanticRatio,
            $this->rrfK,
            1.0 - $semanticRatio,
            $limit,
        );
    }

    /**
     * Adds a filter condition to an existing WHERE clause using AND logic.
     *
     * @param string $whereClause Existing WHERE clause (may be empty or start with 'WHERE ')
     * @param string $filter      Filter condition to add (without 'WHERE ')
     *
     * @return string Combined WHERE clause
     */
    private function addFilterToWhereClause(string $whereClause, string $filter): string
    {
        if ('' === $whereClause) {
            return "WHERE $filter";
        }

        $whereClause = rtrim($whereClause);

        if (str_starts_with($whereClause, 'WHERE ')) {
            return "$whereClause AND $filter";
        }

        // Unexpected format, prepend WHERE
        return "WHERE $filter AND ".ltrim($whereClause);
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
