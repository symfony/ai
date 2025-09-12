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
#[AsTool('nomad_get_jobs', 'Tool that gets Nomad jobs')]
#[AsTool('nomad_get_job', 'Tool that gets Nomad job details', method: 'getJob')]
#[AsTool('nomad_create_job', 'Tool that creates Nomad jobs', method: 'createJob')]
#[AsTool('nomad_update_job', 'Tool that updates Nomad jobs', method: 'updateJob')]
#[AsTool('nomad_delete_job', 'Tool that deletes Nomad jobs', method: 'deleteJob')]
#[AsTool('nomad_get_allocations', 'Tool that gets Nomad allocations', method: 'getAllocations')]
#[AsTool('nomad_get_nodes', 'Tool that gets Nomad nodes', method: 'getNodes')]
#[AsTool('nomad_get_evals', 'Tool that gets Nomad evaluations', method: 'getEvaluations')]
final readonly class Nomad
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'http://localhost:4646',
        private string $region = 'global',
        private string $namespace = 'default',
        private array $options = [],
    ) {
    }

    /**
     * Get Nomad jobs.
     *
     * @param string $prefix    Job name prefix filter
     * @param string $namespace Namespace filter
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     namespace: string,
     *     type: string,
     *     priority: int,
     *     status: string,
     *     statusDescription: string,
     *     createIndex: int,
     *     modifyIndex: int,
     *     jobSummary: array{
     *         jobId: string,
     *         namespace: string,
     *         summary: array<string, array{
     *             queued: int,
     *             complete: int,
     *             failed: int,
     *             running: int,
     *             starting: int,
     *             lost: int,
     *         }>,
     *         children: array{
     *             pending: int,
     *             running: int,
     *             dead: int,
     *         },
     *     },
     * }>
     */
    public function __invoke(
        string $prefix = '',
        string $namespace = '',
    ): array {
        try {
            $params = [];

            if ($prefix) {
                $params['prefix'] = $prefix;
            }

            if ($namespace) {
                $params['namespace'] = $namespace;
            } else {
                $params['namespace'] = $this->namespace;
            }

            $params['region'] = $this->region;

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/jobs", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($job) => [
                'id' => $job['ID'],
                'name' => $job['Name'],
                'namespace' => $job['Namespace'],
                'type' => $job['Type'],
                'priority' => $job['Priority'],
                'status' => $job['Status'],
                'statusDescription' => $job['StatusDescription'],
                'createIndex' => $job['CreateIndex'],
                'modifyIndex' => $job['ModifyIndex'],
                'jobSummary' => [
                    'jobId' => $job['JobSummary']['JobID'],
                    'namespace' => $job['JobSummary']['Namespace'],
                    'summary' => $job['JobSummary']['Summary'],
                    'children' => [
                        'pending' => $job['JobSummary']['Children']['Pending'],
                        'running' => $job['JobSummary']['Children']['Running'],
                        'dead' => $job['JobSummary']['Children']['Dead'],
                    ],
                ],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Nomad job details.
     *
     * @param string $jobId Job ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     namespace: string,
     *     type: string,
     *     priority: int,
     *     status: string,
     *     statusDescription: string,
     *     createIndex: int,
     *     modifyIndex: int,
     *     job: array{
     *         id: string,
     *         name: string,
     *         namespace: string,
     *         type: string,
     *         priority: int,
     *         region: string,
     *         datacenters: array<int, string>,
     *         taskGroups: array<int, array{
     *             name: string,
     *             count: int,
     *             tasks: array<int, array{
     *                 name: string,
     *                 driver: string,
     *                 config: array<string, mixed>,
     *                 resources: array{
     *                     cpu: int,
     *                     memoryMB: int,
     *                     diskMB: int,
     *                     networks: array<int, array{
     *                         device: string,
     *                         cidr: string,
     *                         ip: string,
     *                         mbits: int,
     *                     }>,
     *                 },
     *             }>,
     *         }>,
     *     },
     * }|string
     */
    public function getJob(string $jobId): array|string
    {
        try {
            $params = [
                'region' => $this->region,
                'namespace' => $this->namespace,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/job/{$jobId}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'id' => $data['ID'],
                'name' => $data['Name'],
                'namespace' => $data['Namespace'],
                'type' => $data['Type'],
                'priority' => $data['Priority'],
                'status' => $data['Status'],
                'statusDescription' => $data['StatusDescription'],
                'createIndex' => $data['CreateIndex'],
                'modifyIndex' => $data['ModifyIndex'],
                'job' => [
                    'id' => $data['Job']['ID'],
                    'name' => $data['Job']['Name'],
                    'namespace' => $data['Job']['Namespace'],
                    'type' => $data['Job']['Type'],
                    'priority' => $data['Job']['Priority'],
                    'region' => $data['Job']['Region'],
                    'datacenters' => $data['Job']['Datacenters'],
                    'taskGroups' => array_map(fn ($group) => [
                        'name' => $group['Name'],
                        'count' => $group['Count'],
                        'tasks' => array_map(fn ($task) => [
                            'name' => $task['Name'],
                            'driver' => $task['Driver'],
                            'config' => $task['Config'],
                            'resources' => [
                                'cpu' => $task['Resources']['CPU'],
                                'memoryMB' => $task['Resources']['MemoryMB'],
                                'diskMB' => $task['Resources']['DiskMB'],
                                'networks' => array_map(fn ($network) => [
                                    'device' => $network['Device'],
                                    'cidr' => $network['CIDR'],
                                    'ip' => $network['IP'],
                                    'mbits' => $network['MBits'],
                                ], $task['Resources']['Networks']),
                            ],
                        ], $group['Tasks']),
                    ], $data['Job']['TaskGroups']),
                ],
            ];
        } catch (\Exception $e) {
            return 'Error getting job: '.$e->getMessage();
        }
    }

    /**
     * Create Nomad job.
     *
     * @param array<string, mixed> $jobSpec      Job specification
     * @param string               $evalPriority Evaluation priority
     *
     * @return array{
     *     success: bool,
     *     evalId: string,
     *     jobModifyIndex: int,
     *     warnings: string,
     *     error: string,
     * }
     */
    public function createJob(
        array $jobSpec,
        string $evalPriority = '',
    ): array {
        try {
            $params = [
                'region' => $this->region,
                'namespace' => $this->namespace,
            ];

            if ($evalPriority) {
                $params['eval_priority'] = $evalPriority;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/jobs", [
                'query' => array_merge($this->options, $params),
                'json' => $jobSpec,
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'evalId' => $data['EvalID'],
                'jobModifyIndex' => $data['JobModifyIndex'],
                'warnings' => $data['Warnings'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'evalId' => '',
                'jobModifyIndex' => 0,
                'warnings' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update Nomad job.
     *
     * @param string               $jobId        Job ID
     * @param array<string, mixed> $jobSpec      Updated job specification
     * @param string               $evalPriority Evaluation priority
     *
     * @return array{
     *     success: bool,
     *     evalId: string,
     *     jobModifyIndex: int,
     *     warnings: string,
     *     error: string,
     * }
     */
    public function updateJob(
        string $jobId,
        array $jobSpec,
        string $evalPriority = '',
    ): array {
        try {
            $params = [
                'region' => $this->region,
                'namespace' => $this->namespace,
            ];

            if ($evalPriority) {
                $params['eval_priority'] = $evalPriority;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/job/{$jobId}", [
                'query' => array_merge($this->options, $params),
                'json' => $jobSpec,
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'evalId' => $data['EvalID'],
                'jobModifyIndex' => $data['JobModifyIndex'],
                'warnings' => $data['Warnings'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'evalId' => '',
                'jobModifyIndex' => 0,
                'warnings' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete Nomad job.
     *
     * @param string $jobId Job ID
     * @param string $purge Purge job completely
     *
     * @return array{
     *     success: bool,
     *     evalId: string,
     *     jobModifyIndex: int,
     *     error: string,
     * }
     */
    public function deleteJob(
        string $jobId,
        string $purge = 'false',
    ): array {
        try {
            $params = [
                'region' => $this->region,
                'namespace' => $this->namespace,
            ];

            if ('true' === $purge) {
                $params['purge'] = 'true';
            }

            $response = $this->httpClient->request('DELETE', "{$this->baseUrl}/v1/job/{$jobId}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'evalId' => $data['EvalID'],
                'jobModifyIndex' => $data['JobModifyIndex'],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'evalId' => '',
                'jobModifyIndex' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Nomad allocations.
     *
     * @param string $jobId     Job ID filter
     * @param string $namespace Namespace filter
     *
     * @return array<int, array{
     *     id: string,
     *     evalId: string,
     *     name: string,
     *     namespace: string,
     *     nodeId: string,
     *     jobId: string,
     *     jobType: string,
     *     taskGroup: string,
     *     desiredStatus: string,
     *     desiredDescription: string,
     *     clientStatus: string,
     *     clientDescription: string,
     *     createIndex: int,
     *     modifyIndex: int,
     *     taskStates: array<string, array{
     *         state: string,
     *         failed: bool,
     *         restarts: int,
     *         lastRestart: string,
     *         startedAt: string,
     *         finishedAt: string,
     *     }>,
     * }>
     */
    public function getAllocations(
        string $jobId = '',
        string $namespace = '',
    ): array {
        try {
            $params = [];

            if ($jobId) {
                $params['job'] = $jobId;
            }

            if ($namespace) {
                $params['namespace'] = $namespace;
            } else {
                $params['namespace'] = $this->namespace;
            }

            $params['region'] = $this->region;

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/allocations", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($allocation) => [
                'id' => $allocation['ID'],
                'evalId' => $allocation['EvalID'],
                'name' => $allocation['Name'],
                'namespace' => $allocation['Namespace'],
                'nodeId' => $allocation['NodeID'],
                'jobId' => $allocation['JobID'],
                'jobType' => $allocation['JobType'],
                'taskGroup' => $allocation['TaskGroup'],
                'desiredStatus' => $allocation['DesiredStatus'],
                'desiredDescription' => $allocation['DesiredDescription'],
                'clientStatus' => $allocation['ClientStatus'],
                'clientDescription' => $allocation['ClientDescription'],
                'createIndex' => $allocation['CreateIndex'],
                'modifyIndex' => $allocation['ModifyIndex'],
                'taskStates' => array_map(fn ($taskState) => [
                    'state' => $taskState['State'],
                    'failed' => $taskState['Failed'],
                    'restarts' => $taskState['Restarts'],
                    'lastRestart' => $taskState['LastRestart'],
                    'startedAt' => $taskState['StartedAt'],
                    'finishedAt' => $taskState['FinishedAt'],
                ], $allocation['TaskStates']),
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Nomad nodes.
     *
     * @param string $prefix Node name prefix filter
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     datacenter: string,
     *     nodeClass: string,
     *     drain: bool,
     *     status: string,
     *     statusDescription: string,
     *     createIndex: int,
     *     modifyIndex: int,
     *     nodeResources: array{
     *         cpu: int,
     *         memoryMB: int,
     *         diskMB: int,
     *         networks: array<int, array{
     *             device: string,
     *             cidr: string,
     *             ip: string,
     *             mbits: int,
     *         }>,
     *     },
     *     reservedResources: array{
     *         cpu: int,
     *         memoryMB: int,
     *         diskMB: int,
     *         networks: array<int, array{
     *             device: string,
     *             cidr: string,
     *             ip: string,
     *             mbits: int,
     *         }>,
     *     },
     * }>
     */
    public function getNodes(string $prefix = ''): array
    {
        try {
            $params = [
                'region' => $this->region,
            ];

            if ($prefix) {
                $params['prefix'] = $prefix;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/nodes", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($node) => [
                'id' => $node['ID'],
                'name' => $node['Name'],
                'datacenter' => $node['Datacenter'],
                'nodeClass' => $node['NodeClass'],
                'drain' => $node['Drain'],
                'status' => $node['Status'],
                'statusDescription' => $node['StatusDescription'],
                'createIndex' => $node['CreateIndex'],
                'modifyIndex' => $node['ModifyIndex'],
                'nodeResources' => [
                    'cpu' => $node['NodeResources']['CPU'],
                    'memoryMB' => $node['NodeResources']['MemoryMB'],
                    'diskMB' => $node['NodeResources']['DiskMB'],
                    'networks' => array_map(fn ($network) => [
                        'device' => $network['Device'],
                        'cidr' => $network['CIDR'],
                        'ip' => $network['IP'],
                        'mbits' => $network['MBits'],
                    ], $node['NodeResources']['Networks']),
                ],
                'reservedResources' => [
                    'cpu' => $node['ReservedResources']['CPU'],
                    'memoryMB' => $node['ReservedResources']['MemoryMB'],
                    'diskMB' => $node['ReservedResources']['DiskMB'],
                    'networks' => array_map(fn ($network) => [
                        'device' => $network['Device'],
                        'cidr' => $network['CIDR'],
                        'ip' => $network['IP'],
                        'mbits' => $network['MBits'],
                    ], $node['ReservedResources']['Networks']),
                ],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Nomad evaluations.
     *
     * @param string $jobId     Job ID filter
     * @param string $namespace Namespace filter
     *
     * @return array<int, array{
     *     id: string,
     *     priority: int,
     *     type: string,
     *     triggeredBy: string,
     *     jobId: string,
     *     jobModifyIndex: int,
     *     nodeId: string,
     *     nodeModifyIndex: int,
     *     status: string,
     *     statusDescription: string,
     *     wait: int,
     *     waitUntil: string,
     *     nextEval: string,
     *     previousEval: string,
     *     blockedEval: string,
     *     createIndex: int,
     *     modifyIndex: int,
     * }>
     */
    public function getEvaluations(
        string $jobId = '',
        string $namespace = '',
    ): array {
        try {
            $params = [];

            if ($jobId) {
                $params['job'] = $jobId;
            }

            if ($namespace) {
                $params['namespace'] = $namespace;
            } else {
                $params['namespace'] = $this->namespace;
            }

            $params['region'] = $this->region;

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/evaluations", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($eval) => [
                'id' => $eval['ID'],
                'priority' => $eval['Priority'],
                'type' => $eval['Type'],
                'triggeredBy' => $eval['TriggeredBy'],
                'jobId' => $eval['JobID'],
                'jobModifyIndex' => $eval['JobModifyIndex'],
                'nodeId' => $eval['NodeID'],
                'nodeModifyIndex' => $eval['NodeModifyIndex'],
                'status' => $eval['Status'],
                'statusDescription' => $eval['StatusDescription'],
                'wait' => $eval['Wait'],
                'waitUntil' => $eval['WaitUntil'],
                'nextEval' => $eval['NextEval'],
                'previousEval' => $eval['PreviousEval'],
                'blockedEval' => $eval['BlockedEval'],
                'createIndex' => $eval['CreateIndex'],
                'modifyIndex' => $eval['ModifyIndex'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
