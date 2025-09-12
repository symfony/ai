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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('gitlab_get_projects', 'Tool that gets GitLab projects')]
#[AsTool('gitlab_create_project', 'Tool that creates GitLab projects', method: 'createProject')]
#[AsTool('gitlab_get_issues', 'Tool that gets GitLab issues', method: 'getIssues')]
#[AsTool('gitlab_create_issue', 'Tool that creates GitLab issues', method: 'createIssue')]
#[AsTool('gitlab_get_merge_requests', 'Tool that gets GitLab merge requests', method: 'getMergeRequests')]
#[AsTool('gitlab_get_repository_files', 'Tool that gets GitLab repository files', method: 'getRepositoryFiles')]
final readonly class GitLab
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        #[\SensitiveParameter] private string $gitlabUrl,
        private string $apiVersion = 'v4',
        private array $options = [],
    ) {
    }

    /**
     * Get GitLab projects.
     *
     * @param int    $limit   Number of projects to retrieve
     * @param string $search  Search query
     * @param string $orderBy Order by field (id, name, path, created_at, updated_at, last_activity_at)
     * @param string $sort    Sort order (asc, desc)
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     name_with_namespace: string,
     *     path: string,
     *     path_with_namespace: string,
     *     description: string,
     *     visibility: string,
     *     created_at: string,
     *     updated_at: string,
     *     last_activity_at: string,
     *     web_url: string,
     *     ssh_url_to_repo: string,
     *     http_url_to_repo: string,
     *     default_branch: string,
     *     star_count: int,
     *     forks_count: int,
     *     open_issues_count: int,
     * }>
     */
    public function __invoke(
        int $limit = 20,
        string $search = '',
        string $orderBy = 'last_activity_at',
        string $sort = 'desc',
    ): array {
        try {
            $params = [
                'per_page' => min(max($limit, 1), 100),
                'order_by' => $orderBy,
                'sort' => $sort,
            ];

            if ($search) {
                $params['search'] = $search;
            }

            $response = $this->httpClient->request('GET', "{$this->gitlabUrl}/api/{$this->apiVersion}/projects", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            $projects = [];
            foreach ($data as $project) {
                $projects[] = [
                    'id' => $project['id'],
                    'name' => $project['name'],
                    'name_with_namespace' => $project['name_with_namespace'],
                    'path' => $project['path'],
                    'path_with_namespace' => $project['path_with_namespace'],
                    'description' => $project['description'] ?? '',
                    'visibility' => $project['visibility'],
                    'created_at' => $project['created_at'],
                    'updated_at' => $project['updated_at'],
                    'last_activity_at' => $project['last_activity_at'],
                    'web_url' => $project['web_url'],
                    'ssh_url_to_repo' => $project['ssh_url_to_repo'],
                    'http_url_to_repo' => $project['http_url_to_repo'],
                    'default_branch' => $project['default_branch'],
                    'star_count' => $project['star_count'] ?? 0,
                    'forks_count' => $project['forks_count'] ?? 0,
                    'open_issues_count' => $project['open_issues_count'] ?? 0,
                ];
            }

            return $projects;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a GitLab project.
     *
     * @param string $name                 Project name
     * @param string $description          Project description
     * @param string $visibility           Project visibility (private, internal, public)
     * @param bool   $initializeWithReadme Initialize with README
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     description: string,
     *     visibility: string,
     *     created_at: string,
     *     web_url: string,
     *     ssh_url_to_repo: string,
     *     http_url_to_repo: string,
     * }|string
     */
    public function createProject(
        string $name,
        string $description = '',
        string $visibility = 'private',
        bool $initializeWithReadme = true,
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'description' => $description,
                'visibility' => $visibility,
                'initialize_with_readme' => $initializeWithReadme,
            ];

            $response = $this->httpClient->request('POST', "{$this->gitlabUrl}/api/{$this->apiVersion}/projects", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return 'Error creating project: '.$data['message'];
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'visibility' => $data['visibility'],
                'created_at' => $data['created_at'],
                'web_url' => $data['web_url'],
                'ssh_url_to_repo' => $data['ssh_url_to_repo'],
                'http_url_to_repo' => $data['http_url_to_repo'],
            ];
        } catch (\Exception $e) {
            return 'Error creating project: '.$e->getMessage();
        }
    }

    /**
     * Get GitLab issues.
     *
     * @param int    $projectId Project ID
     * @param int    $limit     Number of issues to retrieve
     * @param string $state     Issue state (opened, closed, all)
     * @param string $labels    Comma-separated labels
     *
     * @return array<int, array{
     *     id: int,
     *     iid: int,
     *     title: string,
     *     description: string,
     *     state: string,
     *     created_at: string,
     *     updated_at: string,
     *     closed_at: string|null,
     *     labels: array<int, string>,
     *     author: array{id: int, name: string, username: string},
     *     assignee: array{id: int, name: string, username: string}|null,
     *     web_url: string,
     * }>
     */
    public function getIssues(
        int $projectId,
        int $limit = 20,
        string $state = 'opened',
        string $labels = '',
    ): array {
        try {
            $params = [
                'per_page' => min(max($limit, 1), 100),
                'state' => $state,
            ];

            if ($labels) {
                $params['labels'] = $labels;
            }

            $response = $this->httpClient->request('GET', "{$this->gitlabUrl}/api/{$this->apiVersion}/projects/{$projectId}/issues", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            $issues = [];
            foreach ($data as $issue) {
                $issues[] = [
                    'id' => $issue['id'],
                    'iid' => $issue['iid'],
                    'title' => $issue['title'],
                    'description' => $issue['description'] ?? '',
                    'state' => $issue['state'],
                    'created_at' => $issue['created_at'],
                    'updated_at' => $issue['updated_at'],
                    'closed_at' => $issue['closed_at'] ?? null,
                    'labels' => $issue['labels'] ?? [],
                    'author' => [
                        'id' => $issue['author']['id'],
                        'name' => $issue['author']['name'],
                        'username' => $issue['author']['username'],
                    ],
                    'assignee' => $issue['assignee'] ? [
                        'id' => $issue['assignee']['id'],
                        'name' => $issue['assignee']['name'],
                        'username' => $issue['assignee']['username'],
                    ] : null,
                    'web_url' => $issue['web_url'],
                ];
            }

            return $issues;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a GitLab issue.
     *
     * @param int                $projectId   Project ID
     * @param string             $title       Issue title
     * @param string             $description Issue description
     * @param array<int, string> $labels      Issue labels
     * @param int                $assigneeId  Assignee user ID
     *
     * @return array{
     *     id: int,
     *     iid: int,
     *     title: string,
     *     description: string,
     *     state: string,
     *     created_at: string,
     *     labels: array<int, string>,
     *     web_url: string,
     * }|string
     */
    public function createIssue(
        int $projectId,
        string $title,
        string $description = '',
        array $labels = [],
        int $assigneeId = 0,
    ): array|string {
        try {
            $payload = [
                'title' => $title,
                'description' => $description,
                'labels' => implode(',', $labels),
            ];

            if ($assigneeId > 0) {
                $payload['assignee_ids'] = [$assigneeId];
            }

            $response = $this->httpClient->request('POST', "{$this->gitlabUrl}/api/{$this->apiVersion}/projects/{$projectId}/issues", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return 'Error creating issue: '.$data['message'];
            }

            return [
                'id' => $data['id'],
                'iid' => $data['iid'],
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'state' => $data['state'],
                'created_at' => $data['created_at'],
                'labels' => $data['labels'] ?? [],
                'web_url' => $data['web_url'],
            ];
        } catch (\Exception $e) {
            return 'Error creating issue: '.$e->getMessage();
        }
    }

    /**
     * Get GitLab merge requests.
     *
     * @param int    $projectId Project ID
     * @param int    $limit     Number of merge requests to retrieve
     * @param string $state     Merge request state (opened, closed, merged, all)
     *
     * @return array<int, array{
     *     id: int,
     *     iid: int,
     *     title: string,
     *     description: string,
     *     state: string,
     *     created_at: string,
     *     updated_at: string,
     *     merged_at: string|null,
     *     source_branch: string,
     *     target_branch: string,
     *     author: array{id: int, name: string, username: string},
     *     assignee: array{id: int, name: string, username: string}|null,
     *     web_url: string,
     * }>
     */
    public function getMergeRequests(
        int $projectId,
        int $limit = 20,
        string $state = 'opened',
    ): array {
        try {
            $params = [
                'per_page' => min(max($limit, 1), 100),
                'state' => $state,
            ];

            $response = $this->httpClient->request('GET', "{$this->gitlabUrl}/api/{$this->apiVersion}/projects/{$projectId}/merge_requests", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            $mergeRequests = [];
            foreach ($data as $mr) {
                $mergeRequests[] = [
                    'id' => $mr['id'],
                    'iid' => $mr['iid'],
                    'title' => $mr['title'],
                    'description' => $mr['description'] ?? '',
                    'state' => $mr['state'],
                    'created_at' => $mr['created_at'],
                    'updated_at' => $mr['updated_at'],
                    'merged_at' => $mr['merged_at'] ?? null,
                    'source_branch' => $mr['source_branch'],
                    'target_branch' => $mr['target_branch'],
                    'author' => [
                        'id' => $mr['author']['id'],
                        'name' => $mr['author']['name'],
                        'username' => $mr['author']['username'],
                    ],
                    'assignee' => $mr['assignee'] ? [
                        'id' => $mr['assignee']['id'],
                        'name' => $mr['assignee']['name'],
                        'username' => $mr['assignee']['username'],
                    ] : null,
                    'web_url' => $mr['web_url'],
                ];
            }

            return $mergeRequests;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get GitLab repository files.
     *
     * @param int    $projectId Project ID
     * @param string $filePath  File path in repository
     * @param string $ref       Branch, tag, or commit SHA
     *
     * @return array{
     *     file_name: string,
     *     file_path: string,
     *     size: int,
     *     encoding: string,
     *     content: string,
     *     content_sha256: string,
     *     ref: string,
     *     blob_id: string,
     *     commit_id: string,
     *     last_commit_id: string,
     * }|string
     */
    public function getRepositoryFiles(
        int $projectId,
        string $filePath,
        string $ref = 'main',
    ): array|string {
        try {
            $params = [
                'ref' => $ref,
            ];

            $response = $this->httpClient->request('GET', "{$this->gitlabUrl}/api/{$this->apiVersion}/projects/{$projectId}/repository/files/".urlencode($filePath), [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return 'Error getting file: '.$data['message'];
            }

            return [
                'file_name' => $data['file_name'],
                'file_path' => $data['file_path'],
                'size' => $data['size'],
                'encoding' => $data['encoding'],
                'content' => base64_decode($data['content']),
                'content_sha256' => $data['content_sha256'],
                'ref' => $data['ref'],
                'blob_id' => $data['blob_id'],
                'commit_id' => $data['commit_id'],
                'last_commit_id' => $data['last_commit_id'],
            ];
        } catch (\Exception $e) {
            return 'Error getting file: '.$e->getMessage();
        }
    }
}
