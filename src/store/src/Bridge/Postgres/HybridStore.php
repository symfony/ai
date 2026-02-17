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

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Bridge\Postgres\TextSearch\PostgresTextSearchStrategy;
use Symfony\AI\Store\Bridge\Postgres\TextSearch\TextSearchStrategyInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * Hybrid Search Store for PostgreSQL combining vector similarity and full-text search.
 *
 * Uses Reciprocal Rank Fusion (RRF) to combine multiple search signals:
 * - Vector similarity (pgvector)
 * - Full-text search (configurable: native PostgreSQL or BM25)
 * - Fuzzy matching (pg_trgm) for typo tolerance
 *
 * @see https://supabase.com/docs/guides/ai/hybrid-search
 *
 * @author Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 */
final class HybridStore implements ManagedStoreInterface, StoreInterface
{
    /**
     * @param string                      $vectorFieldName    Name of the vector field
     * @param string                      $contentFieldName   Name of the text field for FTS
     * @param float                       $semanticRatio      Ratio between semantic and keyword search (0.0 to 1.0)
     * @param Distance                    $distance           Distance metric for vector similarity
     * @param string                      $language           PostgreSQL text search configuration
     * @param TextSearchStrategyInterface $textSearchStrategy Text search strategy (defaults to native PostgreSQL)
     * @param ReciprocalRankFusion        $rrf                RRF calculator (defaults to k=60, normalized)
     * @param float|null                  $defaultMaxScore    Default max distance for vector search
     * @param float|null                  $defaultMinScore    Default min RRF score threshold
     * @param float                       $fuzzyThreshold     Minimum word_similarity threshold for fuzzy matching
     * @param float                       $fuzzyWeight        Weight of fuzzy matching (0.0 to 1.0, disabled by default)
     */
    public function __construct(
        private readonly \PDO $connection,
        private readonly string $tableName,
        private readonly string $vectorFieldName = 'embedding',
        private readonly string $contentFieldName = 'content',
        float $semanticRatio = 1.0,
        private readonly Distance $distance = Distance::L2,
        private readonly string $language = 'simple',
        private readonly TextSearchStrategyInterface $textSearchStrategy = new PostgresTextSearchStrategy(),
        private readonly ReciprocalRankFusion $rrf = new ReciprocalRankFusion(),
        private readonly ?float $defaultMaxScore = null,
        private readonly ?float $defaultMinScore = null,
        private readonly float $fuzzyThreshold = 0.2,
        private readonly float $fuzzyWeight = 0.0,
    ) {
        if ($semanticRatio < 0.0 || $semanticRatio > 1.0) {
            throw new InvalidArgumentException(\sprintf('The semantic ratio must be between 0.0 and 1.0, "%s" given.', $semanticRatio));
        }

        if ($fuzzyWeight < 0.0 || $fuzzyWeight > 1.0) {
            throw new InvalidArgumentException(\sprintf('The fuzzy weight must be between 0.0 and 1.0, "%s" given.', $fuzzyWeight));
        }
    }

    /**
     * @param array{vector_type?: string, vector_size?: positive-int, index_method?: string, index_opclass?: string} $options
     */
    public function setup(array $options = []): void
    {
        // Enable pgvector extension
        $this->connection->exec('CREATE EXTENSION IF NOT EXISTS vector');

        // Enable pg_trgm extension for fuzzy matching
        $this->connection->exec('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Create main table
        $this->connection->exec(
            \sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    id UUID PRIMARY KEY,
                    metadata JSONB,
                    %s TEXT NOT NULL,
                    %s %s(%d) NOT NULL
                )',
                $this->tableName,
                $this->contentFieldName,
                $this->vectorFieldName,
                $options['vector_type'] ?? 'vector',
                $options['vector_size'] ?? 1536,
            ),
        );

        // Add search_text field for fuzzy matching
        $this->connection->exec(
            \sprintf(
                'ALTER TABLE %s ADD COLUMN IF NOT EXISTS search_text TEXT',
                $this->tableName,
            ),
        );

        // Create trigger for search_text auto-update
        $this->createSearchTextTrigger();

        // Create vector index
        $this->connection->exec(
            \sprintf(
                'CREATE INDEX IF NOT EXISTS %s_%s_idx ON %s USING %s (%s %s)',
                $this->tableName,
                $this->vectorFieldName,
                $this->tableName,
                $options['index_method'] ?? 'hnsw',
                $this->vectorFieldName,
                $options['index_opclass'] ?? 'vector_cosine_ops',
            ),
        );

        foreach ($this->textSearchStrategy->getSetupSql($this->tableName, $this->contentFieldName, $this->language) as $sql) {
            $this->connection->exec($sql);
        }

        // Create trigram index for fuzzy matching
        $this->connection->exec(
            \sprintf(
                'CREATE INDEX IF NOT EXISTS %s_search_text_trgm_idx ON %s USING gin(search_text gin_trgm_ops)',
                $this->tableName,
                $this->tableName,
            ),
        );
    }

    public function drop(array $options = []): void
    {
        $this->connection->exec(\sprintf('DROP TABLE IF EXISTS %s', $this->tableName));
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

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
            $statement->bindValue(':id', $document->getId());
            $statement->bindValue(':metadata', json_encode($document->getMetadata()->getArrayCopy(), \JSON_THROW_ON_ERROR));
            $statement->bindValue(':content', $document->getMetadata()->getText() ?? '');
            $statement->bindValue(':vector', PgvectorConverter::toPgvector($document->getVector()));

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
            HybridQuery::class,
        ], true);
    }

    /**
     * @param array{
     *   limit?: int,
     *   where?: string,
     *   params?: array<string, mixed>,
     *   maxScore?: float,
     *   minScore?: float,
     *   includeScoreBreakdown?: bool,
     * } $options
     *
     * @return iterable<VectorDocument>
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if ($query instanceof HybridQuery) {
            $vector = $query->getVector();
            $queryText = $query->getText();
            $semanticRatio = $this->validateSemanticRatio($query->getSemanticRatio());
        } elseif ($query instanceof VectorQuery) {
            $vector = $query->getVector();
            $queryText = '';
            $semanticRatio = 1.0;
        } else {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $limit = $options['limit'] ?? 5;

        [$whereClause, $params] = $this->buildWhereClause($vector, $options, $semanticRatio);

        $sql = $this->buildQuery($semanticRatio, $queryText, $whereClause, $limit);

        if ('' !== $queryText && $semanticRatio < 1.0) {
            $params['query'] = $queryText;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute([...$params, ...($options['params'] ?? [])]);

        $documents = $this->processResults(
            $statement->fetchAll(\PDO::FETCH_ASSOC),
            $options['includeScoreBreakdown'] ?? false,
        );

        $minScore = $options['minScore'] ?? $this->defaultMinScore;
        if (null !== $minScore) {
            $documents = array_values(array_filter(
                $documents,
                fn (VectorDocument $doc) => $doc->getScore() >= $minScore
            ));
        }

        yield from $documents;
    }

    /**
     * Get the text search strategy being used.
     */
    public function getTextSearchStrategy(): TextSearchStrategyInterface
    {
        return $this->textSearchStrategy;
    }

    /**
     * Get the RRF calculator being used.
     */
    public function getRrf(): ReciprocalRankFusion
    {
        return $this->rrf;
    }

    private function createSearchTextTrigger(): void
    {
        $this->connection->exec(
            "CREATE OR REPLACE FUNCTION update_search_text()
            RETURNS TRIGGER AS \$\$
            BEGIN
                NEW.search_text := COALESCE(NEW.metadata->>'title', '');
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;"
        );

        $this->connection->exec(
            \sprintf(
                'DROP TRIGGER IF EXISTS trigger_update_search_text ON %s;
                CREATE TRIGGER trigger_update_search_text
                BEFORE INSERT OR UPDATE ON %s
                FOR EACH ROW
                EXECUTE FUNCTION update_search_text();',
                $this->tableName,
                $this->tableName,
            ),
        );
    }

    private function validateSemanticRatio(float $ratio): float
    {
        if ($ratio < 0.0 || $ratio > 1.0) {
            throw new InvalidArgumentException(\sprintf('The semantic ratio must be between 0.0 and 1.0, "%s" given.', $ratio));
        }

        return $ratio;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{string, array<string, mixed>}
     */
    private function buildWhereClause(Vector $vector, array $options, float $semanticRatio): array
    {
        $where = [];
        $params = [];

        $maxScore = $options['maxScore'] ?? $this->defaultMaxScore;

        if ($semanticRatio > 0.0 || null !== $maxScore) {
            $params['embedding'] = PgvectorConverter::toPgvector($vector);
        }

        if (null !== $maxScore) {
            $where[] = \sprintf(
                '(%s %s :embedding) <= :maxScore',
                $this->vectorFieldName,
                $this->distance->getComparisonSign()
            );
            $params['maxScore'] = $maxScore;
        }

        if (isset($options['where']) && '' !== $options['where']) {
            $where[] = '('.$options['where'].')';
        }

        $whereClause = $where ? 'WHERE '.implode(' AND ', $where) : '';

        return [$whereClause, $params];
    }

    private function buildQuery(float $semanticRatio, string $queryText, string $whereClause, int $limit): string
    {
        if (1.0 === $semanticRatio || '' === $queryText) {
            return $this->buildVectorOnlyQuery($whereClause, $limit);
        }

        if (0.0 === $semanticRatio) {
            return $this->buildFtsOnlyQuery($whereClause, $limit);
        }

        return $this->buildHybridQuery($whereClause, $limit, $semanticRatio);
    }

    private function buildVectorOnlyQuery(string $whereClause, int $limit): string
    {
        return \sprintf(
            'SELECT id, %s AS embedding, metadata, (%s %s :embedding) AS score
            FROM %s
            %s
            ORDER BY score ASC
            LIMIT %d',
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
        $ftsCte = $this->textSearchStrategy->buildSearchCte(
            $this->tableName,
            $this->contentFieldName,
            $this->language,
        );
        $cteAlias = $this->textSearchStrategy->getCteAlias();
        $scoreColumn = $this->textSearchStrategy->getScoreColumn();

        return \sprintf(
            'WITH %s
            SELECT id, NULL AS embedding, metadata, %s AS score
            FROM %s
            %s
            ORDER BY %s DESC
            LIMIT %d',
            $ftsCte,
            $scoreColumn,
            $cteAlias,
            $whereClause ? 'WHERE id IN (SELECT id FROM '.$this->tableName.' '.$whereClause.')' : '',
            $scoreColumn,
            $limit,
        );
    }

    private function buildHybridQuery(string $whereClause, int $limit, float $semanticRatio): string
    {
        $ftsCte = $this->textSearchStrategy->buildSearchCte(
            $this->tableName,
            $this->contentFieldName,
            $this->language,
        );
        $ftsAlias = $this->textSearchStrategy->getCteAlias();
        $ftsRankColumn = $this->textSearchStrategy->getRankColumn();
        $ftsScoreColumn = $this->textSearchStrategy->getScoreColumn();
        $ftsNormalizedScore = $this->textSearchStrategy->getNormalizedScoreExpression($ftsScoreColumn);

        // Calculate weights
        $ftsWeight = (1.0 - $semanticRatio) * (1.0 - $this->fuzzyWeight);
        $fuzzyWeightCalculated = (1.0 - $semanticRatio) * $this->fuzzyWeight;

        // Build fuzzy filter
        $fuzzyFilter = $this->buildFuzzyFilter();
        $fuzzyWhereClause = $this->addFilterToWhereClause($whereClause, $fuzzyFilter);

        // Build RRF expressions using the RRF class
        $vectorContribution = $this->rrf->buildSqlExpression(
            'v.rank_ix',
            '(1.0 - LEAST(v.distance / 2.0, 1.0))',
            $semanticRatio,
        );
        $ftsContribution = $this->rrf->buildSqlExpression(
            "b.{$ftsRankColumn}",
            $ftsNormalizedScore,
            $ftsWeight,
        );
        $fuzzyContribution = $this->rrf->buildSqlExpression(
            'fz.rank_ix',
            'fz.fuzzy_similarity',
            $fuzzyWeightCalculated,
        );

        return \sprintf(
            'WITH vector_scores AS (
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
                SELECT
                    COALESCE(v.id, b.id, fz.id) as id,
                    v.embedding,
                    COALESCE(v.metadata, b.metadata, fz.metadata) as metadata,
                    (%s + %s + %s) AS score,
                    v.rank_ix AS vector_rank,
                    b.%s AS fts_rank,
                    v.distance AS vector_distance,
                    b.%s AS fts_score,
                    fz.rank_ix AS fuzzy_rank,
                    fz.fuzzy_similarity AS fuzzy_score,
                    %s AS vector_contribution,
                    %s AS fts_contribution,
                    %s AS fuzzy_contribution
                FROM vector_scores v
                FULL OUTER JOIN %s b ON v.id = b.id
                FULL OUTER JOIN fuzzy_scores fz ON COALESCE(v.id, b.id) = fz.id
                WHERE v.id IS NOT NULL OR b.id IS NOT NULL OR fz.id IS NOT NULL
            )
            SELECT * FROM (
                SELECT DISTINCT ON (metadata->>\'title\') *
                FROM combined_results
                ORDER BY metadata->>\'title\', score DESC
            ) unique_results
            ORDER BY score DESC
            LIMIT %d',
            $this->vectorFieldName,
            $this->vectorFieldName,
            $this->distance->getComparisonSign(),
            $this->vectorFieldName,
            $this->distance->getComparisonSign(),
            $this->tableName,
            $whereClause,
            $ftsCte,
            $this->tableName,
            $fuzzyWhereClause,
            $vectorContribution,
            $ftsContribution,
            $fuzzyContribution,
            $ftsRankColumn,
            $ftsScoreColumn,
            $vectorContribution,
            $ftsContribution,
            $fuzzyContribution,
            $ftsAlias,
            $limit,
        );
    }

    private function buildFuzzyFilter(): string
    {
        return \sprintf(
            'word_similarity(:query, search_text) > %f',
            $this->fuzzyThreshold
        );
    }

    private function addFilterToWhereClause(string $whereClause, string $filter): string
    {
        if ('' === $whereClause) {
            return "WHERE $filter";
        }

        $whereClause = rtrim($whereClause);

        if (str_starts_with($whereClause, 'WHERE ')) {
            return "$whereClause AND $filter";
        }

        return "WHERE $filter AND ".ltrim($whereClause);
    }

    /**
     * @param array<array<string, mixed>> $results
     *
     * @return VectorDocument[]
     */
    private function processResults(array $results, bool $includeBreakdown): array
    {
        $documents = [];

        foreach ($results as $result) {
            $metadata = new Metadata(json_decode($result['metadata'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR));

            if ($includeBreakdown && isset($result['vector_rank'])) {
                $metadata['_score_breakdown'] = $this->buildScoreBreakdown($result);
            }

            // Use NullVector for results without embedding (FTS-only or fuzzy-only matches)
            $vector = null !== $result['embedding']
                ? new Vector(PgvectorConverter::fromPgvector($result['embedding']))
                : new NullVector();

            $score = $result['score'];
            if ($this->rrf->isNormalized()) {
                $score = $this->rrf->normalize($score);
            }

            $documents[] = new VectorDocument(
                id: $result['id'],
                vector: $vector,
                metadata: $metadata,
                score: $score,
            );
        }

        return $documents;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function buildScoreBreakdown(array $result): array
    {
        $breakdown = [
            'vector_rank' => $result['vector_rank'],
            'fts_rank' => $result['fts_rank'],
            'vector_distance' => $result['vector_distance'],
            'fts_score' => $result['fts_score'],
            'vector_contribution' => $result['vector_contribution'],
            'fts_contribution' => $result['fts_contribution'],
        ];

        if (isset($result['fuzzy_rank'])) {
            $breakdown['fuzzy_rank'] = $result['fuzzy_rank'];
            $breakdown['fuzzy_score'] = $result['fuzzy_score'];
            $breakdown['fuzzy_contribution'] = $result['fuzzy_contribution'];
        }

        if ($this->rrf->isNormalized()) {
            $breakdown['vector_contribution'] = $this->rrf->normalize($breakdown['vector_contribution']);
            $breakdown['fts_contribution'] = $this->rrf->normalize($breakdown['fts_contribution']);

            if (isset($breakdown['fuzzy_contribution'])) {
                $breakdown['fuzzy_contribution'] = $this->rrf->normalize($breakdown['fuzzy_contribution']);
            }
        }

        return $breakdown;
    }
}
