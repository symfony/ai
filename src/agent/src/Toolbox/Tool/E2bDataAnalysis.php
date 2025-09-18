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
#[AsTool('e2b_run_python', 'Tool that runs Python code in E2B sandbox')]
#[AsTool('e2b_run_notebook', 'Tool that runs Jupyter notebook in E2B', method: 'runNotebook')]
#[AsTool('e2b_upload_file', 'Tool that uploads files to E2B sandbox', method: 'uploadFile')]
#[AsTool('e2b_download_file', 'Tool that downloads files from E2B sandbox', method: 'downloadFile')]
#[AsTool('e2b_list_files', 'Tool that lists files in E2B sandbox', method: 'listFiles')]
#[AsTool('e2b_install_package', 'Tool that installs Python packages in E2B', method: 'installPackage')]
#[AsTool('e2b_run_sql', 'Tool that runs SQL queries in E2B', method: 'runSql')]
#[AsTool('e2b_create_sandbox', 'Tool that creates E2B sandbox', method: 'createSandbox')]
final readonly class E2bDataAnalysis
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.e2b.dev',
        private array $options = [],
    ) {
    }

    /**
     * Run Python code in E2B sandbox.
     *
     * @param string               $code        Python code to execute
     * @param string               $sandboxId   E2B sandbox ID
     * @param int                  $timeout     Execution timeout in seconds
     * @param array<string, mixed> $environment Environment variables
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     stderr: string,
     *     exitCode: int,
     *     executionTime: float,
     *     logs: array<int, array{
     *         timestamp: string,
     *         level: string,
     *         message: string,
     *     }>,
     *     error: string,
     * }
     */
    public function __invoke(
        string $code,
        string $sandboxId,
        int $timeout = 30,
        array $environment = [],
    ): array {
        try {
            $requestData = [
                'code' => $code,
                'timeout' => max(1, min($timeout, 300)),
                'environment' => $environment,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/sandboxes/{$sandboxId}/execute", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'output' => $data['output'] ?? '',
                'stderr' => $data['stderr'] ?? '',
                'exitCode' => $data['exit_code'] ?? 0,
                'executionTime' => $data['execution_time'] ?? 0.0,
                'logs' => array_map(fn ($log) => [
                    'timestamp' => $log['timestamp'] ?? '',
                    'level' => $log['level'] ?? 'info',
                    'message' => $log['message'] ?? '',
                ], $data['logs'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'stderr' => $e->getMessage(),
                'exitCode' => 1,
                'executionTime' => 0.0,
                'logs' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run Jupyter notebook in E2B.
     *
     * @param string          $notebookPath Path to notebook file
     * @param string          $sandboxId    E2B sandbox ID
     * @param bool            $executeAll   Execute all cells
     * @param array<int, int> $cellIndices  Specific cell indices to execute
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array{
     *         cellIndex: int,
     *         output: string,
     *         stderr: string,
     *         exitCode: int,
     *         executionTime: float,
     *     }>,
     *     totalExecutionTime: float,
     *     error: string,
     * }
     */
    public function runNotebook(
        string $notebookPath,
        string $sandboxId,
        bool $executeAll = true,
        array $cellIndices = [],
    ): array {
        try {
            $requestData = [
                'notebook_path' => $notebookPath,
                'execute_all' => $executeAll,
                'cell_indices' => $cellIndices,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/sandboxes/{$sandboxId}/notebook/execute", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'results' => array_map(fn ($result) => [
                    'cellIndex' => $result['cell_index'] ?? 0,
                    'output' => $result['output'] ?? '',
                    'stderr' => $result['stderr'] ?? '',
                    'exitCode' => $result['exit_code'] ?? 0,
                    'executionTime' => $result['execution_time'] ?? 0.0,
                ], $data['results'] ?? []),
                'totalExecutionTime' => $data['total_execution_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'totalExecutionTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload file to E2B sandbox.
     *
     * @param string $filePath    Local file path
     * @param string $sandboxId   E2B sandbox ID
     * @param string $destination Destination path in sandbox
     *
     * @return array{
     *     success: bool,
     *     filePath: string,
     *     destination: string,
     *     fileSize: int,
     *     message: string,
     *     error: string,
     * }
     */
    public function uploadFile(
        string $filePath,
        string $sandboxId,
        string $destination = '',
    ): array {
        try {
            if (!file_exists($filePath)) {
                throw new \InvalidArgumentException("File not found: {$filePath}.");
            }

            $fileContent = file_get_contents($filePath);
            $fileName = basename($filePath);
            $destination = $destination ?: $fileName;

            $requestData = [
                'file_content' => base64_encode($fileContent),
                'destination' => $destination,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/sandboxes/{$sandboxId}/files", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'filePath' => $filePath,
                'destination' => $destination,
                'fileSize' => \strlen($fileContent),
                'message' => 'File uploaded successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'filePath' => $filePath,
                'destination' => $destination,
                'fileSize' => 0,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Download file from E2B sandbox.
     *
     * @param string $sandboxPath Path to file in sandbox
     * @param string $sandboxId   E2B sandbox ID
     * @param string $localPath   Local path to save file
     *
     * @return array{
     *     success: bool,
     *     sandboxPath: string,
     *     localPath: string,
     *     fileSize: int,
     *     message: string,
     *     error: string,
     * }
     */
    public function downloadFile(
        string $sandboxPath,
        string $sandboxId,
        string $localPath = '',
    ): array {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/sandboxes/{$sandboxId}/files/{$sandboxPath}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ] + $this->options);

            $fileContent = $response->getContent();
            $fileName = basename($sandboxPath);
            $localPath = $localPath ?: $fileName;

            file_put_contents($localPath, $fileContent);

            return [
                'success' => true,
                'sandboxPath' => $sandboxPath,
                'localPath' => $localPath,
                'fileSize' => \strlen($fileContent),
                'message' => 'File downloaded successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'sandboxPath' => $sandboxPath,
                'localPath' => $localPath,
                'fileSize' => 0,
                'message' => 'Failed to download file',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List files in E2B sandbox.
     *
     * @param string $sandboxId E2B sandbox ID
     * @param string $path      Directory path (empty for root)
     * @param bool   $recursive List recursively
     *
     * @return array{
     *     success: bool,
     *     files: array<int, array{
     *         name: string,
     *         path: string,
     *         size: int,
     *         isDirectory: bool,
     *         modifiedAt: string,
     *         permissions: string,
     *     }>,
     *     path: string,
     *     error: string,
     * }
     */
    public function listFiles(
        string $sandboxId,
        string $path = '',
        bool $recursive = false,
    ): array {
        try {
            $params = [];

            if ($path) {
                $params['path'] = $path;
            }

            if ($recursive) {
                $params['recursive'] = 'true';
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/sandboxes/{$sandboxId}/files", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'files' => array_map(fn ($file) => [
                    'name' => $file['name'] ?? '',
                    'path' => $file['path'] ?? '',
                    'size' => $file['size'] ?? 0,
                    'isDirectory' => $file['is_directory'] ?? false,
                    'modifiedAt' => $file['modified_at'] ?? '',
                    'permissions' => $file['permissions'] ?? '',
                ], $data['files'] ?? []),
                'path' => $data['path'] ?? $path,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'files' => [],
                'path' => $path,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Install Python packages in E2B.
     *
     * @param string             $sandboxId      E2B sandbox ID
     * @param array<int, string> $packages       Packages to install
     * @param string             $packageManager Package manager (pip, conda)
     *
     * @return array{
     *     success: bool,
     *     installedPackages: array<int, string>,
     *     output: string,
     *     stderr: string,
     *     executionTime: float,
     *     error: string,
     * }
     */
    public function installPackage(
        string $sandboxId,
        array $packages,
        string $packageManager = 'pip',
    ): array {
        try {
            $packageList = implode(' ', $packages);
            $code = match ($packageManager) {
                'pip' => "!pip install {$packageList}",
                'conda' => "!conda install -y {$packageList}",
                default => "!pip install {$packageList}",
            };

            $requestData = [
                'code' => $code,
                'timeout' => 120,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/sandboxes/{$sandboxId}/execute", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'installedPackages' => $packages,
                'output' => $data['output'] ?? '',
                'stderr' => $data['stderr'] ?? '',
                'executionTime' => $data['execution_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'installedPackages' => [],
                'output' => '',
                'stderr' => $e->getMessage(),
                'executionTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run SQL queries in E2B.
     *
     * @param string $query            SQL query to execute
     * @param string $sandboxId        E2B sandbox ID
     * @param string $database         Database name
     * @param string $connectionString Database connection string
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     rowCount: int,
     *     executionTime: float,
     *     error: string,
     * }
     */
    public function runSql(
        string $query,
        string $sandboxId,
        string $database = 'sqlite',
        string $connectionString = '',
    ): array {
        try {
            $pythonCode = match ($database) {
                'sqlite' => "
import sqlite3
import pandas as pd
import json

# Connect to SQLite database
conn = sqlite3.connect('data.db')
df = pd.read_sql_query('{$query}', conn)
conn.close()

# Convert results to JSON
results = df.to_dict('records')
print(json.dumps(results))
print('COLUMNS:', json.dumps(list(df.columns)))
print('ROW_COUNT:', len(df))
",
                'postgresql' => "
import psycopg2
import pandas as pd
import json

# Connect to PostgreSQL database
conn = psycopg2.connect('{$connectionString}')
df = pd.read_sql_query('{$query}', conn)
conn.close()

# Convert results to JSON
results = df.to_dict('records')
print(json.dumps(results))
print('COLUMNS:', json.dumps(list(df.columns)))
print('ROW_COUNT:', len(df))
",
                'mysql' => "
import pymysql
import pandas as pd
import json

# Connect to MySQL database
conn = pymysql.connect('{$connectionString}')
df = pd.read_sql_query('{$query}', conn)
conn.close()

# Convert results to JSON
results = df.to_dict('records')
print(json.dumps(results))
print('COLUMNS:', json.dumps(list(df.columns)))
print('ROW_COUNT:', len(df))
",
                default => "
import sqlite3
import pandas as pd
import json

# Connect to SQLite database
conn = sqlite3.connect('data.db')
df = pd.read_sql_query('{$query}', conn)
conn.close()

# Convert results to JSON
results = df.to_dict('records')
print(json.dumps(results))
print('COLUMNS:', json.dumps(list(df.columns)))
print('ROW_COUNT:', len(df))
",
            };

            $requestData = [
                'code' => $pythonCode,
                'timeout' => 60,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/sandboxes/{$sandboxId}/execute", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $output = $data['output'] ?? '';

            // Parse the output to extract results
            $lines = explode("\n", trim($output));
            $results = [];
            $columns = [];
            $rowCount = 0;

            foreach ($lines as $line) {
                if (str_starts_with($line, 'COLUMNS:')) {
                    $columnsJson = substr($line, 8);
                    $columns = json_decode($columnsJson, true) ?? [];
                } elseif (str_starts_with($line, 'ROW_COUNT:')) {
                    $rowCount = (int) substr($line, 10);
                } elseif (!str_starts_with($line, 'COLUMNS:') && !str_starts_with($line, 'ROW_COUNT:')) {
                    $results = json_decode($line, true) ?? [];
                }
            }

            return [
                'success' => true,
                'results' => $results,
                'columns' => $columns,
                'rowCount' => $rowCount,
                'executionTime' => $data['execution_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'columns' => [],
                'rowCount' => 0,
                'executionTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create E2B sandbox.
     *
     * @param string               $template    Sandbox template
     * @param array<string, mixed> $environment Environment variables
     * @param int                  $timeout     Sandbox timeout in seconds
     *
     * @return array{
     *     success: bool,
     *     sandbox: array{
     *         id: string,
     *         status: string,
     *         template: string,
     *         createdAt: string,
     *         expiresAt: string,
     *         environment: array<string, mixed>,
     *     },
     *     error: string,
     * }
     */
    public function createSandbox(
        string $template = 'python',
        array $environment = [],
        int $timeout = 3600,
    ): array {
        try {
            $requestData = [
                'template' => $template,
                'environment' => $environment,
                'timeout' => max(300, min($timeout, 7200)),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/sandboxes", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'sandbox' => [
                    'id' => $data['id'] ?? '',
                    'status' => $data['status'] ?? 'creating',
                    'template' => $data['template'] ?? $template,
                    'createdAt' => $data['created_at'] ?? date('c'),
                    'expiresAt' => $data['expires_at'] ?? '',
                    'environment' => $data['environment'] ?? $environment,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'sandbox' => [
                    'id' => '',
                    'status' => 'error',
                    'template' => $template,
                    'createdAt' => '',
                    'expiresAt' => '',
                    'environment' => $environment,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }
}
