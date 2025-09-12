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
#[AsTool('databricks_execute_notebook', 'Tool that executes Databricks notebooks')]
#[AsTool('databricks_create_cluster', 'Tool that creates Databricks clusters', method: 'createCluster')]
#[AsTool('databricks_list_clusters', 'Tool that lists Databricks clusters', method: 'listClusters')]
#[AsTool('databricks_get_cluster', 'Tool that gets Databricks cluster details', method: 'getCluster')]
#[AsTool('databricks_terminate_cluster', 'Tool that terminates Databricks clusters', method: 'terminateCluster')]
#[AsTool('databricks_upload_file', 'Tool that uploads files to Databricks', method: 'uploadFile')]
#[AsTool('databricks_list_jobs', 'Tool that lists Databricks jobs', method: 'listJobs')]
#[AsTool('databricks_run_job', 'Tool that runs Databricks jobs', method: 'runJob')]
final readonly class Databricks
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $workspaceUrl,
        private string $accessToken,
        private array $options = [],
    ) {
    }

    /**
     * Execute Databricks notebook.
     *
     * @param string               $notebookPath Path to the notebook
     * @param string               $clusterId    Cluster ID to run on
     * @param array<string, mixed> $parameters   Notebook parameters
     * @param string               $language     Notebook language (python, scala, sql, r)
     *
     * @return array{
     *     success: bool,
     *     runId: string,
     *     notebookPath: string,
     *     clusterId: string,
     *     state: string,
     *     startTime: int,
     *     setupDuration: int,
     *     executionDuration: int,
     *     cleanupDuration: int,
     *     result: string,
     *     error: string,
     * }
     */
    public function __invoke(
        string $notebookPath,
        string $clusterId,
        array $parameters = [],
        string $language = 'python',
    ): array {
        try {
            $requestData = [
                'notebook_path' => $notebookPath,
                'cluster_id' => $clusterId,
                'notebook_params' => $parameters,
                'language' => $language,
            ];

            $response = $this->httpClient->request('POST', "{$this->workspaceUrl}/api/2.0/jobs/runs/submit", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'runId' => $data['run_id'] ?? '',
                'notebookPath' => $notebookPath,
                'clusterId' => $clusterId,
                'state' => $data['state'] ?? 'PENDING',
                'startTime' => $data['start_time'] ?? 0,
                'setupDuration' => $data['setup_duration'] ?? 0,
                'executionDuration' => $data['execution_duration'] ?? 0,
                'cleanupDuration' => $data['cleanup_duration'] ?? 0,
                'result' => $data['result'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'runId' => '',
                'notebookPath' => $notebookPath,
                'clusterId' => $clusterId,
                'state' => 'ERROR',
                'startTime' => 0,
                'setupDuration' => 0,
                'executionDuration' => 0,
                'cleanupDuration' => 0,
                'result' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Databricks cluster.
     *
     * @param string               $clusterName            Cluster name
     * @param string               $sparkVersion           Spark version
     * @param string               $nodeTypeId             Node type ID
     * @param int                  $numWorkers             Number of worker nodes
     * @param string               $driverNodeTypeId       Driver node type ID
     * @param array<string, mixed> $sparkConf              Spark configuration
     * @param array<string, mixed> $awsAttributes          AWS attributes
     * @param array<string, mixed> $autoterminationMinutes Auto-termination minutes
     *
     * @return array{
     *     success: bool,
     *     clusterId: string,
     *     clusterName: string,
     *     state: string,
     *     sparkVersion: string,
     *     nodeTypeId: string,
     *     driverNodeTypeId: string,
     *     numWorkers: int,
     *     autoterminationMinutes: int,
     *     startTime: int,
     *     error: string,
     * }
     */
    public function createCluster(
        string $clusterName,
        string $sparkVersion = '13.3.x-scala2.12',
        string $nodeTypeId = 'i3.xlarge',
        int $numWorkers = 1,
        string $driverNodeTypeId = '',
        array $sparkConf = [],
        array $awsAttributes = [],
        int $autoterminationMinutes = 30,
    ): array {
        try {
            $requestData = [
                'cluster_name' => $clusterName,
                'spark_version' => $sparkVersion,
                'node_type_id' => $nodeTypeId,
                'num_workers' => $numWorkers,
                'spark_conf' => $sparkConf,
                'aws_attributes' => $awsAttributes,
                'autotermination_minutes' => $autoterminationMinutes,
            ];

            if ($driverNodeTypeId) {
                $requestData['driver_node_type_id'] = $driverNodeTypeId;
            }

            $response = $this->httpClient->request('POST', "{$this->workspaceUrl}/api/2.0/clusters/create", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'clusterId' => $data['cluster_id'] ?? '',
                'clusterName' => $clusterName,
                'state' => $data['state'] ?? 'PENDING',
                'sparkVersion' => $sparkVersion,
                'nodeTypeId' => $nodeTypeId,
                'driverNodeTypeId' => $driverNodeTypeId ?: $nodeTypeId,
                'numWorkers' => $numWorkers,
                'autoterminationMinutes' => $autoterminationMinutes,
                'startTime' => $data['start_time'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'clusterId' => '',
                'clusterName' => $clusterName,
                'state' => 'ERROR',
                'sparkVersion' => $sparkVersion,
                'nodeTypeId' => $nodeTypeId,
                'driverNodeTypeId' => $driverNodeTypeId,
                'numWorkers' => $numWorkers,
                'autoterminationMinutes' => $autoterminationMinutes,
                'startTime' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List Databricks clusters.
     *
     * @param bool $canUseClient Include clusters that can be used by client
     *
     * @return array{
     *     success: bool,
     *     clusters: array<int, array{
     *         clusterId: string,
     *         clusterName: string,
     *         state: string,
     *         sparkVersion: string,
     *         nodeTypeId: string,
     *         driverNodeTypeId: string,
     *         numWorkers: int,
     *         autoterminationMinutes: int,
     *         startTime: int,
     *         terminatedTime: int,
     *         lastStateLossTime: int,
     *         lastActivityTime: int,
     *         clusterMemoryMb: int,
     *         clusterCores: float,
     *         defaultTags: array<string, string>,
     *         clusterLogConf: array<string, mixed>,
     *         initScripts: array<int, array<string, mixed>>,
     *         sparkConf: array<string, mixed>,
     *         awsAttributes: array<string, mixed>,
     *         azureAttributes: array<string, mixed>,
     *         gcpAttributes: array<string, mixed>,
     *         customTags: array<string, string>,
     *         enableElasticDisk: bool,
     *         driver: array<string, mixed>,
     *         executors: array<int, array<string, mixed>>,
     *         sparkContextId: int,
     *         jdbcPort: int,
     *         sparkUiPort: int,
     *         sparkVersion: string,
     *         stateMessage: string,
     *         startTime: int,
     *         terminatedTime: int,
     *         lastStateLossTime: int,
     *         lastActivityTime: int,
     *         clusterMemoryMb: int,
     *         clusterCores: float,
     *         defaultTags: array<string, string>,
     *         clusterLogConf: array<string, mixed>,
     *         initScripts: array<int, array<string, mixed>>,
     *         sparkConf: array<string, mixed>,
     *         awsAttributes: array<string, mixed>,
     *         azureAttributes: array<string, mixed>,
     *         gcpAttributes: array<string, mixed>,
     *         customTags: array<string, string>,
     *         enableElasticDisk: bool,
     *         driver: array<string, mixed>,
     *         executors: array<int, array<string, mixed>>,
     *         sparkContextId: int,
     *         jdbcPort: int,
     *         sparkUiPort: int,
     *         sparkVersion: string,
     *         stateMessage: string,
     *     }>,
     *     error: string,
     * }
     */
    public function listClusters(bool $canUseClient = false): array
    {
        try {
            $params = [];
            if ($canUseClient) {
                $params['can_use_client'] = 'true';
            }

            $response = $this->httpClient->request('GET', "{$this->workspaceUrl}/api/2.0/clusters/list", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'clusters' => array_map(fn ($cluster) => [
                    'clusterId' => $cluster['cluster_id'],
                    'clusterName' => $cluster['cluster_name'],
                    'state' => $cluster['state'],
                    'sparkVersion' => $cluster['spark_version'],
                    'nodeTypeId' => $cluster['node_type_id'],
                    'driverNodeTypeId' => $cluster['driver_node_type_id'],
                    'numWorkers' => $cluster['num_workers'],
                    'autoterminationMinutes' => $cluster['autotermination_minutes'],
                    'startTime' => $cluster['start_time'] ?? 0,
                    'terminatedTime' => $cluster['terminated_time'] ?? 0,
                    'lastStateLossTime' => $cluster['last_state_loss_time'] ?? 0,
                    'lastActivityTime' => $cluster['last_activity_time'] ?? 0,
                    'clusterMemoryMb' => $cluster['cluster_memory_mb'] ?? 0,
                    'clusterCores' => $cluster['cluster_cores'] ?? 0.0,
                    'defaultTags' => $cluster['default_tags'] ?? [],
                    'clusterLogConf' => $cluster['cluster_log_conf'] ?? [],
                    'initScripts' => $cluster['init_scripts'] ?? [],
                    'sparkConf' => $cluster['spark_conf'] ?? [],
                    'awsAttributes' => $cluster['aws_attributes'] ?? [],
                    'azureAttributes' => $cluster['azure_attributes'] ?? [],
                    'gcpAttributes' => $cluster['gcp_attributes'] ?? [],
                    'customTags' => $cluster['custom_tags'] ?? [],
                    'enableElasticDisk' => $cluster['enable_elastic_disk'] ?? false,
                    'driver' => $cluster['driver'] ?? [],
                    'executors' => $cluster['executors'] ?? [],
                    'sparkContextId' => $cluster['spark_context_id'] ?? 0,
                    'jdbcPort' => $cluster['jdbc_port'] ?? 0,
                    'sparkUiPort' => $cluster['spark_ui_port'] ?? 0,
                    'sparkVersion' => $cluster['spark_version'],
                    'stateMessage' => $cluster['state_message'] ?? '',
                ], $data['clusters'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'clusters' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Databricks cluster details.
     *
     * @param string $clusterId Cluster ID
     *
     * @return array{
     *     success: bool,
     *     cluster: array{
     *         clusterId: string,
     *         clusterName: string,
     *         state: string,
     *         sparkVersion: string,
     *         nodeTypeId: string,
     *         driverNodeTypeId: string,
     *         numWorkers: int,
     *         autoterminationMinutes: int,
     *         startTime: int,
     *         terminatedTime: int,
     *         lastStateLossTime: int,
     *         lastActivityTime: int,
     *         clusterMemoryMb: int,
     *         clusterCores: float,
     *         defaultTags: array<string, string>,
     *         clusterLogConf: array<string, mixed>,
     *         initScripts: array<int, array<string, mixed>>,
     *         sparkConf: array<string, mixed>,
     *         awsAttributes: array<string, mixed>,
     *         azureAttributes: array<string, mixed>,
     *         gcpAttributes: array<string, mixed>,
     *         customTags: array<string, string>,
     *         enableElasticDisk: bool,
     *         driver: array<string, mixed>,
     *         executors: array<int, array<string, mixed>>,
     *         sparkContextId: int,
     *         jdbcPort: int,
     *         sparkUiPort: int,
     *         sparkVersion: string,
     *         stateMessage: string,
     *     },
     *     error: string,
     * }
     */
    public function getCluster(string $clusterId): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->workspaceUrl}/api/2.0/clusters/get", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['cluster_id' => $clusterId]),
            ]);

            $data = $response->toArray();
            $cluster = $data;

            return [
                'success' => true,
                'cluster' => [
                    'clusterId' => $cluster['cluster_id'],
                    'clusterName' => $cluster['cluster_name'],
                    'state' => $cluster['state'],
                    'sparkVersion' => $cluster['spark_version'],
                    'nodeTypeId' => $cluster['node_type_id'],
                    'driverNodeTypeId' => $cluster['driver_node_type_id'],
                    'numWorkers' => $cluster['num_workers'],
                    'autoterminationMinutes' => $cluster['autotermination_minutes'],
                    'startTime' => $cluster['start_time'] ?? 0,
                    'terminatedTime' => $cluster['terminated_time'] ?? 0,
                    'lastStateLossTime' => $cluster['last_state_loss_time'] ?? 0,
                    'lastActivityTime' => $cluster['last_activity_time'] ?? 0,
                    'clusterMemoryMb' => $cluster['cluster_memory_mb'] ?? 0,
                    'clusterCores' => $cluster['cluster_cores'] ?? 0.0,
                    'defaultTags' => $cluster['default_tags'] ?? [],
                    'clusterLogConf' => $cluster['cluster_log_conf'] ?? [],
                    'initScripts' => $cluster['init_scripts'] ?? [],
                    'sparkConf' => $cluster['spark_conf'] ?? [],
                    'awsAttributes' => $cluster['aws_attributes'] ?? [],
                    'azureAttributes' => $cluster['azure_attributes'] ?? [],
                    'gcpAttributes' => $cluster['gcp_attributes'] ?? [],
                    'customTags' => $cluster['custom_tags'] ?? [],
                    'enableElasticDisk' => $cluster['enable_elastic_disk'] ?? false,
                    'driver' => $cluster['driver'] ?? [],
                    'executors' => $cluster['executors'] ?? [],
                    'sparkContextId' => $cluster['spark_context_id'] ?? 0,
                    'jdbcPort' => $cluster['jdbc_port'] ?? 0,
                    'sparkUiPort' => $cluster['spark_ui_port'] ?? 0,
                    'sparkVersion' => $cluster['spark_version'],
                    'stateMessage' => $cluster['state_message'] ?? '',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'cluster' => [
                    'clusterId' => $clusterId,
                    'clusterName' => '',
                    'state' => 'ERROR',
                    'sparkVersion' => '',
                    'nodeTypeId' => '',
                    'driverNodeTypeId' => '',
                    'numWorkers' => 0,
                    'autoterminationMinutes' => 0,
                    'startTime' => 0,
                    'terminatedTime' => 0,
                    'lastStateLossTime' => 0,
                    'lastActivityTime' => 0,
                    'clusterMemoryMb' => 0,
                    'clusterCores' => 0.0,
                    'defaultTags' => [],
                    'clusterLogConf' => [],
                    'initScripts' => [],
                    'sparkConf' => [],
                    'awsAttributes' => [],
                    'azureAttributes' => [],
                    'gcpAttributes' => [],
                    'customTags' => [],
                    'enableElasticDisk' => false,
                    'driver' => [],
                    'executors' => [],
                    'sparkContextId' => 0,
                    'jdbcPort' => 0,
                    'sparkUiPort' => 0,
                    'sparkVersion' => '',
                    'stateMessage' => '',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Terminate Databricks cluster.
     *
     * @param string $clusterId Cluster ID
     *
     * @return array{
     *     success: bool,
     *     clusterId: string,
     *     state: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function terminateCluster(string $clusterId): array
    {
        try {
            $requestData = [
                'cluster_id' => $clusterId,
            ];

            $response = $this->httpClient->request('POST', "{$this->workspaceUrl}/api/2.0/clusters/delete", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'clusterId' => $clusterId,
                'state' => 'TERMINATING',
                'message' => 'Cluster termination initiated',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'clusterId' => $clusterId,
                'state' => 'ERROR',
                'message' => 'Failed to terminate cluster',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload file to Databricks.
     *
     * @param string $filePath    Local file path
     * @param string $destination Destination path in Databricks
     * @param bool   $overwrite   Overwrite existing file
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
        string $destination,
        bool $overwrite = false,
    ): array {
        try {
            if (!file_exists($filePath)) {
                throw new \InvalidArgumentException("File not found: {$filePath}.");
            }

            $fileContent = file_get_contents($filePath);
            $fileName = basename($filePath);

            $requestData = [
                'path' => $destination,
                'contents' => base64_encode($fileContent),
                'overwrite' => $overwrite,
            ];

            $response = $this->httpClient->request('POST', "{$this->workspaceUrl}/api/2.0/dbfs/put", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
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
     * List Databricks jobs.
     *
     * @param int    $limit       Number of jobs to return
     * @param int    $offset      Offset for pagination
     * @param string $expandTasks Expand task details
     *
     * @return array{
     *     success: bool,
     *     jobs: array<int, array{
     *         jobId: int,
     *         name: string,
     *         creatorUserName: string,
     *         runAsUserName: string,
     *         createdTime: int,
     *         settings: array<string, mixed>,
     *         schedule: array<string, mixed>,
     *         maxConcurrentRuns: int,
     *         maxConcurrentRuns: int,
     *         tags: array<string, string>,
     *         tasks: array<int, array<string, mixed>>,
     *     }>,
     *     hasMore: bool,
     *     error: string,
     * }
     */
    public function listJobs(
        int $limit = 25,
        int $offset = 0,
        string $expandTasks = 'false',
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 100)),
                'offset' => max(0, $offset),
                'expand_tasks' => $expandTasks,
            ];

            $response = $this->httpClient->request('GET', "{$this->workspaceUrl}/api/2.0/jobs/list", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'jobs' => array_map(fn ($job) => [
                    'jobId' => $job['job_id'],
                    'name' => $job['name'],
                    'creatorUserName' => $job['creator_user_name'],
                    'runAsUserName' => $job['run_as_user_name'],
                    'createdTime' => $job['created_time'],
                    'settings' => $job['settings'] ?? [],
                    'schedule' => $job['schedule'] ?? [],
                    'maxConcurrentRuns' => $job['max_concurrent_runs'] ?? 1,
                    'tags' => $job['tags'] ?? [],
                    'tasks' => $job['tasks'] ?? [],
                ], $data['jobs'] ?? []),
                'hasMore' => $data['has_more'] ?? false,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'jobs' => [],
                'hasMore' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run Databricks job.
     *
     * @param int                  $jobId             Job ID
     * @param array<string, mixed> $notebookParams    Notebook parameters
     * @param array<string, mixed> $pythonParams      Python parameters
     * @param array<string, mixed> $jarParams         JAR parameters
     * @param array<string, mixed> $sparkSubmitParams Spark submit parameters
     *
     * @return array{
     *     success: bool,
     *     runId: int,
     *     jobId: int,
     *     state: string,
     *     startTime: int,
     *     setupDuration: int,
     *     executionDuration: int,
     *     cleanupDuration: int,
     *     result: string,
     *     error: string,
     * }
     */
    public function runJob(
        int $jobId,
        array $notebookParams = [],
        array $pythonParams = [],
        array $jarParams = [],
        array $sparkSubmitParams = [],
    ): array {
        try {
            $requestData = [
                'job_id' => $jobId,
                'notebook_params' => $notebookParams,
                'python_params' => $pythonParams,
                'jar_params' => $jarParams,
                'spark_submit_params' => $sparkSubmitParams,
            ];

            $response = $this->httpClient->request('POST', "{$this->workspaceUrl}/api/2.0/jobs/run-now", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'runId' => $data['run_id'] ?? 0,
                'jobId' => $jobId,
                'state' => $data['state'] ?? 'PENDING',
                'startTime' => $data['start_time'] ?? 0,
                'setupDuration' => $data['setup_duration'] ?? 0,
                'executionDuration' => $data['execution_duration'] ?? 0,
                'cleanupDuration' => $data['cleanup_duration'] ?? 0,
                'result' => $data['result'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'runId' => 0,
                'jobId' => $jobId,
                'state' => 'ERROR',
                'startTime' => 0,
                'setupDuration' => 0,
                'executionDuration' => 0,
                'cleanupDuration' => 0,
                'result' => '',
                'error' => $e->getMessage(),
            ];
        }
    }
}
