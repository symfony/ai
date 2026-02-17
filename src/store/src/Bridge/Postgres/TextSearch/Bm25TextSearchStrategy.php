<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Postgres\TextSearch;

/**
 * BM25 full-text search strategy using plpgsql_bm25 extension.
 *
 * BM25 (Best Matching 25) is a ranking function used by search engines
 * to estimate the relevance of documents to a given search query.
 * It's generally more accurate than PostgreSQL's native ts_rank_cd.
 *
 * Requirements:
 * - plpgsql_bm25 extension must be installed
 *
 * @see https://github.com/pgsql-bm25/plpgsql_bm25
 *
 * @author Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 */
final class Bm25TextSearchStrategy implements TextSearchStrategyInterface
{
    private const CTE_ALIAS = 'bm25_with_metadata';
    private const RANK_COLUMN = 'bm25_rank';
    private const SCORE_COLUMN = 'bm25_score';

    /**
     * @param string $bm25Language BM25 language code ('en', 'fr', 'es', etc.)
     * @param int    $topK         Number of results to retrieve from BM25 (default: 100)
     */
    public function __construct(
        private readonly string $bm25Language = 'en',
        private readonly int $topK = 100,
    ) {
    }

    public function getSetupSql(string $tableName, string $contentFieldName, string $language): array
    {
        return [];
    }

    public function buildSearchCte(
        string $tableName,
        string $contentFieldName,
        string $language,
        string $queryParam = ':query',
    ): string {
        return \sprintf(
            "bm25_search AS (
                SELECT
                    bm25.doc,
                    bm25.score as %s,
                    ROW_NUMBER() OVER (ORDER BY bm25.score DESC) as %s
                FROM bm25topk(
                    '%s',
                    '%s',
                    %s,
                    %d,
                    '',
                    '%s'
                ) AS bm25
            ),
            %s AS (
                SELECT DISTINCT ON (m.id)
                    m.id,
                    m.metadata,
                    m.%s,
                    b.%s,
                    b.%s
                FROM bm25_search b
                INNER JOIN %s m ON m.%s = b.doc
                ORDER BY m.id, b.%s
            )",
            self::SCORE_COLUMN,
            self::RANK_COLUMN,
            $tableName,
            $contentFieldName,
            $queryParam,
            $this->topK,
            $this->bm25Language,
            self::CTE_ALIAS,
            $contentFieldName,
            self::SCORE_COLUMN,
            self::RANK_COLUMN,
            $tableName,
            $contentFieldName,
            self::RANK_COLUMN,
        );
    }

    public function getCteAlias(): string
    {
        return self::CTE_ALIAS;
    }

    public function getRankColumn(): string
    {
        return self::RANK_COLUMN;
    }

    public function getScoreColumn(): string
    {
        return self::SCORE_COLUMN;
    }

    public function getNormalizedScoreExpression(string $scoreColumn): string
    {
        return \sprintf('LEAST(%s / 10.0, 1.0)', $scoreColumn);
    }

    public function getRequiredExtensions(): array
    {
        return ['plpgsql_bm25'];
    }

    public function isAvailable(\PDO $connection): bool
    {
        try {
            $stmt = $connection->query(
                "SELECT 1 FROM pg_proc WHERE proname = 'bm25topk' LIMIT 1"
            );

            return false !== $stmt->fetchColumn();
        } catch (\PDOException) {
            return false;
        }
    }
}
