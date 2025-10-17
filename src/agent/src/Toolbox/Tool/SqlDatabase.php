<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

// Note: Requires doctrine/dbal package
// use Doctrine\DBAL\Connection;
// use Doctrine\DBAL\DriverManager;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('sql_db_query', 'Tool for executing SQL queries against a database')]
#[AsTool('sql_db_schema', 'Tool for getting database schema information', method: 'getSchema')]
#[AsTool('sql_db_list_tables', 'Tool for listing all tables in the database', method: 'listTables')]
final readonly class SqlDatabase
{
    public function __construct(
        private mixed $connection, // PDO or Doctrine DBAL Connection
        private int $sampleRowsLimit = 3,
    ) {
    }

    /**
     * Execute a SQL query against the database.
     *
     * @param string $query A detailed and correct SQL query
     *
     * @return array<int, array<string, mixed>>|string
     */
    public function __invoke(string $query): array|string
    {
        try {
            // Basic query validation
            if (!$this->isValidQuery($query)) {
                return 'Error: Invalid or potentially dangerous query detected. Only SELECT queries are allowed.';
            }

            $result = $this->connection->executeQuery($query);
            $rows = $result->fetchAllAssociative();

            return $rows;
        } catch (\Exception $e) {
            return 'Error executing query: '.$e->getMessage();
        }
    }

    /**
     * Get schema information for specified tables.
     *
     * @param string $tableNames Comma-separated list of table names
     */
    public function getSchema(string $tableNames): string
    {
        try {
            $tables = array_map('trim', explode(',', $tableNames));
            $schema = [];

            foreach ($tables as $tableName) {
                $tableInfo = $this->getTableInfo($tableName);
                if ($tableInfo) {
                    $schema[] = $tableInfo;
                }
            }

            return implode("\n\n", $schema);
        } catch (\Exception $e) {
            return 'Error getting schema: '.$e->getMessage();
        }
    }

    /**
     * List all tables in the database.
     *
     * @return array<int, string>
     */
    public function listTables(): array
    {
        try {
            $sql = match ($this->connection->getDatabasePlatform()->getName()) {
                'mysql' => 'SHOW TABLES',
                'postgresql' => 'SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\'',
                'sqlite' => 'SELECT name FROM sqlite_master WHERE type = \'table\'',
                default => 'SELECT table_name FROM information_schema.tables',
            };

            $result = $this->connection->executeQuery($sql);
            $tables = $result->fetchFirstColumn();

            return $tables;
        } catch (\Exception $e) {
            return ['Error: '.$e->getMessage()];
        }
    }

    /**
     * Create a new SQL Database tool instance from PDO connection.
     */
    public static function fromPdo(\PDO $pdo): self
    {
        return new self($pdo);
    }

    /**
     * Get detailed information about a table.
     */
    private function getTableInfo(string $tableName): ?string
    {
        try {
            // Get table schema
            $schema = $this->getTableSchema($tableName);

            // Get sample rows
            $sampleRows = $this->getSampleRows($tableName);

            // Get row count
            $rowCount = $this->getRowCount($tableName);

            $info = "Table: {$tableName}\n";
            $info .= "Row count: {$rowCount}\n\n";
            $info .= "Schema:\n{$schema}\n\n";

            if (!empty($sampleRows)) {
                $info .= "Sample rows:\n";
                foreach ($sampleRows as $row) {
                    $info .= json_encode($row, \JSON_PRETTY_PRINT)."\n";
                }
            }

            return $info;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get table schema information.
     */
    private function getTableSchema(string $tableName): string
    {
        try {
            $sql = match ($this->connection->getDatabasePlatform()->getName()) {
                'mysql' => "DESCRIBE `{$tableName}`",
                'postgresql' => "SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = '{$tableName}' ORDER BY ordinal_position",
                'sqlite' => "PRAGMA table_info(`{$tableName}`)",
                default => "SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = '{$tableName}' ORDER BY ordinal_position",
            };

            $result = $this->connection->executeQuery($sql);
            $columns = $result->fetchAllAssociative();

            $schema = '';
            foreach ($columns as $column) {
                $schema .= json_encode($column, \JSON_PRETTY_PRINT)."\n";
            }

            return $schema;
        } catch (\Exception $e) {
            return 'Error getting schema: '.$e->getMessage();
        }
    }

    /**
     * Get sample rows from a table.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSampleRows(string $tableName): array
    {
        try {
            $sql = "SELECT * FROM `{$tableName}` LIMIT {$this->sampleRowsLimit}";
            $result = $this->connection->executeQuery($sql);

            return $result->fetchAllAssociative();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get row count for a table.
     */
    private function getRowCount(string $tableName): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM `{$tableName}`";
            $result = $this->connection->executeQuery($sql);

            return (int) $result->fetchOne();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Validate SQL query for safety.
     */
    private function isValidQuery(string $query): bool
    {
        $query = trim($query);

        // Only allow SELECT queries
        if (!preg_match('/^\s*SELECT\s+/i', $query)) {
            return false;
        }

        // Check for potentially dangerous keywords
        $dangerousKeywords = [
            'DROP', 'DELETE', 'INSERT', 'UPDATE', 'ALTER', 'CREATE', 'TRUNCATE',
            'EXEC', 'EXECUTE', 'UNION', '--', '/*', '*/', ';', 'xp_', 'sp_',
        ];

        $upperQuery = strtoupper($query);
        foreach ($dangerousKeywords as $keyword) {
            if (str_contains($upperQuery, $keyword)) {
                return false;
            }
        }

        return true;
    }
}
