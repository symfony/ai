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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('spark_sql_execute_query', 'Tool that executes Spark SQL queries')]
#[AsTool('spark_sql_create_table', 'Tool that creates Spark SQL tables', method: 'createTable')]
#[AsTool('spark_sql_insert_data', 'Tool that inserts data into Spark SQL tables', method: 'insertData')]
#[AsTool('spark_sql_describe_table', 'Tool that describes Spark SQL table schema', method: 'describeTable')]
#[AsTool('spark_sql_show_tables', 'Tool that shows Spark SQL tables', method: 'showTables')]
#[AsTool('spark_sql_show_databases', 'Tool that shows Spark SQL databases', method: 'showDatabases')]
#[AsTool('spark_sql_optimize_table', 'Tool that optimizes Spark SQL tables', method: 'optimizeTable')]
#[AsTool('spark_sql_analyze_table', 'Tool that analyzes Spark SQL tables', method: 'analyzeTable')]
final readonly class SparkSql
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $host = 'localhost',
        private int $port = 10000,
        private string $database = 'default',
        private string $username = '',
        #[\SensitiveParameter] private string $password = '',
        private array $options = [],
    ) {
    }

    /**
     * Execute Spark SQL query.
     *
     * @param string               $query   SQL query to execute
     * @param array<string, mixed> $params  Query parameters
     * @param int                  $timeout Query timeout in seconds
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     rowCount: int,
     *     executionTime: float,
     *     queryId: string,
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        array $params = [],
        int $timeout = 300,
    ): array {
        try {
            $startTime = microtime(true);

            // This is a simplified implementation
            // In reality, you would use a proper Spark SQL connector like JDBC or REST API
            $command = $this->buildSparkSqlCommand($query, $params, $timeout);
            $output = $this->executeCommand($command);

            $executionTime = microtime(true) - $startTime;

            // Parse output (simplified)
            $results = $this->parseSparkSqlOutput($output);

            return [
                'success' => true,
                'results' => $results['data'],
                'columns' => $results['columns'],
                'rowCount' => \count($results['data']),
                'executionTime' => $executionTime,
                'queryId' => $results['queryId'],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'columns' => [],
                'rowCount' => 0,
                'executionTime' => 0.0,
                'queryId' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Spark SQL table.
     *
     * @param string $tableName Table name
     * @param array<int, array{
     *     name: string,
     *     type: string,
     *     nullable: bool,
     * }> $columns Table columns
     * @param array<string, mixed> $options  Table options (format, location, etc.)
     * @param string               $database Database name
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     database: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function createTable(
        string $tableName,
        array $columns,
        array $options = [],
        string $database = '',
    ): array {
        try {
            $database = $database ?: $this->database;
            if (!$database) {
                throw new \InvalidArgumentException('Database is required.');
            }

            $columnDefs = [];
            foreach ($columns as $column) {
                $nullable = $column['nullable'] ? '' : ' NOT NULL';
                $columnDefs[] = "{$column['name']} {$column['type']}{$nullable}";
            }

            $cql = "CREATE TABLE {$database}.{$tableName} (".implode(', ', $columnDefs).')';

            // Add table options
            if (!empty($options)) {
                $optionPairs = [];
                foreach ($options as $key => $value) {
                    $optionPairs[] = "'{$key}' = '{$value}'";
                }
                $cql .= ' USING '.$options['format'] ?? 'parquet';
                $cql .= ' OPTIONS ('.implode(', ', $optionPairs).')';
            }

            $result = $this->__invoke($cql);

            return [
                'success' => $result['success'],
                'table' => $tableName,
                'database' => $database,
                'message' => $result['success'] ? "Table '{$tableName}' created successfully" : 'Failed to create table',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'database' => $database,
                'message' => 'Error creating table',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Insert data into Spark SQL table.
     *
     * @param string                           $tableName Table name
     * @param array<int, array<string, mixed>> $data      Data to insert
     * @param string                           $database  Database name
     * @param string                           $mode      Insert mode (INSERT, INSERT_OVERWRITE, MERGE)
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     database: string,
     *     rowsInserted: int,
     *     message: string,
     *     error: string,
     * }
     */
    public function insertData(
        string $tableName,
        array $data,
        string $database = '',
        string $mode = 'INSERT',
    ): array {
        try {
            $database = $database ?: $this->database;
            if (!$database) {
                throw new \InvalidArgumentException('Database is required.');
            }

            if (empty($data)) {
                return [
                    'success' => true,
                    'table' => $tableName,
                    'database' => $database,
                    'rowsInserted' => 0,
                    'message' => 'No data to insert',
                    'error' => '',
                ];
            }

            // Get column names from first row
            $columns = array_keys($data[0]);
            $columnList = implode(', ', $columns);

            // Build VALUES clause
            $valueRows = [];
            foreach ($data as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $value = $row[$column] ?? 'NULL';
                    if (\is_string($value)) {
                        $value = "'".addslashes($value)."'";
                    }
                    $values[] = $value;
                }
                $valueRows[] = '('.implode(', ', $values).')';
            }

            $cql = "{$mode} INTO {$database}.{$tableName} ({$columnList}) VALUES ".implode(', ', $valueRows);

            $result = $this->__invoke($cql);

            return [
                'success' => $result['success'],
                'table' => $tableName,
                'database' => $database,
                'rowsInserted' => \count($data),
                'message' => $result['success'] ? 'Data inserted successfully' : 'Failed to insert data',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'database' => $database,
                'rowsInserted' => 0,
                'message' => 'Error inserting data',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Describe Spark SQL table schema.
     *
     * @param string $tableName Table name
     * @param string $database  Database name
     * @param bool   $extended  Include extended information
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     database: string,
     *     columns: array<int, array{
     *         name: string,
     *         type: string,
     *         nullable: bool,
     *         comment: string,
     *     }>,
     *     partitions: array<int, string>,
     *     storage: array{
     *         location: string,
     *         inputFormat: string,
     *         outputFormat: string,
     *         serde: string,
     *     },
     *     error: string,
     * }
     */
    public function describeTable(
        string $tableName,
        string $database = '',
        bool $extended = false,
    ): array {
        try {
            $database = $database ?: $this->database;
            if (!$database) {
                throw new \InvalidArgumentException('Database is required.');
            }

            $query = $extended ? "DESCRIBE EXTENDED {$database}.{$tableName}" : "DESCRIBE {$database}.{$tableName}";
            $result = $this->__invoke($query);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'table' => $tableName,
                    'database' => $database,
                    'columns' => [],
                    'partitions' => [],
                    'storage' => ['location' => '', 'inputFormat' => '', 'outputFormat' => '', 'serde' => ''],
                    'error' => $result['error'],
                ];
            }

            // Parse column information (simplified)
            $columns = [];
            $partitions = [];
            $storage = ['location' => '', 'inputFormat' => '', 'outputFormat' => '', 'serde' => ''];

            foreach ($result['results'] as $row) {
                if (isset($row['col_name'])) {
                    $columns[] = [
                        'name' => $row['col_name'],
                        'type' => $row['data_type'] ?? '',
                        'nullable' => !str_contains(strtolower($row['data_type'] ?? ''), 'not null'),
                        'comment' => $row['comment'] ?? '',
                    ];
                }
            }

            return [
                'success' => true,
                'table' => $tableName,
                'database' => $database,
                'columns' => $columns,
                'partitions' => $partitions,
                'storage' => $storage,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'database' => $database,
                'columns' => [],
                'partitions' => [],
                'storage' => ['location' => '', 'inputFormat' => '', 'outputFormat' => '', 'serde' => ''],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Show Spark SQL tables.
     *
     * @param string $database Database name
     * @param string $pattern  Table name pattern
     *
     * @return array{
     *     success: bool,
     *     database: string,
     *     tables: array<int, array{
     *         name: string,
     *         database: string,
     *         tableType: string,
     *         isTemporary: bool,
     *     }>,
     *     error: string,
     * }
     */
    public function showTables(
        string $database = '',
        string $pattern = '',
    ): array {
        try {
            $database = $database ?: $this->database;
            if (!$database) {
                throw new \InvalidArgumentException('Database is required.');
            }

            $query = "SHOW TABLES FROM {$database}";
            if ($pattern) {
                $query .= " LIKE '{$pattern}'";
            }

            $result = $this->__invoke($query);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'database' => $database,
                    'tables' => [],
                    'error' => $result['error'],
                ];
            }

            $tables = [];
            foreach ($result['results'] as $row) {
                $tables[] = [
                    'name' => $row['tableName'] ?? $row['name'] ?? '',
                    'database' => $database,
                    'tableType' => $row['tableType'] ?? 'MANAGED',
                    'isTemporary' => str_contains($row['tableName'] ?? '', 'temp_'),
                ];
            }

            return [
                'success' => true,
                'database' => $database,
                'tables' => $tables,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'database' => $database,
                'tables' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Show Spark SQL databases.
     *
     * @param string $pattern Database name pattern
     *
     * @return array{
     *     success: bool,
     *     databases: array<int, array{
     *         name: string,
     *         description: string,
     *         locationUri: string,
     *     }>,
     *     error: string,
     * }
     */
    public function showDatabases(string $pattern = ''): array
    {
        try {
            $query = 'SHOW DATABASES';
            if ($pattern) {
                $query .= " LIKE '{$pattern}'";
            }

            $result = $this->__invoke($query);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'databases' => [],
                    'error' => $result['error'],
                ];
            }

            $databases = [];
            foreach ($result['results'] as $row) {
                $databases[] = [
                    'name' => $row['namespace'] ?? $row['databaseName'] ?? $row['name'] ?? '',
                    'description' => $row['description'] ?? '',
                    'locationUri' => $row['locationUri'] ?? '',
                ];
            }

            return [
                'success' => true,
                'databases' => $databases,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'databases' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Optimize Spark SQL table.
     *
     * @param string        $tableName Table name
     * @param string        $database  Database name
     * @param array<string> $columns   Columns to optimize (optional)
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     database: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function optimizeTable(
        string $tableName,
        string $database = '',
        array $columns = [],
    ): array {
        try {
            $database = $database ?: $this->database;
            if (!$database) {
                throw new \InvalidArgumentException('Database is required.');
            }

            $query = "OPTIMIZE {$database}.{$tableName}";
            if (!empty($columns)) {
                $columnList = implode(', ', $columns);
                $query .= " ZORDER BY ({$columnList})";
            }

            $result = $this->__invoke($query);

            return [
                'success' => $result['success'],
                'table' => $tableName,
                'database' => $database,
                'message' => $result['success'] ? "Table '{$tableName}' optimized successfully" : 'Failed to optimize table',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'database' => $database,
                'message' => 'Error optimizing table',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze Spark SQL table.
     *
     * @param string        $tableName Table name
     * @param string        $database  Database name
     * @param array<string> $columns   Columns to analyze (optional)
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     database: string,
     *     statistics: array{
     *         rowCount: int,
     *         sizeInBytes: int,
     *         columnStats: array<string, array{
     *             distinctCount: int,
     *             minValue: mixed,
     *             maxValue: mixed,
     *             nullCount: int,
     *         }>,
     *     },
     *     message: string,
     *     error: string,
     * }
     */
    public function analyzeTable(
        string $tableName,
        string $database = '',
        array $columns = [],
    ): array {
        try {
            $database = $database ?: $this->database;
            if (!$database) {
                throw new \InvalidArgumentException('Database is required.');
            }

            $query = "ANALYZE TABLE {$database}.{$tableName} COMPUTE STATISTICS";
            if (!empty($columns)) {
                $columnList = implode(', ', $columns);
                $query .= " FOR COLUMNS {$columnList}";
            }

            $result = $this->__invoke($query);

            return [
                'success' => $result['success'],
                'table' => $tableName,
                'database' => $database,
                'statistics' => [
                    'rowCount' => 0,
                    'sizeInBytes' => 0,
                    'columnStats' => [],
                ],
                'message' => $result['success'] ? "Table '{$tableName}' analyzed successfully" : 'Failed to analyze table',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'database' => $database,
                'statistics' => [
                    'rowCount' => 0,
                    'sizeInBytes' => 0,
                    'columnStats' => [],
                ],
                'message' => 'Error analyzing table',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build Spark SQL command.
     */
    private function buildSparkSqlCommand(string $query, array $params, int $timeout): string
    {
        $command = "spark-sql --master yarn --conf spark.sql.execution.timeout={$timeout}s";

        if ($this->database) {
            $command .= " --database {$this->database}";
        }

        $command .= ' -e "'.addslashes($query).'"';

        return $command;
    }

    /**
     * Execute command.
     */
    private function executeCommand(string $command): string
    {
        $output = [];
        $returnCode = 0;

        exec("{$command} 2>&1", $output, $returnCode);

        if (0 !== $returnCode) {
            throw new \RuntimeException('Spark SQL command failed: '.implode("\n", $output));
        }

        return implode("\n", $output);
    }

    /**
     * Parse Spark SQL output.
     */
    private function parseSparkSqlOutput(string $output): array
    {
        // This is a simplified parser
        // In reality, you would need more sophisticated parsing
        return [
            'data' => [],
            'columns' => [],
            'queryId' => uniqid('spark_', true),
        ];
    }
}
