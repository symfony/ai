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
#[AsTool('clickup_get_tasks', 'Tool that gets ClickUp tasks')]
#[AsTool('clickup_create_task', 'Tool that creates ClickUp tasks', method: 'createTask')]
#[AsTool('clickup_get_lists', 'Tool that gets ClickUp lists', method: 'getLists')]
#[AsTool('clickup_get_folders', 'Tool that gets ClickUp folders', method: 'getFolders')]
#[AsTool('clickup_get_spaces', 'Tool that gets ClickUp spaces', method: 'getSpaces')]
#[AsTool('clickup_update_task', 'Tool that updates ClickUp tasks', method: 'updateTask')]
final readonly class ClickUp
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiToken,
        private string $apiVersion = 'v2',
        private array $options = [],
    ) {
    }

    /**
     * Get ClickUp tasks.
     *
     * @param string $listId        List ID to filter tasks
     * @param string $assignee      Assignee ID
     * @param string $status        Task status
     * @param bool   $includeClosed Include closed tasks
     * @param int    $page          Page number
     * @param string $orderBy       Order by field
     * @param bool   $reverse       Reverse order
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     status: array{status: string, color: string, type: string, orderindex: int},
     *     orderindex: string,
     *     date_created: string,
     *     date_updated: string,
     *     date_closed: string|null,
     *     assignees: array<int, array{id: int, username: string, email: string, color: string}>,
     *     watchers: array<int, mixed>,
     *     checklists: array<int, mixed>,
     *     tags: array<int, array{name: string, tag_fg: string, tag_bg: string}>,
     *     url: string,
     *     list: array{id: string, name: string},
     *     folder: array{id: string, name: string},
     *     space: array{id: string},
     * }>|string
     */
    public function __invoke(
        string $listId = '',
        string $assignee = '',
        string $status = '',
        bool $includeClosed = false,
        int $page = 0,
        string $orderBy = 'created',
        bool $reverse = false,
    ): array|string {
        try {
            $params = [
                'page' => $page,
                'order_by' => $orderBy,
                'reverse' => $reverse,
                'include_closed' => $includeClosed,
            ];

            if ($assignee) {
                $params['assignees'] = [$assignee];
            }

            $url = $listId
                ? "https://api.clickup.com/api/{$this->apiVersion}/list/{$listId}/task"
                : "https://api.clickup.com/api/{$this->apiVersion}/team/{$this->options['team_id']}/task";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => $this->apiToken,
                ],
                'query' => array_merge($params, $this->options),
            ]);

            $data = $response->toArray();

            if (isset($data['err'])) {
                return 'Error getting tasks: '.($data['err'] ?? 'Unknown error');
            }

            $tasks = $data['tasks'] ?? [];

            return array_map(fn ($task) => [
                'id' => $task['id'],
                'name' => $task['name'],
                'description' => $task['description'] ?? '',
                'status' => $task['status'],
                'orderindex' => $task['orderindex'],
                'date_created' => $task['date_created'],
                'date_updated' => $task['date_updated'],
                'date_closed' => $task['date_closed'],
                'assignees' => array_map(fn ($assignee) => [
                    'id' => $assignee['id'],
                    'username' => $assignee['username'],
                    'email' => $assignee['email'],
                    'color' => $assignee['color'],
                ], $task['assignees'] ?? []),
                'watchers' => $task['watchers'] ?? [],
                'checklists' => $task['checklists'] ?? [],
                'tags' => array_map(fn ($tag) => [
                    'name' => $tag['name'],
                    'tag_fg' => $tag['tag_fg'],
                    'tag_bg' => $tag['tag_bg'],
                ], $task['tags'] ?? []),
                'url' => $task['url'],
                'list' => [
                    'id' => $task['list']['id'],
                    'name' => $task['list']['name'],
                ],
                'folder' => [
                    'id' => $task['folder']['id'],
                    'name' => $task['folder']['name'],
                ],
                'space' => [
                    'id' => $task['space']['id'],
                ],
            ], $tasks);
        } catch (\Exception $e) {
            return 'Error getting tasks: '.$e->getMessage();
        }
    }

    /**
     * Create a ClickUp task.
     *
     * @param string             $listId      List ID
     * @param string             $name        Task name
     * @param string             $description Task description
     * @param array<int, int>    $assignees   Assignee IDs
     * @param array<int, string> $tags        Tag names
     * @param string             $status      Task status
     * @param int                $priority    Priority (1=urgent, 2=high, 3=normal, 4=low)
     * @param string             $dueDate     Due date
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     status: array{status: string, color: string, type: string, orderindex: int},
     *     orderindex: string,
     *     date_created: string,
     *     date_updated: string,
     *     assignees: array<int, array{id: int, username: string, email: string, color: string}>,
     *     tags: array<int, array{name: string, tag_fg: string, tag_bg: string}>,
     *     url: string,
     *     list: array{id: string, name: string},
     *     folder: array{id: string, name: string},
     *     space: array{id: string},
     * }|string
     */
    public function createTask(
        string $listId,
        string $name,
        string $description = '',
        array $assignees = [],
        array $tags = [],
        string $status = '',
        int $priority = 3,
        string $dueDate = '',
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'description' => $description,
                'assignees' => $assignees,
                'tags' => $tags,
                'priority' => $priority,
            ];

            if ($status) {
                $payload['status'] = $status;
            }
            if ($dueDate) {
                $payload['due_date'] = $dueDate;
            }

            $response = $this->httpClient->request('POST', "https://api.clickup.com/api/{$this->apiVersion}/list/{$listId}/task", [
                'headers' => [
                    'Authorization' => $this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['err'])) {
                return 'Error creating task: '.($data['err'] ?? 'Unknown error');
            }

            $task = $data;

            return [
                'id' => $task['id'],
                'name' => $task['name'],
                'description' => $task['description'] ?? '',
                'status' => $task['status'],
                'orderindex' => $task['orderindex'],
                'date_created' => $task['date_created'],
                'date_updated' => $task['date_updated'],
                'assignees' => array_map(fn ($assignee) => [
                    'id' => $assignee['id'],
                    'username' => $assignee['username'],
                    'email' => $assignee['email'],
                    'color' => $assignee['color'],
                ], $task['assignees'] ?? []),
                'tags' => array_map(fn ($tag) => [
                    'name' => $tag['name'],
                    'tag_fg' => $tag['tag_fg'],
                    'tag_bg' => $tag['tag_bg'],
                ], $task['tags'] ?? []),
                'url' => $task['url'],
                'list' => [
                    'id' => $task['list']['id'],
                    'name' => $task['list']['name'],
                ],
                'folder' => [
                    'id' => $task['folder']['id'],
                    'name' => $task['folder']['name'],
                ],
                'space' => [
                    'id' => $task['space']['id'],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error creating task: '.$e->getMessage();
        }
    }

    /**
     * Get ClickUp lists.
     *
     * @param string $folderId Folder ID to filter lists
     * @param bool   $archived Include archived lists
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     orderindex: int,
     *     status: array<string, mixed>|null,
     *     priority: array<string, mixed>|null,
     *     assignee: array<string, mixed>|null,
     *     task_count: int,
     *     due_date: string|null,
     *     start_date: string|null,
     *     folder: array{id: string, name: string, hidden: bool, access: bool},
     *     space: array{id: string, name: string, access: bool},
     *     statuses: array<int, array{status: string, type: string, orderindex: int, color: string}>,
     *     permission_level: string,
     * }>|string
     */
    public function getLists(
        string $folderId = '',
        bool $archived = false,
    ): array|string {
        try {
            $params = [
                'archived' => $archived,
            ];

            $url = $folderId
                ? "https://api.clickup.com/api/{$this->apiVersion}/folder/{$folderId}/list"
                : "https://api.clickup.com/api/{$this->apiVersion}/team/{$this->options['team_id']}/list";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => $this->apiToken,
                ],
                'query' => array_merge($params, $this->options),
            ]);

            $data = $response->toArray();

            if (isset($data['err'])) {
                return 'Error getting lists: '.($data['err'] ?? 'Unknown error');
            }

            $lists = $data['lists'] ?? [];

            return array_map(fn ($list) => [
                'id' => $list['id'],
                'name' => $list['name'],
                'orderindex' => $list['orderindex'],
                'status' => $list['status'],
                'priority' => $list['priority'],
                'assignee' => $list['assignee'],
                'task_count' => $list['task_count'],
                'due_date' => $list['due_date'],
                'start_date' => $list['start_date'],
                'folder' => [
                    'id' => $list['folder']['id'],
                    'name' => $list['folder']['name'],
                    'hidden' => $list['folder']['hidden'],
                    'access' => $list['folder']['access'],
                ],
                'space' => [
                    'id' => $list['space']['id'],
                    'name' => $list['space']['name'],
                    'access' => $list['space']['access'],
                ],
                'statuses' => array_map(fn ($status) => [
                    'status' => $status['status'],
                    'type' => $status['type'],
                    'orderindex' => $status['orderindex'],
                    'color' => $status['color'],
                ], $list['statuses'] ?? []),
                'permission_level' => $list['permission_level'],
            ], $lists);
        } catch (\Exception $e) {
            return 'Error getting lists: '.$e->getMessage();
        }
    }

    /**
     * Get ClickUp folders.
     *
     * @param string $spaceId  Space ID to filter folders
     * @param bool   $archived Include archived folders
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     orderindex: int,
     *     override_statuses: bool,
     *     hidden: bool,
     *     space: array{id: string, name: string, access: bool},
     *     task_count: string,
     *     archived: bool,
     *     statuses: array<int, array{status: string, type: string, orderindex: int, color: string}>,
     *     lists: array<int, array{id: string, name: string, access: bool}>,
     *     permission_level: string,
     * }>|string
     */
    public function getFolders(
        string $spaceId = '',
        bool $archived = false,
    ): array|string {
        try {
            $params = [
                'archived' => $archived,
            ];

            $url = $spaceId
                ? "https://api.clickup.com/api/{$this->apiVersion}/space/{$spaceId}/folder"
                : "https://api.clickup.com/api/{$this->apiVersion}/team/{$this->options['team_id']}/folder";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => $this->apiToken,
                ],
                'query' => array_merge($params, $this->options),
            ]);

            $data = $response->toArray();

            if (isset($data['err'])) {
                return 'Error getting folders: '.($data['err'] ?? 'Unknown error');
            }

            $folders = $data['folders'] ?? [];

            return array_map(fn ($folder) => [
                'id' => $folder['id'],
                'name' => $folder['name'],
                'orderindex' => $folder['orderindex'],
                'override_statuses' => $folder['override_statuses'],
                'hidden' => $folder['hidden'],
                'space' => [
                    'id' => $folder['space']['id'],
                    'name' => $folder['space']['name'],
                    'access' => $folder['space']['access'],
                ],
                'task_count' => $folder['task_count'],
                'archived' => $folder['archived'],
                'statuses' => array_map(fn ($status) => [
                    'status' => $status['status'],
                    'type' => $status['type'],
                    'orderindex' => $status['orderindex'],
                    'color' => $status['color'],
                ], $folder['statuses'] ?? []),
                'lists' => array_map(fn ($list) => [
                    'id' => $list['id'],
                    'name' => $list['name'],
                    'access' => $list['access'],
                ], $folder['lists'] ?? []),
                'permission_level' => $folder['permission_level'],
            ], $folders);
        } catch (\Exception $e) {
            return 'Error getting folders: '.$e->getMessage();
        }
    }

    /**
     * Get ClickUp spaces.
     *
     * @param bool $archived Include archived spaces
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     private: bool,
     *     color: string,
     *     avatar: string|null,
     *     admin_can_manage: bool,
     *     statuses: array<int, array{status: string, type: string, orderindex: int, color: string}>,
     *     multiple_assignees: bool,
     *     features: array<string, mixed>,
     *     archived: bool,
     * }>|string
     */
    public function getSpaces(bool $archived = false): array|string
    {
        try {
            $params = [
                'archived' => $archived,
            ];

            $response = $this->httpClient->request('GET', "https://api.clickup.com/api/{$this->apiVersion}/team/{$this->options['team_id']}/space", [
                'headers' => [
                    'Authorization' => $this->apiToken,
                ],
                'query' => array_merge($params, $this->options),
            ]);

            $data = $response->toArray();

            if (isset($data['err'])) {
                return 'Error getting spaces: '.($data['err'] ?? 'Unknown error');
            }

            $spaces = $data['spaces'] ?? [];

            return array_map(fn ($space) => [
                'id' => $space['id'],
                'name' => $space['name'],
                'private' => $space['private'],
                'color' => $space['color'],
                'avatar' => $space['avatar'],
                'admin_can_manage' => $space['admin_can_manage'],
                'statuses' => array_map(fn ($status) => [
                    'status' => $status['status'],
                    'type' => $status['type'],
                    'orderindex' => $status['orderindex'],
                    'color' => $status['color'],
                ], $space['statuses'] ?? []),
                'multiple_assignees' => $space['multiple_assignees'],
                'features' => $space['features'],
                'archived' => $space['archived'],
            ], $spaces);
        } catch (\Exception $e) {
            return 'Error getting spaces: '.$e->getMessage();
        }
    }

    /**
     * Update a ClickUp task.
     *
     * @param string          $taskId      Task ID to update
     * @param string          $name        New name (optional)
     * @param string          $description New description (optional)
     * @param string          $status      New status (optional)
     * @param array<int, int> $assignees   New assignees (optional)
     * @param int             $priority    New priority (optional)
     */
    public function updateTask(
        string $taskId,
        string $name = '',
        string $description = '',
        string $status = '',
        array $assignees = [],
        int $priority = -1,
    ): string {
        try {
            $payload = [];

            if ($name) {
                $payload['name'] = $name;
            }
            if ($description) {
                $payload['description'] = $description;
            }
            if ($status) {
                $payload['status'] = $status;
            }
            if (!empty($assignees)) {
                $payload['assignees'] = $assignees;
            }
            if ($priority >= 0) {
                $payload['priority'] = $priority;
            }

            $response = $this->httpClient->request('PUT', "https://api.clickup.com/api/{$this->apiVersion}/task/{$taskId}", [
                'headers' => [
                    'Authorization' => $this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['err'])) {
                return 'Error updating task: '.($data['err'] ?? 'Unknown error');
            }

            return 'Task updated successfully';
        } catch (\Exception $e) {
            return 'Error updating task: '.$e->getMessage();
        }
    }
}
