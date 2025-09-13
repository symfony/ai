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
#[AsTool('render_deploy_service', 'Tool that deploys services to Render')]
#[AsTool('render_get_service', 'Tool that gets Render service details', method: 'getService')]
#[AsTool('render_list_services', 'Tool that lists Render services', method: 'listServices')]
#[AsTool('render_delete_service', 'Tool that deletes Render services', method: 'deleteService')]
#[AsTool('render_get_logs', 'Tool that gets Render service logs', method: 'getLogs')]
#[AsTool('render_suspend_service', 'Tool that suspends Render services', method: 'suspendService')]
#[AsTool('render_resume_service', 'Tool that resumes Render services', method: 'resumeService')]
#[AsTool('render_get_deployments', 'Tool that gets Render deployments', method: 'getDeployments')]
final readonly class Render
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.render.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Deploy service to Render.
     *
     * @param string               $name                 Service name
     * @param string               $repo                 Repository URL
     * @param string               $branch               Branch name
     * @param string               $buildCommand         Build command
     * @param string               $startCommand         Start command
     * @param string               $serviceType          Service type (web_service, static_site, private_service, background_worker, cron_job)
     * @param string               $runtime              Runtime (node, python, ruby, go, docker, etc.)
     * @param array<string, mixed> $environmentVariables Environment variables
     * @param array<string, mixed> $envVars              Environment variables (alias)
     *
     * @return array{
     *     success: bool,
     *     service: array{
     *         id: string,
     *         name: string,
     *         type: string,
     *         repo: string,
     *         branch: string,
     *         buildCommand: string,
     *         startCommand: string,
     *         runtime: string,
     *         status: string,
     *         url: string,
     *         createdAt: string,
     *         updatedAt: string,
     *         environmentVariables: array<string, mixed>,
     *     },
     *     deployment: array{
     *         id: string,
     *         commit: string,
     *         status: string,
     *         createdAt: string,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(
        string $name,
        string $repo,
        string $branch = 'main',
        string $buildCommand = '',
        string $startCommand = '',
        string $serviceType = 'web_service',
        string $runtime = 'node',
        array $environmentVariables = [],
        array $envVars = [],
    ): array {
        try {
            $env = array_merge($environmentVariables, $envVars);

            $requestData = [
                'name' => $name,
                'type' => $serviceType,
                'repo' => $repo,
                'branch' => $branch,
                'runtime' => $runtime,
                'buildCommand' => $buildCommand,
                'startCommand' => $startCommand,
                'envVars' => $env,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/services", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $service = $data['service'] ?? [];
            $deployment = $data['deployment'] ?? [];

            return [
                'success' => true,
                'service' => [
                    'id' => $service['id'] ?? '',
                    'name' => $service['name'] ?? $name,
                    'type' => $service['type'] ?? $serviceType,
                    'repo' => $service['repo'] ?? $repo,
                    'branch' => $service['branch'] ?? $branch,
                    'buildCommand' => $service['buildCommand'] ?? $buildCommand,
                    'startCommand' => $service['startCommand'] ?? $startCommand,
                    'runtime' => $service['runtime'] ?? $runtime,
                    'status' => $service['status'] ?? 'building',
                    'url' => $service['url'] ?? '',
                    'createdAt' => $service['createdAt'] ?? date('c'),
                    'updatedAt' => $service['updatedAt'] ?? date('c'),
                    'environmentVariables' => $service['envVars'] ?? $env,
                ],
                'deployment' => [
                    'id' => $deployment['id'] ?? '',
                    'commit' => $deployment['commit'] ?? '',
                    'status' => $deployment['status'] ?? 'building',
                    'createdAt' => $deployment['createdAt'] ?? date('c'),
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'service' => [
                    'id' => '',
                    'name' => $name,
                    'type' => $serviceType,
                    'repo' => $repo,
                    'branch' => $branch,
                    'buildCommand' => $buildCommand,
                    'startCommand' => $startCommand,
                    'runtime' => $runtime,
                    'status' => 'error',
                    'url' => '',
                    'createdAt' => '',
                    'updatedAt' => '',
                    'environmentVariables' => $env,
                ],
                'deployment' => [
                    'id' => '',
                    'commit' => '',
                    'status' => 'error',
                    'createdAt' => '',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Render service details.
     *
     * @param string $serviceId Service ID
     *
     * @return array{
     *     success: bool,
     *     service: array{
     *         id: string,
     *         name: string,
     *         type: string,
     *         repo: string,
     *         branch: string,
     *         buildCommand: string,
     *         startCommand: string,
     *         runtime: string,
     *         status: string,
     *         url: string,
     *         createdAt: string,
     *         updatedAt: string,
     *         environmentVariables: array<string, mixed>,
     *         lastDeploy: array{
     *             id: string,
     *             commit: string,
     *             status: string,
     *             createdAt: string,
     *         },
     *     },
     *     error: string,
     * }
     */
    public function getService(string $serviceId): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/services/{$serviceId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
            ] + $this->options);

            $data = $response->toArray();
            $service = $data['service'] ?? [];

            return [
                'success' => true,
                'service' => [
                    'id' => $service['id'] ?? $serviceId,
                    'name' => $service['name'] ?? '',
                    'type' => $service['type'] ?? '',
                    'repo' => $service['repo'] ?? '',
                    'branch' => $service['branch'] ?? '',
                    'buildCommand' => $service['buildCommand'] ?? '',
                    'startCommand' => $service['startCommand'] ?? '',
                    'runtime' => $service['runtime'] ?? '',
                    'status' => $service['status'] ?? '',
                    'url' => $service['url'] ?? '',
                    'createdAt' => $service['createdAt'] ?? '',
                    'updatedAt' => $service['updatedAt'] ?? '',
                    'environmentVariables' => $service['envVars'] ?? [],
                    'lastDeploy' => [
                        'id' => $service['lastDeploy']['id'] ?? '',
                        'commit' => $service['lastDeploy']['commit'] ?? '',
                        'status' => $service['lastDeploy']['status'] ?? '',
                        'createdAt' => $service['lastDeploy']['createdAt'] ?? '',
                    ],
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'service' => [
                    'id' => $serviceId,
                    'name' => '',
                    'type' => '',
                    'repo' => '',
                    'branch' => '',
                    'buildCommand' => '',
                    'startCommand' => '',
                    'runtime' => '',
                    'status' => 'error',
                    'url' => '',
                    'createdAt' => '',
                    'updatedAt' => '',
                    'environmentVariables' => [],
                    'lastDeploy' => [
                        'id' => '',
                        'commit' => '',
                        'status' => '',
                        'createdAt' => '',
                    ],
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List Render services.
     *
     * @param int    $limit  Number of services to return
     * @param int    $offset Offset for pagination
     * @param string $type   Service type filter
     *
     * @return array{
     *     success: bool,
     *     services: array<int, array{
     *         id: string,
     *         name: string,
     *         type: string,
     *         repo: string,
     *         branch: string,
     *         runtime: string,
     *         status: string,
     *         url: string,
     *         createdAt: string,
     *         updatedAt: string,
     *     }>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     error: string,
     * }
     */
    public function listServices(
        int $limit = 20,
        int $offset = 0,
        string $type = '',
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 100)),
                'offset' => max(0, $offset),
            ];

            if ($type) {
                $params['type'] = $type;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/services", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'services' => array_map(fn ($service) => [
                    'id' => $service['id'] ?? '',
                    'name' => $service['name'] ?? '',
                    'type' => $service['type'] ?? '',
                    'repo' => $service['repo'] ?? '',
                    'branch' => $service['branch'] ?? '',
                    'runtime' => $service['runtime'] ?? '',
                    'status' => $service['status'] ?? '',
                    'url' => $service['url'] ?? '',
                    'createdAt' => $service['createdAt'] ?? '',
                    'updatedAt' => $service['updatedAt'] ?? '',
                ], $data['services'] ?? []),
                'total' => $data['total'] ?? 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'services' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete Render service.
     *
     * @param string $serviceId Service ID
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     error: string,
     * }
     */
    public function deleteService(string $serviceId): array
    {
        try {
            $response = $this->httpClient->request('DELETE', "{$this->baseUrl}/services/{$serviceId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
            ] + $this->options);

            return [
                'success' => true,
                'message' => 'Service deleted successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete service',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Render service logs.
     *
     * @param string $serviceId Service ID
     * @param string $startTime Start time (ISO 8601 format)
     * @param string $endTime   End time (ISO 8601 format)
     * @param int    $limit     Number of log entries
     *
     * @return array{
     *     success: bool,
     *     logs: array<int, array{
     *         id: string,
     *         message: string,
     *         level: string,
     *         timestamp: string,
     *         source: string,
     *     }>,
     *     serviceId: string,
     *     total: int,
     *     error: string,
     * }
     */
    public function getLogs(
        string $serviceId,
        string $startTime = '',
        string $endTime = '',
        int $limit = 100,
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 1000)),
            ];

            if ($startTime) {
                $params['startTime'] = $startTime;
            }

            if ($endTime) {
                $params['endTime'] = $endTime;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/services/{$serviceId}/logs", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'logs' => array_map(fn ($log) => [
                    'id' => $log['id'] ?? '',
                    'message' => $log['message'] ?? '',
                    'level' => $log['level'] ?? 'info',
                    'timestamp' => $log['timestamp'] ?? '',
                    'source' => $log['source'] ?? '',
                ], $data['logs'] ?? []),
                'serviceId' => $serviceId,
                'total' => $data['total'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'logs' => [],
                'serviceId' => $serviceId,
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Suspend Render service.
     *
     * @param string $serviceId Service ID
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     error: string,
     * }
     */
    public function suspendService(string $serviceId): array
    {
        try {
            $response = $this->httpClient->request('POST', "{$this->baseUrl}/services/{$serviceId}/suspend", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
            ] + $this->options);

            return [
                'success' => true,
                'message' => 'Service suspended successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to suspend service',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resume Render service.
     *
     * @param string $serviceId Service ID
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     error: string,
     * }
     */
    public function resumeService(string $serviceId): array
    {
        try {
            $response = $this->httpClient->request('POST', "{$this->baseUrl}/services/{$serviceId}/resume", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
            ] + $this->options);

            return [
                'success' => true,
                'message' => 'Service resumed successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to resume service',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Render deployments.
     *
     * @param string $serviceId Service ID
     * @param int    $limit     Number of deployments
     * @param int    $offset    Offset for pagination
     *
     * @return array{
     *     success: bool,
     *     deployments: array<int, array{
     *         id: string,
     *         commit: string,
     *         status: string,
     *         createdAt: string,
     *         finishedAt: string,
     *         buildCommand: string,
     *         startCommand: string,
     *         environmentVariables: array<string, mixed>,
     *         logs: string,
     *     }>,
     *     serviceId: string,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     error: string,
     * }
     */
    public function getDeployments(
        string $serviceId,
        int $limit = 20,
        int $offset = 0,
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 100)),
                'offset' => max(0, $offset),
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/services/{$serviceId}/deployments", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'deployments' => array_map(fn ($deployment) => [
                    'id' => $deployment['id'] ?? '',
                    'commit' => $deployment['commit'] ?? '',
                    'status' => $deployment['status'] ?? '',
                    'createdAt' => $deployment['createdAt'] ?? '',
                    'finishedAt' => $deployment['finishedAt'] ?? '',
                    'buildCommand' => $deployment['buildCommand'] ?? '',
                    'startCommand' => $deployment['startCommand'] ?? '',
                    'environmentVariables' => $deployment['envVars'] ?? [],
                    'logs' => $deployment['logs'] ?? '',
                ], $data['deployments'] ?? []),
                'serviceId' => $serviceId,
                'total' => $data['total'] ?? 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'deployments' => [],
                'serviceId' => $serviceId,
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage(),
            ];
        }
    }
}
