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
 * PostgreSQL native full-text search strategy using ts_rank_cd.
 *
 * This is the default strategy that works with any PostgreSQL installation
 * without requiring additional extensions.
 *
 * @author Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 */
final class PostgresTextSearchStrategy implements TextSearchStrategyInterface
{
    private const CTE_ALIAS = 'fts_search';
    private const RANK_COLUMN = 'fts_rank';
    private const SCORE_COLUMN = 'fts_score';

    public function getSetupSql(string $tableName, string $contentFieldName, string $language): array
    {
        return [
            // Add tsvector column if not exists
            \sprintf(
                "ALTER TABLE %s ADD COLUMN IF NOT EXISTS content_tsv tsvector
                 GENERATED ALWAYS AS (to_tsvector('%s', %s)) STORED",
                $tableName,
                $language,
                $contentFieldName,
            ),
            // Create GIN index for full-text search
            \sprintf(
                'CREATE INDEX IF NOT EXISTS %s_content_tsv_idx ON %s USING gin(content_tsv)',
                $tableName,
                $tableName,
            ),
        ];
    }

    public function buildSearchCte(
        string $tableName,
        string $contentFieldName,
        string $language,
        string $queryParam = ':query',
    ): string {
        return \sprintf(
            "%s AS (
                SELECT
                    id,
                    metadata,
                    %s,
                    ts_rank_cd(content_tsv, plainto_tsquery('%s', %s)) AS %s,
                    ROW_NUMBER() OVER (
                        ORDER BY ts_rank_cd(content_tsv, plainto_tsquery('%s', %s)) DESC
                    ) AS %s
                FROM %s
                WHERE content_tsv @@ plainto_tsquery('%s', %s)
            )",
            self::CTE_ALIAS,
            $contentFieldName,
            $language,
            $queryParam,
            self::SCORE_COLUMN,
            $language,
            $queryParam,
            self::RANK_COLUMN,
            $tableName,
            $language,
            $queryParam,
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
        // ts_rank_cd returns values typically between 0 and 1, but can exceed 1
        // We cap it at 1.0 for normalization
        return \sprintf('LEAST(%s, 1.0)', $scoreColumn);
    }

    public function getRequiredExtensions(): array
    {
        return []; // No additional extensions required
    }

    public function isAvailable(\PDO $connection): bool
    {
        return true; // Always available in PostgreSQL
    }
}
