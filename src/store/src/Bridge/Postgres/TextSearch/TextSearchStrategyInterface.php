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
 * Strategy interface for full-text search implementations.
 *
 * Allows pluggable FTS backends (PostgreSQL native, BM25, etc.)
 *
 * @author Ahmed EBEN HASSINE <ahmedbhs123@gmail.com>
 */
interface TextSearchStrategyInterface
{
    /**
     * Get the SQL statements needed to set up the text search.
     *
     * @param string $tableName        The table name
     * @param string $contentFieldName The content field name
     * @param string $language         The language configuration
     *
     * @return string[] Array of SQL statements to execute
     */
    public function getSetupSql(string $tableName, string $contentFieldName, string $language): array;

    /**
     * Build the CTE (Common Table Expression) for text search ranking.
     *
     * @param string $tableName        The table name
     * @param string $contentFieldName The content field name
     * @param string $language         The language configuration
     * @param string $queryParam       The parameter name for the query (e.g., ':query')
     *
     * @return string SQL CTE expression
     */
    public function buildSearchCte(
        string $tableName,
        string $contentFieldName,
        string $language,
        string $queryParam = ':query',
    ): string;

    /**
     * Get the name of the CTE that will be used in joins.
     */
    public function getCteAlias(): string;

    /**
     * Get the rank column name from the CTE.
     */
    public function getRankColumn(): string;

    /**
     * Get the score column name from the CTE.
     */
    public function getScoreColumn(): string;

    /**
     * Get the SQL expression to normalize the score to 0-1 range.
     *
     * @param string $scoreColumn The score column name
     */
    public function getNormalizedScoreExpression(string $scoreColumn): string;

    /**
     * Check if this strategy requires external extensions.
     *
     * @return string[] List of required extensions
     */
    public function getRequiredExtensions(): array;

    /**
     * Check if the strategy is available (extensions installed, etc.).
     */
    public function isAvailable(\PDO $connection): bool;
}
