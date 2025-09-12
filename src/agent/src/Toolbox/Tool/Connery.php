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
#[AsTool('connery_execute', 'Tool that executes actions using Connery')]
#[AsTool('connery_list_actions', 'Tool that lists available actions', method: 'listActions')]
#[AsTool('connery_get_action', 'Tool that gets action details', method: 'getAction')]
#[AsTool('connery_create_workflow', 'Tool that creates workflows', method: 'createWorkflow')]
#[AsTool('connery_execute_workflow', 'Tool that executes workflows', method: 'executeWorkflow')]
#[AsTool('connery_list_workflows', 'Tool that lists workflows', method: 'listWorkflows')]
#[AsTool('connery_get_workflow', 'Tool that gets workflow details', method: 'getWorkflow')]
#[AsTool('connery_run_recipe', 'Tool that runs recipes', method: 'runRecipe')]
final readonly class Connery
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.connery.io/v1',
        private array $options = [],
    ) {
    }

    /**
     * Execute actions using Connery.
     *
     * @param string               $actionId   Action ID to execute
     * @param array<string, mixed> $parameters Action parameters
     * @param array<string, mixed> $context    Execution context
     *
     * @return array{
     *     success: bool,
     *     execution: array{
     *         execution_id: string,
     *         action_id: string,
     *         parameters: array<string, mixed>,
     *         status: string,
     *         result: array<string, mixed>,
     *         started_at: string,
     *         completed_at: string,
     *         duration: float,
     *         logs: array<int, array{
     *             level: string,
     *             message: string,
     *             timestamp: string,
     *         }>,
     *         error: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $actionId,
        array $parameters = [],
        array $context = [],
    ): array {
        try {
            $requestData = [
                'action_id' => $actionId,
                'parameters' => $parameters,
                'context' => $context,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/actions/{$actionId}/execute", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $execution = $responseData['execution'] ?? [];

            return [
                'success' => true,
                'execution' => [
                    'execution_id' => $execution['execution_id'] ?? '',
                    'action_id' => $actionId,
                    'parameters' => $parameters,
                    'status' => $execution['status'] ?? 'completed',
                    'result' => $execution['result'] ?? [],
                    'started_at' => $execution['started_at'] ?? date('c'),
                    'completed_at' => $execution['completed_at'] ?? date('c'),
                    'duration' => $execution['duration'] ?? 0.0,
                    'logs' => array_map(fn ($log) => [
                        'level' => $log['level'] ?? 'info',
                        'message' => $log['message'] ?? '',
                        'timestamp' => $log['timestamp'] ?? date('c'),
                    ], $execution['logs'] ?? []),
                    'error' => $execution['error'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'execution' => [
                    'execution_id' => '',
                    'action_id' => $actionId,
                    'parameters' => $parameters,
                    'status' => 'failed',
                    'result' => [],
                    'started_at' => date('c'),
                    'completed_at' => date('c'),
                    'duration' => 0.0,
                    'logs' => [],
                    'error' => $e->getMessage(),
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List available actions.
     *
     * @param string $category Action category filter
     * @param string $provider Provider filter
     * @param int    $limit    Number of actions to return
     * @param int    $offset   Offset for pagination
     *
     * @return array{
     *     success: bool,
     *     actions: array<int, array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         category: string,
     *         provider: string,
     *         parameters: array<int, array{
     *             name: string,
     *             type: string,
     *             required: bool,
     *             description: string,
     *         }>,
     *         output_schema: array<string, mixed>,
     *         tags: array<int, string>,
     *     }>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function listActions(
        string $category = '',
        string $provider = '',
        int $limit = 20,
        int $offset = 0,
    ): array {
        try {
            $query = [];
            if ($category) {
                $query['category'] = $category;
            }
            if ($provider) {
                $query['provider'] = $provider;
            }
            $query['limit'] = max(1, min($limit, 100));
            $query['offset'] = max(0, $offset);

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/actions", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'query' => $query,
            ] + $this->options);

            $responseData = $response->toArray();
            $actions = $responseData['actions'] ?? [];

            return [
                'success' => true,
                'actions' => array_map(fn ($action) => [
                    'id' => $action['id'] ?? '',
                    'name' => $action['name'] ?? '',
                    'description' => $action['description'] ?? '',
                    'category' => $action['category'] ?? '',
                    'provider' => $action['provider'] ?? '',
                    'parameters' => array_map(fn ($param) => [
                        'name' => $param['name'] ?? '',
                        'type' => $param['type'] ?? 'string',
                        'required' => $param['required'] ?? false,
                        'description' => $param['description'] ?? '',
                    ], $action['parameters'] ?? []),
                    'output_schema' => $action['output_schema'] ?? [],
                    'tags' => $action['tags'] ?? [],
                ], $actions),
                'total' => $responseData['total'] ?? \count($actions),
                'limit' => $limit,
                'offset' => $offset,
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'actions' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get action details.
     *
     * @param string $actionId Action ID
     *
     * @return array{
     *     success: bool,
     *     action: array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         category: string,
     *         provider: string,
     *         parameters: array<int, array{
     *             name: string,
     *             type: string,
     *             required: bool,
     *             description: string,
     *             default_value: mixed,
     *         }>,
     *         output_schema: array<string, mixed>,
     *         tags: array<int, string>,
     *         version: string,
     *         documentation: string,
     *         examples: array<int, array{
     *             name: string,
     *             parameters: array<string, mixed>,
     *             result: array<string, mixed>,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function getAction(string $actionId): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/actions/{$actionId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ] + $this->options);

            $responseData = $response->toArray();
            $action = $responseData['action'] ?? [];

            return [
                'success' => true,
                'action' => [
                    'id' => $actionId,
                    'name' => $action['name'] ?? '',
                    'description' => $action['description'] ?? '',
                    'category' => $action['category'] ?? '',
                    'provider' => $action['provider'] ?? '',
                    'parameters' => array_map(fn ($param) => [
                        'name' => $param['name'] ?? '',
                        'type' => $param['type'] ?? 'string',
                        'required' => $param['required'] ?? false,
                        'description' => $param['description'] ?? '',
                        'default_value' => $param['default_value'] ?? null,
                    ], $action['parameters'] ?? []),
                    'output_schema' => $action['output_schema'] ?? [],
                    'tags' => $action['tags'] ?? [],
                    'version' => $action['version'] ?? '1.0.0',
                    'documentation' => $action['documentation'] ?? '',
                    'examples' => array_map(fn ($example) => [
                        'name' => $example['name'] ?? '',
                        'parameters' => $example['parameters'] ?? [],
                        'result' => $example['result'] ?? [],
                    ], $action['examples'] ?? []),
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'action' => [
                    'id' => $actionId,
                    'name' => '',
                    'description' => '',
                    'category' => '',
                    'provider' => '',
                    'parameters' => [],
                    'output_schema' => [],
                    'tags' => [],
                    'version' => '1.0.0',
                    'documentation' => '',
                    'examples' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create workflow.
     *
     * @param string $name        Workflow name
     * @param string $description Workflow description
     * @param array<int, array{
     *     action_id: string,
     *     parameters: array<string, mixed>,
     *     conditions: array<string, mixed>,
     * }> $steps Workflow steps
     * @param array<string, mixed> $settings Workflow settings
     *
     * @return array{
     *     success: bool,
     *     workflow: array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         steps: array<int, array{
     *             step_id: string,
     *             action_id: string,
     *             parameters: array<string, mixed>,
     *             conditions: array<string, mixed>,
     *             order: int,
     *         }>,
     *         settings: array<string, mixed>,
     *         status: string,
     *         created_at: string,
     *         updated_at: string,
     *         version: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function createWorkflow(
        string $name,
        string $description,
        array $steps,
        array $settings = [],
    ): array {
        try {
            $requestData = [
                'name' => $name,
                'description' => $description,
                'steps' => $steps,
                'settings' => $settings,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/workflows", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $workflow = $responseData['workflow'] ?? [];

            return [
                'success' => true,
                'workflow' => [
                    'id' => $workflow['id'] ?? '',
                    'name' => $name,
                    'description' => $description,
                    'steps' => array_map(fn ($step, $index) => [
                        'step_id' => $step['step_id'] ?? "step_{$index}",
                        'action_id' => $step['action_id'],
                        'parameters' => $step['parameters'] ?? [],
                        'conditions' => $step['conditions'] ?? [],
                        'order' => $index + 1,
                    ], $steps),
                    'settings' => $settings,
                    'status' => $workflow['status'] ?? 'active',
                    'created_at' => $workflow['created_at'] ?? date('c'),
                    'updated_at' => $workflow['updated_at'] ?? date('c'),
                    'version' => $workflow['version'] ?? '1.0.0',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'workflow' => [
                    'id' => '',
                    'name' => $name,
                    'description' => $description,
                    'steps' => array_map(fn ($step, $index) => [
                        'step_id' => "step_{$index}",
                        'action_id' => $step['action_id'],
                        'parameters' => $step['parameters'] ?? [],
                        'conditions' => $step['conditions'] ?? [],
                        'order' => $index + 1,
                    ], $steps),
                    'settings' => $settings,
                    'status' => 'failed',
                    'created_at' => date('c'),
                    'updated_at' => date('c'),
                    'version' => '1.0.0',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute workflow.
     *
     * @param string               $workflowId Workflow ID to execute
     * @param array<string, mixed> $inputs     Workflow inputs
     * @param array<string, mixed> $context    Execution context
     *
     * @return array{
     *     success: bool,
     *     execution: array{
     *         execution_id: string,
     *         workflow_id: string,
     *         inputs: array<string, mixed>,
     *         status: string,
     *         results: array<int, array{
     *             step_id: string,
     *             status: string,
     *             result: array<string, mixed>,
     *             started_at: string,
     *             completed_at: string,
     *             duration: float,
     *         }>,
     *         started_at: string,
     *         completed_at: string,
     *         duration: float,
     *         logs: array<int, array{
     *             level: string,
     *             message: string,
     *             timestamp: string,
     *         }>,
     *         error: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function executeWorkflow(
        string $workflowId,
        array $inputs = [],
        array $context = [],
    ): array {
        try {
            $requestData = [
                'inputs' => $inputs,
                'context' => $context,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/workflows/{$workflowId}/execute", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $execution = $responseData['execution'] ?? [];

            return [
                'success' => true,
                'execution' => [
                    'execution_id' => $execution['execution_id'] ?? '',
                    'workflow_id' => $workflowId,
                    'inputs' => $inputs,
                    'status' => $execution['status'] ?? 'completed',
                    'results' => array_map(fn ($result) => [
                        'step_id' => $result['step_id'] ?? '',
                        'status' => $result['status'] ?? 'completed',
                        'result' => $result['result'] ?? [],
                        'started_at' => $result['started_at'] ?? date('c'),
                        'completed_at' => $result['completed_at'] ?? date('c'),
                        'duration' => $result['duration'] ?? 0.0,
                    ], $execution['results'] ?? []),
                    'started_at' => $execution['started_at'] ?? date('c'),
                    'completed_at' => $execution['completed_at'] ?? date('c'),
                    'duration' => $execution['duration'] ?? 0.0,
                    'logs' => array_map(fn ($log) => [
                        'level' => $log['level'] ?? 'info',
                        'message' => $log['message'] ?? '',
                        'timestamp' => $log['timestamp'] ?? date('c'),
                    ], $execution['logs'] ?? []),
                    'error' => $execution['error'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'execution' => [
                    'execution_id' => '',
                    'workflow_id' => $workflowId,
                    'inputs' => $inputs,
                    'status' => 'failed',
                    'results' => [],
                    'started_at' => date('c'),
                    'completed_at' => date('c'),
                    'duration' => 0.0,
                    'logs' => [],
                    'error' => $e->getMessage(),
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List workflows.
     *
     * @param string $status Workflow status filter
     * @param int    $limit  Number of workflows to return
     * @param int    $offset Offset for pagination
     *
     * @return array{
     *     success: bool,
     *     workflows: array<int, array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         status: string,
     *         steps_count: int,
     *         created_at: string,
     *         updated_at: string,
     *         version: string,
     *     }>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function listWorkflows(
        string $status = '',
        int $limit = 20,
        int $offset = 0,
    ): array {
        try {
            $query = [];
            if ($status) {
                $query['status'] = $status;
            }
            $query['limit'] = max(1, min($limit, 100));
            $query['offset'] = max(0, $offset);

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/workflows", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'query' => $query,
            ] + $this->options);

            $responseData = $response->toArray();
            $workflows = $responseData['workflows'] ?? [];

            return [
                'success' => true,
                'workflows' => array_map(fn ($workflow) => [
                    'id' => $workflow['id'] ?? '',
                    'name' => $workflow['name'] ?? '',
                    'description' => $workflow['description'] ?? '',
                    'status' => $workflow['status'] ?? 'active',
                    'steps_count' => $workflow['steps_count'] ?? 0,
                    'created_at' => $workflow['created_at'] ?? '',
                    'updated_at' => $workflow['updated_at'] ?? '',
                    'version' => $workflow['version'] ?? '1.0.0',
                ], $workflows),
                'total' => $responseData['total'] ?? \count($workflows),
                'limit' => $limit,
                'offset' => $offset,
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'workflows' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get workflow details.
     *
     * @param string $workflowId Workflow ID
     *
     * @return array{
     *     success: bool,
     *     workflow: array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         steps: array<int, array{
     *             step_id: string,
     *             action_id: string,
     *             parameters: array<string, mixed>,
     *             conditions: array<string, mixed>,
     *             order: int,
     *         }>,
     *         settings: array<string, mixed>,
     *         status: string,
     *         created_at: string,
     *         updated_at: string,
     *         version: string,
     *         executions_count: int,
     *         success_rate: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function getWorkflow(string $workflowId): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/workflows/{$workflowId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ] + $this->options);

            $responseData = $response->toArray();
            $workflow = $responseData['workflow'] ?? [];

            return [
                'success' => true,
                'workflow' => [
                    'id' => $workflowId,
                    'name' => $workflow['name'] ?? '',
                    'description' => $workflow['description'] ?? '',
                    'steps' => array_map(fn ($step) => [
                        'step_id' => $step['step_id'] ?? '',
                        'action_id' => $step['action_id'] ?? '',
                        'parameters' => $step['parameters'] ?? [],
                        'conditions' => $step['conditions'] ?? [],
                        'order' => $step['order'] ?? 0,
                    ], $workflow['steps'] ?? []),
                    'settings' => $workflow['settings'] ?? [],
                    'status' => $workflow['status'] ?? 'active',
                    'created_at' => $workflow['created_at'] ?? '',
                    'updated_at' => $workflow['updated_at'] ?? '',
                    'version' => $workflow['version'] ?? '1.0.0',
                    'executions_count' => $workflow['executions_count'] ?? 0,
                    'success_rate' => $workflow['success_rate'] ?? 0.0,
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'workflow' => [
                    'id' => $workflowId,
                    'name' => '',
                    'description' => '',
                    'steps' => [],
                    'settings' => [],
                    'status' => 'unknown',
                    'created_at' => '',
                    'updated_at' => '',
                    'version' => '1.0.0',
                    'executions_count' => 0,
                    'success_rate' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run recipe.
     *
     * @param string               $recipeId Recipe ID to run
     * @param array<string, mixed> $inputs   Recipe inputs
     * @param array<string, mixed> $context  Execution context
     *
     * @return array{
     *     success: bool,
     *     recipe_execution: array{
     *         execution_id: string,
     *         recipe_id: string,
     *         inputs: array<string, mixed>,
     *         status: string,
     *         result: array<string, mixed>,
     *         started_at: string,
     *         completed_at: string,
     *         duration: float,
     *         steps_executed: int,
     *         steps_total: int,
     *         logs: array<int, array{
     *             level: string,
     *             message: string,
     *             timestamp: string,
     *         }>,
     *         error: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function runRecipe(
        string $recipeId,
        array $inputs = [],
        array $context = [],
    ): array {
        try {
            $requestData = [
                'inputs' => $inputs,
                'context' => $context,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/recipes/{$recipeId}/run", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $responseData = $response->toArray();
            $execution = $responseData['recipe_execution'] ?? [];

            return [
                'success' => true,
                'recipe_execution' => [
                    'execution_id' => $execution['execution_id'] ?? '',
                    'recipe_id' => $recipeId,
                    'inputs' => $inputs,
                    'status' => $execution['status'] ?? 'completed',
                    'result' => $execution['result'] ?? [],
                    'started_at' => $execution['started_at'] ?? date('c'),
                    'completed_at' => $execution['completed_at'] ?? date('c'),
                    'duration' => $execution['duration'] ?? 0.0,
                    'steps_executed' => $execution['steps_executed'] ?? 0,
                    'steps_total' => $execution['steps_total'] ?? 0,
                    'logs' => array_map(fn ($log) => [
                        'level' => $log['level'] ?? 'info',
                        'message' => $log['message'] ?? '',
                        'timestamp' => $log['timestamp'] ?? date('c'),
                    ], $execution['logs'] ?? []),
                    'error' => $execution['error'] ?? '',
                ],
                'processingTime' => $responseData['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'recipe_execution' => [
                    'execution_id' => '',
                    'recipe_id' => $recipeId,
                    'inputs' => $inputs,
                    'status' => 'failed',
                    'result' => [],
                    'started_at' => date('c'),
                    'completed_at' => date('c'),
                    'duration' => 0.0,
                    'steps_executed' => 0,
                    'steps_total' => 0,
                    'logs' => [],
                    'error' => $e->getMessage(),
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
