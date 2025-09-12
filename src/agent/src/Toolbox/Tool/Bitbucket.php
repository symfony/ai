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
#[AsTool('bitbucket_get_repositories', 'Tool that gets Bitbucket repositories')]
#[AsTool('bitbucket_create_repository', 'Tool that creates Bitbucket repositories', method: 'createRepository')]
#[AsTool('bitbucket_get_issues', 'Tool that gets Bitbucket issues', method: 'getIssues')]
#[AsTool('bitbucket_create_issue', 'Tool that creates Bitbucket issues', method: 'createIssue')]
#[AsTool('bitbucket_get_pull_requests', 'Tool that gets Bitbucket pull requests', method: 'getPullRequests')]
#[AsTool('bitbucket_create_pull_request', 'Tool that creates Bitbucket pull requests', method: 'createPullRequest')]
final readonly class Bitbucket
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $username,
        #[\SensitiveParameter] private string $appPassword,
        private string $apiVersion = '2.0',
        private array $options = [],
    ) {
    }

    /**
     * Get Bitbucket repositories.
     *
     * @param string $workspace Bitbucket workspace/team name
     * @param int    $limit     Number of repositories to retrieve
     * @param string $sort      Sort by field (name, created_on, updated_on, size)
     *
     * @return array<int, array{
     *     uuid: string,
     *     name: string,
     *     slug: string,
     *     full_name: string,
     *     description: string,
     *     is_private: bool,
     *     created_on: string,
     *     updated_on: string,
     *     size: int,
     *     language: string,
     *     has_issues: bool,
     *     has_wiki: bool,
     *     fork_policy: string,
     *     project: array{key: string, type: string, name: string}|null,
     *     mainbranch: array{name: string, type: string},
     *     links: array{
     *         self: array{href: string},
     *         html: array{href: string},
     *         clone: array<int, array{href: string, name: string}>,
     *     },
     * }>
     */
    public function __invoke(
        string $workspace,
        int $limit = 20,
        string $sort = 'updated_on',
    ): array {
        try {
            $params = [
                'pagelen' => min(max($limit, 1), 100),
                'sort' => '-'.$sort,
            ];

            $response = $this->httpClient->request('GET', "https://api.bitbucket.org/{$this->apiVersion}/repositories/{$workspace}", [
                'auth_basic' => [$this->username, $this->appPassword],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['values'])) {
                return [];
            }

            $repositories = [];
            foreach ($data['values'] as $repo) {
                $repositories[] = [
                    'uuid' => $repo['uuid'],
                    'name' => $repo['name'],
                    'slug' => $repo['slug'],
                    'full_name' => $repo['full_name'],
                    'description' => $repo['description'] ?? '',
                    'is_private' => $repo['is_private'],
                    'created_on' => $repo['created_on'],
                    'updated_on' => $repo['updated_on'],
                    'size' => $repo['size'],
                    'language' => $repo['language'] ?? '',
                    'has_issues' => $repo['has_issues'],
                    'has_wiki' => $repo['has_wiki'],
                    'fork_policy' => $repo['fork_policy'],
                    'project' => $repo['project'] ?? null,
                    'mainbranch' => $repo['mainbranch'],
                    'links' => [
                        'self' => $repo['links']['self'],
                        'html' => $repo['links']['html'],
                        'clone' => $repo['links']['clone'],
                    ],
                ];
            }

            return $repositories;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Bitbucket repository.
     *
     * @param string $workspace   Bitbucket workspace/team name
     * @param string $name        Repository name
     * @param string $description Repository description
     * @param bool   $isPrivate   Whether repository is private
     * @param string $language    Primary programming language
     *
     * @return array{
     *     uuid: string,
     *     name: string,
     *     slug: string,
     *     full_name: string,
     *     description: string,
     *     is_private: bool,
     *     created_on: string,
     *     updated_on: string,
     *     language: string,
     *     has_issues: bool,
     *     has_wiki: bool,
     *     fork_policy: string,
     *     mainbranch: array{name: string, type: string},
     *     links: array{
     *         self: array{href: string},
     *         html: array{href: string},
     *         clone: array<int, array{href: string, name: string}>,
     *     },
     * }|string
     */
    public function createRepository(
        string $workspace,
        string $name,
        string $description = '',
        bool $isPrivate = true,
        string $language = '',
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'description' => $description,
                'is_private' => $isPrivate,
                'has_issues' => true,
                'has_wiki' => true,
                'fork_policy' => 'allow_forks',
            ];

            if ($language) {
                $payload['language'] = $language;
            }

            $response = $this->httpClient->request('POST', "https://api.bitbucket.org/{$this->apiVersion}/repositories/{$workspace}/{$name}", [
                'auth_basic' => [$this->username, $this->appPassword],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating repository: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'uuid' => $data['uuid'],
                'name' => $data['name'],
                'slug' => $data['slug'],
                'full_name' => $data['full_name'],
                'description' => $data['description'] ?? '',
                'is_private' => $data['is_private'],
                'created_on' => $data['created_on'],
                'updated_on' => $data['updated_on'],
                'language' => $data['language'] ?? '',
                'has_issues' => $data['has_issues'],
                'has_wiki' => $data['has_wiki'],
                'fork_policy' => $data['fork_policy'],
                'mainbranch' => $data['mainbranch'],
                'links' => [
                    'self' => $data['links']['self'],
                    'html' => $data['links']['html'],
                    'clone' => $data['links']['clone'],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error creating repository: '.$e->getMessage();
        }
    }

    /**
     * Get Bitbucket issues.
     *
     * @param string $workspace  Bitbucket workspace/team name
     * @param string $repository Repository name
     * @param int    $limit      Number of issues to retrieve
     * @param string $state      Issue state (new, open, resolved, on hold, invalid, duplicate, wontfix, closed)
     * @param string $priority   Issue priority (trivial, minor, major, critical, blocker)
     *
     * @return array<int, array{
     *     id: int,
     *     title: string,
     *     content: array{raw: string, markup: string, html: string, type: string},
     *     state: string,
     *     kind: string,
     *     priority: string,
     *     votes: int,
     *     reporter: array{display_name: string, uuid: string, links: array{self: array{href: string}, html: array{href: string}}},
     *     assignee: array{display_name: string, uuid: string, links: array{self: array{href: string}, html: array{href: string}}}|null,
     *     created_on: string,
     *     updated_on: string,
     *     links: array{self: array{href: string}, html: array{href: string}},
     * }>
     */
    public function getIssues(
        string $workspace,
        string $repository,
        int $limit = 20,
        string $state = '',
        string $priority = '',
    ): array {
        try {
            $params = [
                'pagelen' => min(max($limit, 1), 100),
            ];

            if ($state) {
                $params['q'] = 'state="'.$state.'"';
            }
            if ($priority) {
                $q = $params['q'] ?? '';
                $params['q'] = $q ? $q.' AND priority="'.$priority.'"' : 'priority="'.$priority.'"';
            }

            $response = $this->httpClient->request('GET', "https://api.bitbucket.org/{$this->apiVersion}/repositories/{$workspace}/{$repository}/issues", [
                'auth_basic' => [$this->username, $this->appPassword],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['values'])) {
                return [];
            }

            $issues = [];
            foreach ($data['values'] as $issue) {
                $issues[] = [
                    'id' => $issue['id'],
                    'title' => $issue['title'],
                    'content' => $issue['content'],
                    'state' => $issue['state'],
                    'kind' => $issue['kind'],
                    'priority' => $issue['priority'],
                    'votes' => $issue['votes'],
                    'reporter' => $issue['reporter'],
                    'assignee' => $issue['assignee'] ?? null,
                    'created_on' => $issue['created_on'],
                    'updated_on' => $issue['updated_on'],
                    'links' => $issue['links'],
                ];
            }

            return $issues;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Bitbucket issue.
     *
     * @param string $workspace  Bitbucket workspace/team name
     * @param string $repository Repository name
     * @param string $title      Issue title
     * @param string $content    Issue content/description
     * @param string $kind       Issue kind (bug, enhancement, proposal, task)
     * @param string $priority   Issue priority (trivial, minor, major, critical, blocker)
     * @param string $assignee   Assignee username
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     content: array{raw: string, markup: string, html: string, type: string},
     *     state: string,
     *     kind: string,
     *     priority: string,
     *     votes: int,
     *     reporter: array{display_name: string, uuid: string, links: array{self: array{href: string}, html: array{href: string}}},
     *     assignee: array{display_name: string, uuid: string, links: array{self: array{href: string}, html: array{href: string}}}|null,
     *     created_on: string,
     *     updated_on: string,
     *     links: array{self: array{href: string}, html: array{href: string}},
     * }|string
     */
    public function createIssue(
        string $workspace,
        string $repository,
        string $title,
        string $content,
        string $kind = 'bug',
        string $priority = 'major',
        string $assignee = '',
    ): array|string {
        try {
            $payload = [
                'title' => $title,
                'content' => [
                    'raw' => $content,
                    'markup' => 'markdown',
                ],
                'kind' => $kind,
                'priority' => $priority,
            ];

            if ($assignee) {
                $payload['assignee'] = [
                    'username' => $assignee,
                ];
            }

            $response = $this->httpClient->request('POST', "https://api.bitbucket.org/{$this->apiVersion}/repositories/{$workspace}/{$repository}/issues", [
                'auth_basic' => [$this->username, $this->appPassword],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating issue: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'title' => $data['title'],
                'content' => $data['content'],
                'state' => $data['state'],
                'kind' => $data['kind'],
                'priority' => $data['priority'],
                'votes' => $data['votes'],
                'reporter' => $data['reporter'],
                'assignee' => $data['assignee'] ?? null,
                'created_on' => $data['created_on'],
                'updated_on' => $data['updated_on'],
                'links' => $data['links'],
            ];
        } catch (\Exception $e) {
            return 'Error creating issue: '.$e->getMessage();
        }
    }

    /**
     * Get Bitbucket pull requests.
     *
     * @param string $workspace  Bitbucket workspace/team name
     * @param string $repository Repository name
     * @param int    $limit      Number of pull requests to retrieve
     * @param string $state      Pull request state (OPEN, MERGED, DECLINED, SUPERSEDED)
     *
     * @return array<int, array{
     *     id: int,
     *     title: string,
     *     description: string,
     *     state: string,
     *     author: array{display_name: string, uuid: string, links: array{self: array{href: string}, html: array{href: string}}},
     *     source: array{branch: array{name: string}, commit: array{hash: string}},
     *     destination: array{branch: array{name: string}, commit: array{hash: string}},
     *     reviewers: array<int, array{display_name: string, uuid: string, links: array{self: array{href: string}, html: array{href: string}}}>,
     *     created_on: string,
     *     updated_on: string,
     *     links: array{self: array{href: string}, html: array{href: string}},
     * }>
     */
    public function getPullRequests(
        string $workspace,
        string $repository,
        int $limit = 20,
        string $state = 'OPEN',
    ): array {
        try {
            $params = [
                'pagelen' => min(max($limit, 1), 100),
                'state' => $state,
            ];

            $response = $this->httpClient->request('GET', "https://api.bitbucket.org/{$this->apiVersion}/repositories/{$workspace}/{$repository}/pullrequests", [
                'auth_basic' => [$this->username, $this->appPassword],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['values'])) {
                return [];
            }

            $pullRequests = [];
            foreach ($data['values'] as $pr) {
                $pullRequests[] = [
                    'id' => $pr['id'],
                    'title' => $pr['title'],
                    'description' => $pr['description'] ?? '',
                    'state' => $pr['state'],
                    'author' => $pr['author'],
                    'source' => $pr['source'],
                    'destination' => $pr['destination'],
                    'reviewers' => $pr['reviewers'] ?? [],
                    'created_on' => $pr['created_on'],
                    'updated_on' => $pr['updated_on'],
                    'links' => $pr['links'],
                ];
            }

            return $pullRequests;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Bitbucket pull request.
     *
     * @param string             $workspace         Bitbucket workspace/team name
     * @param string             $repository        Repository name
     * @param string             $title             Pull request title
     * @param string             $description       Pull request description
     * @param string             $sourceBranch      Source branch name
     * @param string             $destinationBranch Destination branch name
     * @param array<int, string> $reviewers         Reviewer usernames
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     description: string,
     *     state: string,
     *     author: array{display_name: string, uuid: string, links: array{self: array{href: string}, html: array{href: string}}},
     *     source: array{branch: array{name: string}, commit: array{hash: string}},
     *     destination: array{branch: array{name: string}, commit: array{hash: string}},
     *     reviewers: array<int, array{display_name: string, uuid: string, links: array{self: array{href: string}, html: array{href: string}}}>,
     *     created_on: string,
     *     updated_on: string,
     *     links: array{self: array{href: string}, html: array{href: string}},
     * }|string
     */
    public function createPullRequest(
        string $workspace,
        string $repository,
        string $title,
        string $description,
        string $sourceBranch,
        string $destinationBranch,
        array $reviewers = [],
    ): array|string {
        try {
            $payload = [
                'title' => $title,
                'description' => $description,
                'source' => [
                    'branch' => [
                        'name' => $sourceBranch,
                    ],
                ],
                'destination' => [
                    'branch' => [
                        'name' => $destinationBranch,
                    ],
                ],
            ];

            if (!empty($reviewers)) {
                $payload['reviewers'] = array_map(fn ($username) => [
                    'username' => $username,
                ], $reviewers);
            }

            $response = $this->httpClient->request('POST', "https://api.bitbucket.org/{$this->apiVersion}/repositories/{$workspace}/{$repository}/pullrequests", [
                'auth_basic' => [$this->username, $this->appPassword],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating pull request: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'state' => $data['state'],
                'author' => $data['author'],
                'source' => $data['source'],
                'destination' => $data['destination'],
                'reviewers' => $data['reviewers'] ?? [],
                'created_on' => $data['created_on'],
                'updated_on' => $data['updated_on'],
                'links' => $data['links'],
            ];
        } catch (\Exception $e) {
            return 'Error creating pull request: '.$e->getMessage();
        }
    }
}
