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
 * Combines pgvector (semantic) + BM25 (keyword) using RRF.
 *
 * Uses Reciprocal Rank Fusion (RRF) to combine vector similarity and BM25 search,
 * following the same approach as Supabase hybrid search implementation.
 *
 * Requirements:
 * - PostgreSQL with pgvector extension
 * - plpgsql_bm25 extension for BM25 search
 * - A 'content' text field for BM25 search
 *
 * @see https://supabase.com/docs/guides/ai/hybrid-search
 *
 * @author Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 */
final class HybridStore implements ManagedStoreInterface, StoreInterface
{
    /**
     * @param string     $vectorFieldName        Name of the vector field
     * @param string     $contentFieldName       Name of the text field for FTS
     * @param float      $semanticRatio          Ratio between semantic (vector) and keyword (FTS) search (0.0 to 1.0)
     *                                           - 0.0 = 100% keyword search (FTS)
     *                                           - 0.5 = balanced hybrid search
     *                                           - 1.0 = 100% semantic search (vector only) - default
     * @param Distance   $distance               Distance metric for vector similarity
     * @param string     $language               PostgreSQL text search configuration (default: 'simple')
     *                                           - 'simple': Works for ALL languages, no stemming (recommended for multilingual content)
     *                                           - 'english', 'french', 'spanish', etc.: Language-specific stemming/stopwords
     * @param int        $rrfK                   RRF (Reciprocal Rank Fusion) constant for hybrid search (default: 60)
     *                                           Higher values = more equal weighting between results
     * @param float|null $defaultMaxScore        Default maximum distance threshold for vector search (default: null = no filter)
     *                                           Only applies to pure vector search (semanticRatio = 1.0)
     *                                           Prevents returning irrelevant results with high distance scores
     *                                           Example: 0.8 means only return documents with distance < 0.8
     * @param float|null $defaultMinScore        Default minimum RRF score threshold (default: null = no filter)
     *                                           Filters out results with RRF score below this threshold
     *                                           Useful to prevent irrelevant results when FTS returns no matches
     *                                           Example: 0.01 means only return documents with RRF score >= 0.01
     * @param bool       $normalizeScores        Normalize scores to 0-100 range for better readability (default: true)
     *                                           When true, scores are multiplied by 100
     *                                           Example: 0.0164 becomes 1.64 (more intuitive)
     * @param float      $fuzzyPrimaryThreshold  Primary threshold for fuzzy matching (default: 0.25)
     *                                           Higher threshold = fewer false positives, stricter matching
     *                                           Recommended: 0.25 for good balance
     * @param float      $fuzzySecondaryThreshold Secondary threshold for fuzzy matching (default: 0.2)
     *                                           Used with fuzzyStrictThreshold for double validation
     *                                           Catches more typos but requires strict check
     * @param float      $fuzzyStrictThreshold   Strict similarity threshold for double validation (default: 0.15)
     *                                           Used with fuzzySecondaryThreshold to eliminate false positives
     *                                           Ensures word_similarity > 0.2 has minimum similarity > 0.15
     * @param float      $fuzzyWeight            Weight of fuzzy matching in hybrid search (default: 0.5)
     *                                           - 0.0 = fuzzy disabled
     *                                           - 0.5 = equal weight with FTS (recommended)
     *                                           - 1.0 = fuzzy only (not recommended)
     * @param array      $searchableAttributes   Searchable attributes with field-specific boosting (similar to Meilisearch)
     *                                           Format: ['field_name' => ['boost' => 2.0, 'metadata_key' => 'title'], ...]
     *                                           Each attribute creates a separate tsvector column extracted from metadata
     *                                           Example: ['title' => ['boost' => 2.0, 'metadata_key' => 'title'],
     *                                                     'overview' => ['boost' => 0.5, 'metadata_key' => 'overview']]
     * @param string     $bm25Language           BM25 language code (default: 'en')
     *                                           BM25 uses short codes: 'en', 'fr', 'es', 'de', etc.
     *                                           Separate from $language which is for PostgreSQL FTS
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
        private ?float $defaultMinScore = null,
        private bool $normalizeScores = true,
        private float $fuzzyPrimaryThreshold = 0.25,
        private float $fuzzySecondaryThreshold = 0.2,
        private float $fuzzyStrictThreshold = 0.15,
        private float $fuzzyWeight = 0.5,
        private array $searchableAttributes = [],
        private string $bm25Language = 'en',
    ) {
        if ($semanticRatio < 0.0 || $semanticRatio > 1.0) {
            throw new InvalidArgumentException(\sprintf('The semantic ratio must be between 0.0 and 1.0, "%s" given.', $semanticRatio));
        }
        if ($fuzzyWeight < 0.0 || $fuzzyWeight > 1.0) {
            throw new InvalidArgumentException(\sprintf('The fuzzy weight must be between 0.0 and 1.0, "%s" given.', $fuzzyWeight));
        }
    }

    public function setup(array $options = []): void
    {
        // Enable pgvector extension
        $this->connection->exec('CREATE EXTENSION IF NOT EXISTS vector');

        // Enable pg_trgm extension for fuzzy matching (typo tolerance)
        $this->connection->exec('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Build tsvector columns based on searchable_attributes configuration
        $tsvectorColumns = '';
        if (!empty($this->searchableAttributes)) {
            // Create separate tsvector column for each searchable attribute
            foreach ($this->searchableAttributes as $fieldName => $config) {
                $metadataKey = $config['metadata_key'];
                $tsvectorColumns .= \sprintf(
                    ",\n                    %s_tsv tsvector GENERATED ALWAYS AS (to_tsvector('%s', COALESCE(metadata->>'%s', ''))) STORED",
                    $fieldName,
                    $this->language,
                    $metadataKey
                );
            }
        } else {
            // Backward compatibility: use single content_tsv if no searchable_attributes configured
            $tsvectorColumns = \sprintf(
                ",\n                    content_tsv tsvector GENERATED ALWAYS AS (to_tsvector('%s', %s)) STORED",
                $this->language,
                $this->contentFieldName
            );
        }

        // Create table with vector field, content field for FTS, and tsvector field(s)
        $this->connection->exec(
            \sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    id UUID PRIMARY KEY,
                    metadata JSONB,
                    %s TEXT NOT NULL,
                    %s %s(%d) NOT NULL%s
                )',
                $this->tableName,
                $this->contentFieldName,
                $this->vectorFieldName,
                $options['vector_type'] ?? 'vector',
                $options['vector_size'] ?? 1536,
                $tsvectorColumns,
            ),
        );

        // Add search_text field for optimized fuzzy matching
        // This field contains only title + relevant metadata for better fuzzy precision
        $this->connection->exec(
            \sprintf(
                'ALTER TABLE %s ADD COLUMN IF NOT EXISTS search_text TEXT',
                $this->tableName,
            ),
        );

        // Create function to auto-update search_text from metadata
        $this->connection->exec(
            "CREATE OR REPLACE FUNCTION update_search_text()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.search_text := COALESCE(NEW.metadata->>'title', '');
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;"
        );

        // Create trigger to auto-update search_text on insert/update
        $this->connection->exec(
            \sprintf(
                "DROP TRIGGER IF EXISTS trigger_update_search_text ON %s;
                CREATE TRIGGER trigger_update_search_text
                BEFORE INSERT OR UPDATE ON %s
                FOR EACH ROW
                EXECUTE FUNCTION update_search_text();",
                $this->tableName,
                $this->tableName,
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
        if (!empty($this->searchableAttributes)) {
            // Create GIN index for each searchable attribute tsvector
            foreach ($this->searchableAttributes as $fieldName => $config) {
                $this->connection->exec(
                    \sprintf(
                        'CREATE INDEX IF NOT EXISTS %s_%s_tsv_idx ON %s USING gin(%s_tsv)',
                        $this->tableName,
                        $fieldName,
                        $this->tableName,
                        $fieldName,
                    ),
                );
            }
        } else {
            // Backward compatibility: create single content_tsv index
            $this->connection->exec(
                \sprintf(
                    'CREATE INDEX IF NOT EXISTS %s_content_tsv_idx ON %s USING gin(content_tsv)',
                    $this->tableName,
                    $this->tableName,
                ),
            );
        }

        // Create trigram index on search_text for optimized fuzzy matching
        $this->connection->exec(
            \sprintf(
                'CREATE INDEX IF NOT EXISTS %s_search_text_trgm_idx ON %s USING gin(search_text gin_trgm_ops)',
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
     *   maxScore?: float,
     *   minScore?: float,
     *   includeScoreBreakdown?: bool,
     *   boostFields?: array<string, array{min?: float, max?: float, boost: float}>
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
            // DEBUG: Log query vector
            $vecArray = $vector->getData();
            $first5 = array_slice($vecArray, 0, 5);
            file_put_contents('/tmp/hybrid_debug.log', sprintf("[%s] Query: %s | Vector dims: %d | First 5: [%s]\n", date('Y-m-d H:i:s'), $queryText, count($vecArray), implode(', ', array_map(fn($v) => sprintf('%.4f', $v), $first5))), FILE_APPEND);
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

        // DEBUG: Log the SQL query and parameters
        file_put_contents('/tmp/hybrid_debug.log', sprintf("[%s] SQL Query:\n%s\n\nParameters:\n%s\n\n", date('Y-m-d H:i:s'), $sql, print_r(array_merge($params, $options['params'] ?? []), true)), FILE_APPEND);

        $statement = $this->connection->prepare($sql);
        $statement->execute([...$params, ...($options['params'] ?? [])]);

        $includeBreakdown = $options['includeScoreBreakdown'] ?? false;
        $documents = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            $metadata = new Metadata(json_decode($result['metadata'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR));

            // Add score breakdown to metadata if requested
            if ($includeBreakdown && isset($result['vector_rank'])) {
                $metadata['_score_breakdown'] = [
                    'vector_rank' => $result['vector_rank'],
                    'fts_rank' => $result['fts_rank'],
                    'vector_distance' => $result['vector_distance'],
                    'fts_score' => $result['fts_score'],
                    'vector_contribution' => $result['vector_contribution'],
                    'fts_contribution' => $result['fts_contribution'],
                ];

                // Add fuzzy matching info if available
                if (isset($result['fuzzy_rank'])) {
                    $metadata['_score_breakdown']['fuzzy_rank'] = $result['fuzzy_rank'];
                    $metadata['_score_breakdown']['fuzzy_score'] = $result['fuzzy_score'];
                    $metadata['_score_breakdown']['fuzzy_contribution'] = $result['fuzzy_contribution'];
                }
            }

            // Handle cases where embedding might be NULL (fuzzy-only or FTS-only matches)
            $vectorData = $result['embedding'] !== null
                ? new Vector($this->fromPgvector($result['embedding']))
                : new Vector([0.0]); // Placeholder vector for non-semantic matches

            $documents[] = new VectorDocument(
                id: Uuid::fromString($result['id']),
                vector: $vectorData,
                metadata: $metadata,
                score: $result['score'],
            );
        }

        // Normalize scores to 0-100 range for better readability (if enabled)
        if ($this->normalizeScores) {
            // Calculate theoretical maximum RRF score: 1/(k+1)
            // Normalize to 0-100 by dividing by max and multiplying by 100
            $maxScore = 1.0 / ($this->rrfK + 1);
            $documents = array_map(function(VectorDocument $doc) use ($maxScore, $includeBreakdown) {
                $metadata = $doc->metadata;

                // Also normalize breakdown scores if they exist
                if ($includeBreakdown && isset($metadata['_score_breakdown'])) {
                    $breakdown = $metadata['_score_breakdown'];
                    $metadata['_score_breakdown'] = [
                        'vector_rank' => $breakdown['vector_rank'],
                        'fts_rank' => $breakdown['fts_rank'],
                        'vector_distance' => $breakdown['vector_distance'],
                        'fts_score' => $breakdown['fts_score'],
                        'vector_contribution' => ($breakdown['vector_contribution'] / $maxScore) * 100,
                        'fts_contribution' => ($breakdown['fts_contribution'] / $maxScore) * 100,
                    ];

                    // Add normalized fuzzy scores if available
                    if (isset($breakdown['fuzzy_rank'])) {
                        $metadata['_score_breakdown']['fuzzy_rank'] = $breakdown['fuzzy_rank'];
                        $metadata['_score_breakdown']['fuzzy_score'] = $breakdown['fuzzy_score'];
                        $metadata['_score_breakdown']['fuzzy_contribution'] = ($breakdown['fuzzy_contribution'] / $maxScore) * 100;
                    }
                }

                return new VectorDocument(
                    id: $doc->id,
                    vector: $doc->vector,
                    metadata: $metadata,
                    score: ($doc->score / $maxScore) * 100
                );
            }, $documents);
        }

        // Apply metadata-based boosting (if configured)
        // Boost scores based on metadata field values (e.g., popularity, ratings)
        $boostFields = $options['boostFields'] ?? [];
        if (!empty($boostFields)) {
            $documents = array_map(function(VectorDocument $doc) use ($boostFields) {
                $metadata = $doc->metadata;
                $score = $doc->score;
                $appliedBoosts = [];

                foreach ($boostFields as $field => $boostConfig) {
                    // Skip if metadata doesn't have this field
                    if (!isset($metadata[$field])) {
                        continue;
                    }

                    $value = $metadata[$field];
                    $boost = $boostConfig['boost'] ?? 0.0;

                    // Check min/max conditions
                    $shouldBoost = true;
                    if (isset($boostConfig['min']) && $value < $boostConfig['min']) {
                        $shouldBoost = false;
                    }
                    if (isset($boostConfig['max']) && $value > $boostConfig['max']) {
                        $shouldBoost = false;
                    }

                    // Apply boost multiplier if conditions are met
                    if ($shouldBoost && $boost !== 0.0) {
                        $score *= (1.0 + $boost);
                        $appliedBoosts[$field] = [
                            'value' => $value,
                            'boost' => $boost,
                            'multiplier' => (1.0 + $boost),
                        ];
                    }
                }

                // Add boost information to metadata if any boosts were applied
                if (!empty($appliedBoosts)) {
                    $metadata['_applied_boosts'] = $appliedBoosts;
                }

                return new VectorDocument(
                    id: $doc->id,
                    vector: $doc->vector,
                    metadata: $metadata,
                    score: $score
                );
            }, $documents);

            // Re-sort by boosted scores (descending)
            usort($documents, fn(VectorDocument $a, VectorDocument $b) => $b->score <=> $a->score);
        }

        // Filter results by minimum score threshold (if configured)
        // Note: minScore should be in the same scale as the scores (0-100 if normalized)
        $minScore = $options['minScore'] ?? $this->defaultMinScore;
        if (null !== $minScore) {
            $documents = array_values(array_filter($documents, fn(VectorDocument $doc) => $doc->score >= $minScore));
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

    /**
     * Build BM25 search CTE with DISTINCT ON fix for duplicate titles.
     * Replaces FTS rank expression to use plpgsql_bm25 instead of ts_rank_cd.
     *
     * @return string BM25 CTE SQL with deduplication fix
     */
    private function buildBm25Cte(): string
    {
        return \sprintf(
            '
            bm25_search AS (
                SELECT
                    SUBSTRING(bm25.doc FROM \'title: ([^\n]+)\') as extracted_title,
                    bm25.doc,
                    bm25.score as bm25_score,
                    ROW_NUMBER() OVER (ORDER BY bm25.score DESC) as bm25_rank
                FROM bm25topk(
                    \'%s\',
                    \'%s\',
                    :query,
                    100,
                    \'\',
                    \'%s\'
                ) AS bm25
            ),
            bm25_with_metadata AS (
                SELECT DISTINCT ON (b.bm25_rank)
                    m.id,
                    m.metadata,
                    m.%s,
                    b.bm25_score,
                    b.bm25_rank
                FROM bm25_search b
                INNER JOIN %s m ON (m.metadata->>\'title\') = b.extracted_title
                ORDER BY b.bm25_rank, m.id
            )',
            $this->tableName,
            $this->contentFieldName,
            $this->bm25Language,
            $this->contentFieldName,
            $this->tableName
        );
    }

    private function buildFtsOnlyQuery(string $whereClause, int $limit): string
    {
        // BM25-only search (no vector)
        $bm25Cte = $this->buildBm25Cte();

        return \sprintf(<<<SQL
            WITH %s
            SELECT id, NULL AS embedding, metadata,
                   bm25_score AS score
            FROM bm25_with_metadata
            %s
            ORDER BY bm25_score DESC
            LIMIT %d
            SQL,
            $bm25Cte,
            $whereClause ? 'WHERE id IN (SELECT id FROM ' . $this->tableName . ' ' . $whereClause . ')' : '',
            $limit,
        );
    }

    private function buildHybridQuery(string $whereClause, int $limit, float $semanticRatio): string
    {
        // Use BM25 CTE with DISTINCT ON fix for duplicate titles
        $bm25Cte = $this->buildBm25Cte();

        // Add fuzzy filter for the fuzzy_scores CTE using word_similarity on search_text
        // word_similarity() compares query with individual words, much better for typos
        // Hybrid threshold: Configurable thresholds to balance recall and precision
        // - Primary threshold ($fuzzyPrimaryThreshold) for high-quality matches
        // - Secondary + strict thresholds for catching more typos with double validation
        $fuzzyFilter = \sprintf(
            '(
                word_similarity(:query, search_text) > %f
                OR (
                    word_similarity(:query, search_text) > %f
                    AND similarity(:query, search_text) > %f
                )
            )',
            $this->fuzzyPrimaryThreshold,
            $this->fuzzySecondaryThreshold,
            $this->fuzzyStrictThreshold
        );
        $fuzzyWhereClause = $this->addFilterToWhereClause($whereClause, $fuzzyFilter);

        // Calculate weights for BM25 and Fuzzy (both share the non-semantic portion)
        // Weights are configurable to allow tuning for different use cases
        $bm25Weight = (1.0 - $semanticRatio) * (1.0 - $this->fuzzyWeight);
        $fuzzyWeightCalculated = (1.0 - $semanticRatio) * $this->fuzzyWeight;

        // Enhanced RRF: Combines vector, BM25, and fuzzy matching
        // Formula: (1/(k + rank)) * normalized_score * weight
        // BM25 with DISTINCT ON fix eliminates duplicate titles
        // Fuzzy matching uses word_similarity on search_text for optimal typo tolerance
        // Final DISTINCT ON (id) ensures no duplicates in combined results
        return \sprintf(<<<SQL
            WITH vector_scores AS (
                SELECT id, %s AS embedding, metadata,
                       (%s %s :embedding) AS distance,
                       ROW_NUMBER() OVER (ORDER BY %s %s :embedding) AS rank_ix
                FROM %s
                %s
            ),
            %s,
            fuzzy_scores AS (
                SELECT id, metadata,
                       word_similarity(:query, search_text) AS fuzzy_similarity,
                       ROW_NUMBER() OVER (ORDER BY word_similarity(:query, search_text) DESC) AS rank_ix
                FROM %s
                %s
            ),
            combined_results AS (
                SELECT COALESCE(v.id, b.id, fz.id) as id, v.embedding, COALESCE(v.metadata, b.metadata, fz.metadata) as metadata,
                       (
                           COALESCE(1.0 / (%d + v.rank_ix) * (1.0 - LEAST(v.distance / 2.0, 1.0)), 0.0) * %f +
                           COALESCE(1.0 / (%d + b.bm25_rank) * LEAST(b.bm25_score / 10.0, 1.0), 0.0) * %f +
                           COALESCE(1.0 / (%d + fz.rank_ix) * fz.fuzzy_similarity, 0.0) * %f
                       ) AS score,
                       v.rank_ix AS vector_rank,
                       b.bm25_rank AS fts_rank,
                       v.distance AS vector_distance,
                       b.bm25_score AS fts_score,
                       fz.rank_ix AS fuzzy_rank,
                       fz.fuzzy_similarity AS fuzzy_score,
                       COALESCE(1.0 / (%d + v.rank_ix) * (1.0 - LEAST(v.distance / 2.0, 1.0)), 0.0) * %f AS vector_contribution,
                       COALESCE(1.0 / (%d + b.bm25_rank) * LEAST(b.bm25_score / 10.0, 1.0), 0.0) * %f AS fts_contribution,
                       COALESCE(1.0 / (%d + fz.rank_ix) * fz.fuzzy_similarity, 0.0) * %f AS fuzzy_contribution
                FROM vector_scores v
                FULL OUTER JOIN bm25_with_metadata b ON v.id = b.id
                FULL OUTER JOIN fuzzy_scores fz ON COALESCE(v.id, b.id) = fz.id
                WHERE v.id IS NOT NULL OR b.id IS NOT NULL OR fz.id IS NOT NULL
            )
            SELECT * FROM (
                SELECT DISTINCT ON (metadata->>'title') *
                FROM combined_results
                ORDER BY metadata->>'title', score DESC
            ) unique_results
            ORDER BY score DESC
            LIMIT %d
            SQL,
            $this->vectorFieldName,
            $this->vectorFieldName,
            $this->distance->getComparisonSign(),
            $this->vectorFieldName,
            $this->distance->getComparisonSign(),
            $this->tableName,
            $whereClause,
            $bm25Cte,
            $this->tableName,
            $fuzzyWhereClause,
            $this->rrfK,
            $semanticRatio,
            $this->rrfK,
            $bm25Weight,
            $this->rrfK,
            $fuzzyWeightCalculated,
            $this->rrfK,
            $semanticRatio,
            $this->rrfK,
            $bm25Weight,
            $this->rrfK,
            $fuzzyWeightCalculated,
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
