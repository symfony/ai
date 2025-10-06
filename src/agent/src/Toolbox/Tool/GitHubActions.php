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
#[AsTool('github_actions_get_workflows', 'Tool that gets GitHub Actions workflows')]
#[AsTool('github_actions_get_workflow_runs', 'Tool that gets GitHub Actions workflow runs', method: 'getWorkflowRuns')]
#[AsTool('github_actions_get_jobs', 'Tool that gets GitHub Actions jobs', method: 'getJobs')]
#[AsTool('github_actions_get_logs', 'Tool that gets GitHub Actions logs', method: 'getLogs')]
#[AsTool('github_actions_rerun_workflow', 'Tool that reruns GitHub Actions workflows', method: 'rerunWorkflow')]
#[AsTool('github_actions_cancel_workflow', 'Tool that cancels GitHub Actions workflows', method: 'cancelWorkflow')]
final readonly class GitHubActions
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v3',
        private array $options = [],
    ) {
    }

    /**
     * Get GitHub Actions workflows.
     *
     * @param string $owner   Repository owner
     * @param string $repo    Repository name
     * @param int    $perPage Number of workflows per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: int,
     *     node_id: string,
     *     name: string,
     *     path: string,
     *     state: string,
     *     created_at: string,
     *     updated_at: string,
     *     url: string,
     *     html_url: string,
     *     badge_url: string,
     * }>
     */
    public function __invoke(
        string $owner,
        string $repo,
        int $perPage = 30,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$owner}/{$repo}/actions/workflows", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Accept' => 'application/vnd.github.'.$this->apiVersion.'+json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return [];
            }

            return array_map(fn ($workflow) => [
                'id' => $workflow['id'],
                'node_id' => $workflow['node_id'],
                'name' => $workflow['name'],
                'path' => $workflow['path'],
                'state' => $workflow['state'],
                'created_at' => $workflow['created_at'],
                'updated_at' => $workflow['updated_at'],
                'url' => $workflow['url'],
                'html_url' => $workflow['html_url'],
                'badge_url' => $workflow['badge_url'],
            ], $data['workflows'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get GitHub Actions workflow runs.
     *
     * @param string $owner      Repository owner
     * @param string $repo       Repository name
     * @param int    $workflowId Workflow ID (optional)
     * @param string $actor      Actor filter (optional)
     * @param string $branch     Branch filter (optional)
     * @param string $event      Event filter (optional)
     * @param string $status     Status filter (completed, action_required, cancelled, failure, neutral, skipped, stale, success, timed_out, in_progress, queued, requested, waiting)
     * @param string $conclusion Conclusion filter (success, failure, neutral, cancelled, skipped, timed_out, action_required)
     * @param int    $perPage    Number of runs per page
     * @param int    $page       Page number
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     head_branch: string,
     *     head_sha: string,
     *     run_number: int,
     *     event: string,
     *     status: string,
     *     conclusion: string|null,
     *     workflow_id: int,
     *     check_suite_id: int,
     *     check_suite_node_id: string,
     *     url: string,
     *     html_url: string,
     *     pull_requests: array<int, array{
     *         url: string,
     *         id: int,
     *         number: int,
     *         head: array{
     *             ref: string,
     *             sha: string,
     *             repo: array{id: int, url: string, name: string},
     *         },
     *         base: array{
     *             ref: string,
     *             sha: string,
     *             repo: array{id: int, url: string, name: string},
     *         },
     *     }>,
     *     created_at: string,
     *     updated_at: string,
     *     jobs_url: string,
     *     logs_url: string,
     *     check_suite_url: string,
     *     artifacts_url: string,
     *     cancel_url: string,
     *     rerun_url: string,
     *     workflow_url: string,
     *     head_commit: array{
     *         id: string,
     *         tree_id: string,
     *         message: string,
     *         timestamp: string,
     *         author: array{name: string, email: string},
     *         committer: array{name: string, email: string},
     *     },
     *     repository: array{
     *         id: int,
     *         node_id: string,
     *         name: string,
     *         full_name: string,
     *         private: bool,
     *         owner: array{login: string, id: int, node_id: string, avatar_url: string},
     *         html_url: string,
     *         description: string,
     *         fork: bool,
     *         url: string,
     *         created_at: string,
     *         updated_at: string,
     *         pushed_at: string,
     *         clone_url: string,
     *         default_branch: string,
     *     },
     *     head_repository: array{
     *         id: int,
     *         node_id: string,
     *         name: string,
     *         full_name: string,
     *         private: bool,
     *         owner: array{login: string, id: int, node_id: string, avatar_url: string},
     *         html_url: string,
     *         description: string,
     *         fork: bool,
     *         url: string,
     *         created_at: string,
     *         updated_at: string,
     *         pushed_at: string,
     *         clone_url: string,
     *         default_branch: string,
     *     },
     * }>
     */
    public function getWorkflowRuns(
        string $owner,
        string $repo,
        int $workflowId = 0,
        string $actor = '',
        string $branch = '',
        string $event = '',
        string $status = '',
        string $conclusion = '',
        int $perPage = 30,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            if ($actor) {
                $params['actor'] = $actor;
            }
            if ($branch) {
                $params['branch'] = $branch;
            }
            if ($event) {
                $params['event'] = $event;
            }
            if ($status) {
                $params['status'] = $status;
            }
            if ($conclusion) {
                $params['conclusion'] = $conclusion;
            }

            $url = $workflowId > 0
                ? "https://api.github.com/repos/{$owner}/{$repo}/actions/workflows/{$workflowId}/runs"
                : "https://api.github.com/repos/{$owner}/{$repo}/actions/runs";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Accept' => 'application/vnd.github.'.$this->apiVersion.'+json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return [];
            }

            return array_map(fn ($run) => [
                'id' => $run['id'],
                'name' => $run['name'],
                'head_branch' => $run['head_branch'],
                'head_sha' => $run['head_sha'],
                'run_number' => $run['run_number'],
                'event' => $run['event'],
                'status' => $run['status'],
                'conclusion' => $run['conclusion'],
                'workflow_id' => $run['workflow_id'],
                'check_suite_id' => $run['check_suite_id'],
                'check_suite_node_id' => $run['check_suite_node_id'],
                'url' => $run['url'],
                'html_url' => $run['html_url'],
                'pull_requests' => array_map(fn ($pr) => [
                    'url' => $pr['url'],
                    'id' => $pr['id'],
                    'number' => $pr['number'],
                    'head' => [
                        'ref' => $pr['head']['ref'],
                        'sha' => $pr['head']['sha'],
                        'repo' => [
                            'id' => $pr['head']['repo']['id'],
                            'url' => $pr['head']['repo']['url'],
                            'name' => $pr['head']['repo']['name'],
                        ],
                    ],
                    'base' => [
                        'ref' => $pr['base']['ref'],
                        'sha' => $pr['base']['sha'],
                        'repo' => [
                            'id' => $pr['base']['repo']['id'],
                            'url' => $pr['base']['repo']['url'],
                            'name' => $pr['base']['repo']['name'],
                        ],
                    ],
                ], $run['pull_requests'] ?? []),
                'created_at' => $run['created_at'],
                'updated_at' => $run['updated_at'],
                'jobs_url' => $run['jobs_url'],
                'logs_url' => $run['logs_url'],
                'check_suite_url' => $run['check_suite_url'],
                'artifacts_url' => $run['artifacts_url'],
                'cancel_url' => $run['cancel_url'],
                'rerun_url' => $run['rerun_url'],
                'workflow_url' => $run['workflow_url'],
                'head_commit' => [
                    'id' => $run['head_commit']['id'],
                    'tree_id' => $run['head_commit']['tree_id'],
                    'message' => $run['head_commit']['message'],
                    'timestamp' => $run['head_commit']['timestamp'],
                    'author' => [
                        'name' => $run['head_commit']['author']['name'],
                        'email' => $run['head_commit']['author']['email'],
                    ],
                    'committer' => [
                        'name' => $run['head_commit']['committer']['name'],
                        'email' => $run['head_commit']['committer']['email'],
                    ],
                ],
                'repository' => [
                    'id' => $run['repository']['id'],
                    'node_id' => $run['repository']['node_id'],
                    'name' => $run['repository']['name'],
                    'full_name' => $run['repository']['full_name'],
                    'private' => $run['repository']['private'],
                    'owner' => [
                        'login' => $run['repository']['owner']['login'],
                        'id' => $run['repository']['owner']['id'],
                        'node_id' => $run['repository']['owner']['node_id'],
                        'avatar_url' => $run['repository']['owner']['avatar_url'],
                    ],
                    'html_url' => $run['repository']['html_url'],
                    'description' => $run['repository']['description'],
                    'fork' => $run['repository']['fork'],
                    'url' => $run['repository']['url'],
                    'created_at' => $run['repository']['created_at'],
                    'updated_at' => $run['repository']['updated_at'],
                    'pushed_at' => $run['repository']['pushed_at'],
                    'clone_url' => $run['repository']['clone_url'],
                    'default_branch' => $run['repository']['default_branch'],
                ],
                'head_repository' => [
                    'id' => $run['head_repository']['id'],
                    'node_id' => $run['head_repository']['node_id'],
                    'name' => $run['head_repository']['name'],
                    'full_name' => $run['head_repository']['full_name'],
                    'private' => $run['head_repository']['private'],
                    'owner' => [
                        'login' => $run['head_repository']['owner']['login'],
                        'id' => $run['head_repository']['owner']['id'],
                        'node_id' => $run['head_repository']['owner']['node_id'],
                        'avatar_url' => $run['head_repository']['owner']['avatar_url'],
                    ],
                    'html_url' => $run['head_repository']['html_url'],
                    'description' => $run['head_repository']['description'],
                    'fork' => $run['head_repository']['fork'],
                    'url' => $run['head_repository']['url'],
                    'created_at' => $run['head_repository']['created_at'],
                    'updated_at' => $run['head_repository']['updated_at'],
                    'pushed_at' => $run['head_repository']['pushed_at'],
                    'clone_url' => $run['head_repository']['clone_url'],
                    'default_branch' => $run['head_repository']['default_branch'],
                ],
            ], $data['workflow_runs'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get GitHub Actions jobs.
     *
     * @param string $owner  Repository owner
     * @param string $repo   Repository name
     * @param int    $runId  Workflow run ID
     * @param string $filter Job filter (latest, all)
     *
     * @return array<int, array{
     *     id: int,
     *     run_id: int,
     *     run_url: string,
     *     node_id: string,
     *     head_sha: string,
     *     url: string,
     *     html_url: string,
     *     status: string,
     *     conclusion: string|null,
     *     created_at: string,
     *     started_at: string,
     *     completed_at: string|null,
     *     name: string,
     *     steps: array<int, array{
     *         name: string,
     *         status: string,
     *         conclusion: string|null,
     *         number: int,
     *         started_at: string|null,
     *         completed_at: string|null,
     *     }>,
     *     check_run_url: string,
     *     labels: array<int, string>,
     *     runner_id: int|null,
     *     runner_name: string|null,
     *     runner_group_id: int|null,
     *     runner_group_name: string|null,
     * }>
     */
    public function getJobs(
        string $owner,
        string $repo,
        int $runId,
        string $filter = 'latest',
    ): array {
        try {
            $params = [
                'filter' => $filter,
            ];

            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$owner}/{$repo}/actions/runs/{$runId}/jobs", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Accept' => 'application/vnd.github.'.$this->apiVersion.'+json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return [];
            }

            return array_map(fn ($job) => [
                'id' => $job['id'],
                'run_id' => $job['run_id'],
                'run_url' => $job['run_url'],
                'node_id' => $job['node_id'],
                'head_sha' => $job['head_sha'],
                'url' => $job['url'],
                'html_url' => $job['html_url'],
                'status' => $job['status'],
                'conclusion' => $job['conclusion'],
                'created_at' => $job['created_at'],
                'started_at' => $job['started_at'],
                'completed_at' => $job['completed_at'],
                'name' => $job['name'],
                'steps' => array_map(fn ($step) => [
                    'name' => $step['name'],
                    'status' => $step['status'],
                    'conclusion' => $step['conclusion'],
                    'number' => $step['number'],
                    'started_at' => $step['started_at'],
                    'completed_at' => $step['completed_at'],
                ], $job['steps'] ?? []),
                'check_run_url' => $job['check_run_url'],
                'labels' => $job['labels'] ?? [],
                'runner_id' => $job['runner_id'],
                'runner_name' => $job['runner_name'],
                'runner_group_id' => $job['runner_group_id'],
                'runner_group_name' => $job['runner_group_name'],
            ], $data['jobs'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get GitHub Actions logs.
     *
     * @param string $owner Repository owner
     * @param string $repo  Repository name
     * @param int    $runId Workflow run ID
     *
     * @return array{
     *     logs: string,
     *     truncated: bool,
     * }|string
     */
    public function getLogs(
        string $owner,
        string $repo,
        int $runId,
    ): array|string {
        try {
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$owner}/{$repo}/actions/runs/{$runId}/logs", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Accept' => 'application/vnd.github.'.$this->apiVersion.'+json',
                ],
            ]);

            if (200 === $response->getStatusCode()) {
                $logs = $response->getContent();

                return [
                    'logs' => $logs,
                    'truncated' => false,
                ];
            }

            $data = $response->toArray();

            return 'Error getting logs: '.($data['message'] ?? 'Unknown error');
        } catch (\Exception $e) {
            return 'Error getting logs: '.$e->getMessage();
        }
    }

    /**
     * Rerun a GitHub Actions workflow.
     *
     * @param string $owner       Repository owner
     * @param string $repo        Repository name
     * @param int    $runId       Workflow run ID
     * @param bool   $enableDebug Enable debug logging
     */
    public function rerunWorkflow(
        string $owner,
        string $repo,
        int $runId,
        bool $enableDebug = false,
    ): string {
        try {
            $payload = [];

            if ($enableDebug) {
                $payload['enable_debug_logging'] = true;
            }

            $response = $this->httpClient->request('POST', "https://api.github.com/repos/{$owner}/{$repo}/actions/runs/{$runId}/rerun", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Accept' => 'application/vnd.github.'.$this->apiVersion.'+json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            if (201 === $response->getStatusCode()) {
                return 'Workflow rerun successfully triggered';
            }

            $data = $response->toArray();

            return 'Error rerunning workflow: '.($data['message'] ?? 'Unknown error');
        } catch (\Exception $e) {
            return 'Error rerunning workflow: '.$e->getMessage();
        }
    }

    /**
     * Cancel a GitHub Actions workflow.
     *
     * @param string $owner Repository owner
     * @param string $repo  Repository name
     * @param int    $runId Workflow run ID
     */
    public function cancelWorkflow(
        string $owner,
        string $repo,
        int $runId,
    ): string {
        try {
            $response = $this->httpClient->request('POST', "https://api.github.com/repos/{$owner}/{$repo}/actions/runs/{$runId}/cancel", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Accept' => 'application/vnd.github.'.$this->apiVersion.'+json',
                ],
            ]);

            if (202 === $response->getStatusCode()) {
                return 'Workflow cancel request submitted successfully';
            }

            $data = $response->toArray();

            return 'Error canceling workflow: '.($data['message'] ?? 'Unknown error');
        } catch (\Exception $e) {
            return 'Error canceling workflow: '.$e->getMessage();
        }
    }
}
