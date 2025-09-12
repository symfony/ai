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
#[AsTool('asana_get_tasks', 'Tool that gets Asana tasks')]
#[AsTool('asana_create_task', 'Tool that creates Asana tasks', method: 'createTask')]
#[AsTool('asana_update_task', 'Tool that updates Asana tasks', method: 'updateTask')]
#[AsTool('asana_get_projects', 'Tool that gets Asana projects', method: 'getProjects')]
#[AsTool('asana_get_workspaces', 'Tool that gets Asana workspaces', method: 'getWorkspaces')]
#[AsTool('asana_get_users', 'Tool that gets Asana users', method: 'getUsers')]
final readonly class Asana
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = '1.0',
        private array $options = [],
    ) {
    }

    /**
     * Get Asana tasks.
     *
     * @param string $projectId Project ID to filter tasks
     * @param string $assignee  Assignee ID
     * @param string $workspace Workspace ID
     * @param bool   $completed Include completed tasks
     * @param int    $limit     Number of tasks to retrieve
     * @param string $offset    Pagination offset
     *
     * @return array<int, array{
     *     gid: string,
     *     name: string,
     *     resource_type: string,
     *     notes: string,
     *     completed: bool,
     *     assignee: array{gid: string, name: string, email: string}|null,
     *     assignee_status: string,
     *     completed_at: string|null,
     *     completed_by: array{gid: string, name: string}|null,
     *     created_at: string,
     *     due_on: string|null,
     *     due_at: string|null,
     *     followers: array<int, array{gid: string, name: string}>,
     *     modified_at: string,
     *     num_hearts: int,
     *     num_likes: int,
     *     projects: array<int, array{gid: string, name: string}>,
     *     tags: array<int, array{gid: string, name: string}>,
     *     workspace: array{gid: string, name: string},
     *     permalink_url: string,
     *     html_notes: string,
     * }>
     */
    public function __invoke(
        string $projectId = '',
        string $assignee = '',
        string $workspace = '',
        bool $completed = false,
        int $limit = 50,
        string $offset = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
                'opt_fields' => 'gid,name,notes,completed,assignee,assignee_status,completed_at,completed_by,created_at,due_on,due_at,followers,modified_at,num_hearts,num_likes,projects,tags,workspace,permalink_url,html_notes',
            ];

            if ($projectId) {
                $params['project'] = $projectId;
            }
            if ($assignee) {
                $params['assignee'] = $assignee;
            }
            if ($workspace) {
                $params['workspace'] = $workspace;
            }
            if ($completed) {
                $params['completed_since'] = '2012-02-22T02:06:58.147Z';
            }
            if ($offset) {
                $params['offset'] = $offset;
            }

            $response = $this->httpClient->request('GET', "https://app.asana.com/api/{$this->apiVersion}/tasks", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return [];
            }

            return array_map(fn ($task) => [
                'gid' => $task['gid'],
                'name' => $task['name'],
                'resource_type' => $task['resource_type'],
                'notes' => $task['notes'] ?? '',
                'completed' => $task['completed'],
                'assignee' => $task['assignee'] ? [
                    'gid' => $task['assignee']['gid'],
                    'name' => $task['assignee']['name'],
                    'email' => $task['assignee']['email'] ?? '',
                ] : null,
                'assignee_status' => $task['assignee_status'] ?? '',
                'completed_at' => $task['completed_at'],
                'completed_by' => $task['completed_by'] ? [
                    'gid' => $task['completed_by']['gid'],
                    'name' => $task['completed_by']['name'],
                ] : null,
                'created_at' => $task['created_at'],
                'due_on' => $task['due_on'],
                'due_at' => $task['due_at'],
                'followers' => array_map(fn ($follower) => [
                    'gid' => $follower['gid'],
                    'name' => $follower['name'],
                ], $task['followers'] ?? []),
                'modified_at' => $task['modified_at'],
                'num_hearts' => $task['num_hearts'] ?? 0,
                'num_likes' => $task['num_likes'] ?? 0,
                'projects' => array_map(fn ($project) => [
                    'gid' => $project['gid'],
                    'name' => $project['name'],
                ], $task['projects'] ?? []),
                'tags' => array_map(fn ($tag) => [
                    'gid' => $tag['gid'],
                    'name' => $tag['name'],
                ], $task['tags'] ?? []),
                'workspace' => [
                    'gid' => $task['workspace']['gid'],
                    'name' => $task['workspace']['name'],
                ],
                'permalink_url' => $task['permalink_url'],
                'html_notes' => $task['html_notes'] ?? '',
            ], $data['data'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create an Asana task.
     *
     * @param string             $name      Task name
     * @param string             $notes     Task notes
     * @param string             $workspace Workspace ID
     * @param string             $project   Project ID
     * @param string             $assignee  Assignee ID
     * @param string             $dueOn     Due date (YYYY-MM-DD)
     * @param array<int, string> $tags      Tag IDs
     * @param bool               $completed Whether task is completed
     *
     * @return array{
     *     gid: string,
     *     name: string,
     *     resource_type: string,
     *     notes: string,
     *     completed: bool,
     *     assignee: array{gid: string, name: string, email: string}|null,
     *     created_at: string,
     *     due_on: string|null,
     *     projects: array<int, array{gid: string, name: string}>,
     *     tags: array<int, array{gid: string, name: string}>,
     *     workspace: array{gid: string, name: string},
     *     permalink_url: string,
     * }|string
     */
    public function createTask(
        string $name,
        string $notes = '',
        string $workspace = '',
        string $project = '',
        string $assignee = '',
        string $dueOn = '',
        array $tags = [],
        bool $completed = false,
    ): array|string {
        try {
            $payload = [
                'data' => [
                    'name' => $name,
                    'completed' => $completed,
                ],
            ];

            if ($notes) {
                $payload['data']['notes'] = $notes;
            }
            if ($workspace) {
                $payload['data']['workspace'] = $workspace;
            }
            if ($project) {
                $payload['data']['projects'] = [$project];
            }
            if ($assignee) {
                $payload['data']['assignee'] = $assignee;
            }
            if ($dueOn) {
                $payload['data']['due_on'] = $dueOn;
            }
            if (!empty($tags)) {
                $payload['data']['tags'] = $tags;
            }

            $response = $this->httpClient->request('POST', "https://app.asana.com/api/{$this->apiVersion}/tasks", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error creating task: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $task = $data['data'];

            return [
                'gid' => $task['gid'],
                'name' => $task['name'],
                'resource_type' => $task['resource_type'],
                'notes' => $task['notes'] ?? '',
                'completed' => $task['completed'],
                'assignee' => $task['assignee'] ? [
                    'gid' => $task['assignee']['gid'],
                    'name' => $task['assignee']['name'],
                    'email' => $task['assignee']['email'] ?? '',
                ] : null,
                'created_at' => $task['created_at'],
                'due_on' => $task['due_on'],
                'projects' => array_map(fn ($project) => [
                    'gid' => $project['gid'],
                    'name' => $project['name'],
                ], $task['projects'] ?? []),
                'tags' => array_map(fn ($tag) => [
                    'gid' => $tag['gid'],
                    'name' => $tag['name'],
                ], $task['tags'] ?? []),
                'workspace' => [
                    'gid' => $task['workspace']['gid'],
                    'name' => $task['workspace']['name'],
                ],
                'permalink_url' => $task['permalink_url'],
            ];
        } catch (\Exception $e) {
            return 'Error creating task: '.$e->getMessage();
        }
    }

    /**
     * Update an Asana task.
     *
     * @param string $taskGid   Task GID to update
     * @param string $name      New name (optional)
     * @param string $notes     New notes (optional)
     * @param string $assignee  New assignee (optional)
     * @param string $dueOn     New due date (optional)
     * @param bool   $completed New completion status (optional)
     */
    public function updateTask(
        string $taskGid,
        string $name = '',
        string $notes = '',
        string $assignee = '',
        string $dueOn = '',
        bool $completed = false,
    ): string {
        try {
            $payload = ['data' => []];

            if ($name) {
                $payload['data']['name'] = $name;
            }
            if ($notes) {
                $payload['data']['notes'] = $notes;
            }
            if ($assignee) {
                $payload['data']['assignee'] = $assignee;
            }
            if ($dueOn) {
                $payload['data']['due_on'] = $dueOn;
            }
            $payload['data']['completed'] = $completed;

            $response = $this->httpClient->request('PUT', "https://app.asana.com/api/{$this->apiVersion}/tasks/{$taskGid}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error updating task: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            return 'Task updated successfully';
        } catch (\Exception $e) {
            return 'Error updating task: '.$e->getMessage();
        }
    }

    /**
     * Get Asana projects.
     *
     * @param string $workspace Workspace ID
     * @param bool   $archived  Include archived projects
     * @param int    $limit     Number of projects to retrieve
     *
     * @return array<int, array{
     *     gid: string,
     *     name: string,
     *     resource_type: string,
     *     notes: string,
     *     color: string,
     *     archived: bool,
     *     created_at: string,
     *     modified_at: string,
     *     owner: array{gid: string, name: string, email: string},
     *     team: array{gid: string, name: string},
     *     workspace: array{gid: string, name: string},
     *     permalink_url: string,
     *     html_notes: string,
     * }>
     */
    public function getProjects(
        string $workspace = '',
        bool $archived = false,
        int $limit = 50,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
                'opt_fields' => 'gid,name,notes,color,archived,created_at,modified_at,owner,team,workspace,permalink_url,html_notes',
            ];

            if ($workspace) {
                $params['workspace'] = $workspace;
            }
            if ($archived) {
                $params['archived'] = $archived;
            }

            $response = $this->httpClient->request('GET', "https://app.asana.com/api/{$this->apiVersion}/projects", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return [];
            }

            return array_map(fn ($project) => [
                'gid' => $project['gid'],
                'name' => $project['name'],
                'resource_type' => $project['resource_type'],
                'notes' => $project['notes'] ?? '',
                'color' => $project['color'] ?? '',
                'archived' => $project['archived'],
                'created_at' => $project['created_at'],
                'modified_at' => $project['modified_at'],
                'owner' => [
                    'gid' => $project['owner']['gid'],
                    'name' => $project['owner']['name'],
                    'email' => $project['owner']['email'] ?? '',
                ],
                'team' => [
                    'gid' => $project['team']['gid'],
                    'name' => $project['team']['name'],
                ],
                'workspace' => [
                    'gid' => $project['workspace']['gid'],
                    'name' => $project['workspace']['name'],
                ],
                'permalink_url' => $project['permalink_url'],
                'html_notes' => $project['html_notes'] ?? '',
            ], $data['data'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Asana workspaces.
     *
     * @return array<int, array{
     *     gid: string,
     *     name: string,
     *     resource_type: string,
     *     is_organization: bool,
     * }>
     */
    public function getWorkspaces(): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://app.asana.com/api/{$this->apiVersion}/workspaces", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return [];
            }

            return array_map(fn ($workspace) => [
                'gid' => $workspace['gid'],
                'name' => $workspace['name'],
                'resource_type' => $workspace['resource_type'],
                'is_organization' => $workspace['is_organization'],
            ], $data['data'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Asana users.
     *
     * @param string $workspace Workspace ID
     * @param int    $limit     Number of users to retrieve
     *
     * @return array<int, array{
     *     gid: string,
     *     name: string,
     *     resource_type: string,
     *     email: string,
     *     photo: array{gid: string, image_128x128: string}|null,
     *     workspaces: array<int, array{gid: string, name: string}>,
     * }>
     */
    public function getUsers(
        string $workspace = '',
        int $limit = 50,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
                'opt_fields' => 'gid,name,email,photo,workspaces',
            ];

            if ($workspace) {
                $params['workspace'] = $workspace;
            }

            $response = $this->httpClient->request('GET', "https://app.asana.com/api/{$this->apiVersion}/users", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return [];
            }

            return array_map(fn ($user) => [
                'gid' => $user['gid'],
                'name' => $user['name'],
                'resource_type' => $user['resource_type'],
                'email' => $user['email'] ?? '',
                'photo' => $user['photo'] ? [
                    'gid' => $user['photo']['gid'],
                    'image_128x128' => $user['photo']['image_128x128'],
                ] : null,
                'workspaces' => array_map(fn ($workspace) => [
                    'gid' => $workspace['gid'],
                    'name' => $workspace['name'],
                ], $user['workspaces'] ?? []),
            ], $data['data'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }
}
