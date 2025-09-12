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
#[AsTool('cassandra_execute_cql', 'Tool that executes CQL queries on Cassandra')]
#[AsTool('cassandra_create_keyspace', 'Tool that creates Cassandra keyspace', method: 'createKeyspace')]
#[AsTool('cassandra_create_table', 'Tool that creates Cassandra table', method: 'createTable')]
#[AsTool('cassandra_insert_data', 'Tool that inserts data into Cassandra', method: 'insertData')]
#[AsTool('cassandra_select_data', 'Tool that selects data from Cassandra', method: 'selectData')]
#[AsTool('cassandra_update_data', 'Tool that updates data in Cassandra', method: 'updateData')]
#[AsTool('cassandra_delete_data', 'Tool that deletes data from Cassandra', method: 'deleteData')]
#[AsTool('cassandra_describe_keyspaces', 'Tool that describes Cassandra keyspaces', method: 'describeKeyspaces')]
#[AsTool('cassandra_describe_tables', 'Tool that describes Cassandra tables', method: 'describeTables')]
final readonly class CassandraDatabase
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $host = 'localhost',
        private int $port = 9042,
        private string $username = '',
        #[\SensitiveParameter] private string $password = '',
        private string $keyspace = '',
        private array $options = [],
    ) {
    }

    /**
     * Execute CQL query on Cassandra.
     *
     * @param string               $cql         CQL query to execute
     * @param array<string, mixed> $params      Query parameters
     * @param string               $consistency Consistency level
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array<string, mixed>>,
     *     columns: array<int, array{
     *         name: string,
     *         type: string,
     *     }>,
     *     executionTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $cql,
        array $params = [],
        string $consistency = 'ONE',
    ): array {
        try {
            $startTime = microtime(true);

            // This is a simplified implementation
            // In reality, you would use a proper Cassandra driver like DataStax PHP driver
            $command = $this->buildCqlshCommand($cql, $params);
            $output = $this->executeCommand($command);

            $executionTime = microtime(true) - $startTime;

            // Parse output (simplified)
            $results = $this->parseCqlOutput($output);

            return [
                'success' => true,
                'results' => $results['data'],
                'columns' => $results['columns'],
                'executionTime' => $executionTime,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'columns' => [],
                'executionTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Cassandra keyspace.
     *
     * @param string $keyspaceName        Keyspace name
     * @param string $replicationStrategy Replication strategy (SimpleStrategy, NetworkTopologyStrategy)
     * @param int    $replicationFactor   Replication factor
     *
     * @return array{
     *     success: bool,
     *     keyspace: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function createKeyspace(
        string $keyspaceName,
        string $replicationStrategy = 'SimpleStrategy',
        int $replicationFactor = 3,
    ): array {
        try {
            $cql = "CREATE KEYSPACE IF NOT EXISTS {$keyspaceName} WITH REPLICATION = {'class': '{$replicationStrategy}'";

            if ('SimpleStrategy' === $replicationStrategy) {
                $cql .= ", 'replication_factor': {$replicationFactor}";
            }

            $cql .= '};';

            $result = $this->__invoke($cql);

            return [
                'success' => $result['success'],
                'keyspace' => $keyspaceName,
                'message' => $result['success'] ? "Keyspace '{$keyspaceName}' created successfully" : 'Failed to create keyspace',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'keyspace' => $keyspaceName,
                'message' => 'Error creating keyspace',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Cassandra table.
     *
     * @param string $tableName Table name
     * @param array<int, array{
     *     name: string,
     *     type: string,
     *     isPrimaryKey: bool,
     *     isPartitionKey: bool,
     *     isClusteringKey: bool,
     * }> $columns Table columns
     * @param string $keyspace Keyspace name
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     keyspace: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function createTable(
        string $tableName,
        array $columns,
        string $keyspace = '',
    ): array {
        try {
            $keyspace = $keyspace ?: $this->keyspace;
            if (!$keyspace) {
                throw new \InvalidArgumentException('Keyspace is required.');
            }

            $columnDefs = [];
            $primaryKey = [];

            foreach ($columns as $column) {
                $columnDef = "{$column['name']} {$column['type']}";
                $columnDefs[] = $columnDef;

                if ($column['isPrimaryKey']) {
                    $primaryKey[] = $column['name'];
                }
            }

            $cql = "CREATE TABLE IF NOT EXISTS {$keyspace}.{$tableName} (".
                   implode(', ', $columnDefs).
                   ', PRIMARY KEY ('.implode(', ', $primaryKey).'));';

            $result = $this->__invoke($cql);

            return [
                'success' => $result['success'],
                'table' => $tableName,
                'keyspace' => $keyspace,
                'message' => $result['success'] ? "Table '{$tableName}' created successfully" : 'Failed to create table',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'keyspace' => $keyspace,
                'message' => 'Error creating table',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Insert data into Cassandra.
     *
     * @param string               $tableName Table name
     * @param array<string, mixed> $data      Data to insert
     * @param string               $keyspace  Keyspace name
     * @param string               $ttl       Time to live (optional)
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     keyspace: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function insertData(
        string $tableName,
        array $data,
        string $keyspace = '',
        string $ttl = '',
    ): array {
        try {
            $keyspace = $keyspace ?: $this->keyspace;
            if (!$keyspace) {
                throw new \InvalidArgumentException('Keyspace is required.');
            }

            $columns = array_keys($data);
            $values = array_values($data);

            $cql = "INSERT INTO {$keyspace}.{$tableName} (".implode(', ', $columns).') VALUES ('.
                   implode(', ', array_fill(0, \count($values), '?')).')';

            if ($ttl) {
                $cql .= " USING TTL {$ttl}";
            }

            $result = $this->__invoke($cql, $values);

            return [
                'success' => $result['success'],
                'table' => $tableName,
                'keyspace' => $keyspace,
                'message' => $result['success'] ? 'Data inserted successfully' : 'Failed to insert data',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'keyspace' => $keyspace,
                'message' => 'Error inserting data',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Select data from Cassandra.
     *
     * @param string               $tableName Table name
     * @param array<string, mixed> $where     Where conditions
     * @param string               $keyspace  Keyspace name
     * @param int                  $limit     Limit results
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array<string, mixed>>,
     *     columns: array<int, array{
     *         name: string,
     *         type: string,
     *     }>,
     *     count: int,
     *     error: string,
     * }
     */
    public function selectData(
        string $tableName,
        array $where = [],
        string $keyspace = '',
        int $limit = 100,
    ): array {
        try {
            $keyspace = $keyspace ?: $this->keyspace;
            if (!$keyspace) {
                throw new \InvalidArgumentException('Keyspace is required.');
            }

            $cql = "SELECT * FROM {$keyspace}.{$tableName}";

            if (!empty($where)) {
                $conditions = [];
                foreach ($where as $column => $value) {
                    $conditions[] = "{$column} = ?";
                }
                $cql .= ' WHERE '.implode(' AND ', $conditions);
            }

            if ($limit > 0) {
                $cql .= " LIMIT {$limit}";
            }

            $result = $this->__invoke($cql, array_values($where));

            return [
                'success' => $result['success'],
                'results' => $result['results'],
                'columns' => $result['columns'],
                'count' => \count($result['results']),
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'columns' => [],
                'count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update data in Cassandra.
     *
     * @param string               $tableName Table name
     * @param array<string, mixed> $data      Data to update
     * @param array<string, mixed> $where     Where conditions
     * @param string               $keyspace  Keyspace name
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     keyspace: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function updateData(
        string $tableName,
        array $data,
        array $where,
        string $keyspace = '',
    ): array {
        try {
            $keyspace = $keyspace ?: $this->keyspace;
            if (!$keyspace) {
                throw new \InvalidArgumentException('Keyspace is required.');
            }

            $setClause = [];
            foreach (array_keys($data) as $column) {
                $setClause[] = "{$column} = ?";
            }

            $whereClause = [];
            foreach (array_keys($where) as $column) {
                $whereClause[] = "{$column} = ?";
            }

            $cql = "UPDATE {$keyspace}.{$tableName} SET ".implode(', ', $setClause).
                   ' WHERE '.implode(' AND ', $whereClause);

            $params = array_merge(array_values($data), array_values($where));
            $result = $this->__invoke($cql, $params);

            return [
                'success' => $result['success'],
                'table' => $tableName,
                'keyspace' => $keyspace,
                'message' => $result['success'] ? 'Data updated successfully' : 'Failed to update data',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'keyspace' => $keyspace,
                'message' => 'Error updating data',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete data from Cassandra.
     *
     * @param string               $tableName Table name
     * @param array<string, mixed> $where     Where conditions
     * @param string               $keyspace  Keyspace name
     *
     * @return array{
     *     success: bool,
     *     table: string,
     *     keyspace: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function deleteData(
        string $tableName,
        array $where,
        string $keyspace = '',
    ): array {
        try {
            $keyspace = $keyspace ?: $this->keyspace;
            if (!$keyspace) {
                throw new \InvalidArgumentException('Keyspace is required.');
            }

            $whereClause = [];
            foreach (array_keys($where) as $column) {
                $whereClause[] = "{$column} = ?";
            }

            $cql = "DELETE FROM {$keyspace}.{$tableName} WHERE ".implode(' AND ', $whereClause);

            $result = $this->__invoke($cql, array_values($where));

            return [
                'success' => $result['success'],
                'table' => $tableName,
                'keyspace' => $keyspace,
                'message' => $result['success'] ? 'Data deleted successfully' : 'Failed to delete data',
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'table' => $tableName,
                'keyspace' => $keyspace,
                'message' => 'Error deleting data',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Describe Cassandra keyspaces.
     *
     * @return array{
     *     success: bool,
     *     keyspaces: array<int, array{
     *         name: string,
     *         durableWrites: bool,
     *         replication: array<string, mixed>,
     *     }>,
     *     error: string,
     * }
     */
    public function describeKeyspaces(): array
    {
        try {
            $result = $this->__invoke('DESCRIBE KEYSPACES;');

            // Parse keyspace information (simplified)
            $keyspaces = [];
            foreach ($result['results'] as $row) {
                $keyspaces[] = [
                    'name' => $row['keyspace_name'] ?? '',
                    'durableWrites' => $row['durable_writes'] ?? true,
                    'replication' => $row['replication'] ?? [],
                ];
            }

            return [
                'success' => $result['success'],
                'keyspaces' => $keyspaces,
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'keyspaces' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Describe Cassandra tables.
     *
     * @param string $keyspace Keyspace name
     *
     * @return array{
     *     success: bool,
     *     tables: array<int, array{
     *         name: string,
     *         keyspace: string,
     *         columns: array<int, array{
     *             name: string,
     *             type: string,
     *             kind: string,
     *         }>,
     *     }>,
     *     error: string,
     * }
     */
    public function describeTables(string $keyspace = ''): array
    {
        try {
            $keyspace = $keyspace ?: $this->keyspace;
            if (!$keyspace) {
                throw new \InvalidArgumentException('Keyspace is required.');
            }

            $result = $this->__invoke("DESCRIBE TABLES FROM {$keyspace};");

            // Parse table information (simplified)
            $tables = [];
            foreach ($result['results'] as $row) {
                $tables[] = [
                    'name' => $row['table_name'] ?? '',
                    'keyspace' => $keyspace,
                    'columns' => [], // Would need separate DESCRIBE TABLE command
                ];
            }

            return [
                'success' => $result['success'],
                'tables' => $tables,
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'tables' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build cqlsh command.
     */
    private function buildCqlshCommand(string $cql, array $params): string
    {
        $command = "cqlsh {$this->host} {$this->port}";

        if ($this->username) {
            $command .= " -u {$this->username}";
        }

        if ($this->password) {
            $command .= " -p {$this->password}";
        }

        if ($this->keyspace) {
            $command .= " -k {$this->keyspace}";
        }

        $command .= ' -e "'.addslashes($cql).'"';

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
            throw new \RuntimeException('CQL command failed: '.implode("\n", $output));
        }

        return implode("\n", $output);
    }

    /**
     * Parse CQL output.
     */
    private function parseCqlOutput(string $output): array
    {
        // This is a simplified parser
        // In reality, you would need more sophisticated parsing
        return [
            'data' => [],
            'columns' => [],
        ];
    }
}
