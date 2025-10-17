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
#[AsTool('github_issues', 'Get open issues from GitHub repository', method: 'getIssues')]
#[AsTool('github_pull_requests', 'Get open pull requests from GitHub repository', method: 'getPullRequests')]
#[AsTool('github_issue', 'Get specific issue details from GitHub repository', method: 'getIssue')]
#[AsTool('github_pull_request', 'Get specific pull request details from GitHub repository', method: 'getPullRequest')]
#[AsTool('github_branches', 'List all branches in GitHub repository', method: 'getBranches')]
#[AsTool('github_files', 'List files in GitHub repository', method: 'getFiles')]
#[AsTool('github_file_content', 'Get content of a specific file from GitHub repository', method: 'getFileContent')]
#[AsTool('github_search_issues', 'Search issues and pull requests in GitHub repository', method: 'searchIssues')]
#[AsTool('github_search_code', 'Search code in GitHub repository', method: 'searchCode')]
#[AsTool('github_releases', 'Get releases from GitHub repository', method: 'getReleases')]
final readonly class GitHub
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $token,
        private string $repository,
        private array $options = [],
    ) {
    }

    /**
     * Get open issues from GitHub repository.
     *
     * @return array<int, array{
     *     number: int,
     *     title: string,
     *     state: string,
     *     user: string,
     *     created_at: string,
     *     updated_at: string,
     *     html_url: string,
     * }>
     */
    public function getIssues(): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/issues", [
                'headers' => $this->getHeaders(),
                'query' => [
                    'state' => 'open',
                    'per_page' => 50,
                ],
            ]);

            $data = $response->toArray();
            $issues = [];

            foreach ($data as $issue) {
                // Filter out pull requests (they appear in issues API)
                if (!isset($issue['pull_request'])) {
                    $issues[] = [
                        'number' => $issue['number'],
                        'title' => $issue['title'],
                        'state' => $issue['state'],
                        'user' => $issue['user']['login'] ?? '',
                        'created_at' => $issue['created_at'],
                        'updated_at' => $issue['updated_at'],
                        'html_url' => $issue['html_url'],
                    ];
                }
            }

            return $issues;
        } catch (\Exception $e) {
            return [
                [
                    'number' => 0,
                    'title' => 'Error',
                    'state' => 'error',
                    'user' => '',
                    'created_at' => '',
                    'updated_at' => '',
                    'html_url' => '',
                ],
            ];
        }
    }

    /**
     * Get open pull requests from GitHub repository.
     *
     * @return array<int, array{
     *     number: int,
     *     title: string,
     *     state: string,
     *     user: string,
     *     created_at: string,
     *     updated_at: string,
     *     html_url: string,
     *     base: array{branch: string},
     *     head: array{branch: string},
     * }>
     */
    public function getPullRequests(): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/pulls", [
                'headers' => $this->getHeaders(),
                'query' => [
                    'state' => 'open',
                    'per_page' => 50,
                ],
            ]);

            $data = $response->toArray();
            $pullRequests = [];

            foreach ($data as $pr) {
                $pullRequests[] = [
                    'number' => $pr['number'],
                    'title' => $pr['title'],
                    'state' => $pr['state'],
                    'user' => $pr['user']['login'] ?? '',
                    'created_at' => $pr['created_at'],
                    'updated_at' => $pr['updated_at'],
                    'html_url' => $pr['html_url'],
                    'base' => ['branch' => $pr['base']['ref']],
                    'head' => ['branch' => $pr['head']['ref']],
                ];
            }

            return $pullRequests;
        } catch (\Exception $e) {
            return [
                [
                    'number' => 0,
                    'title' => 'Error',
                    'state' => 'error',
                    'user' => '',
                    'created_at' => '',
                    'updated_at' => '',
                    'html_url' => '',
                    'base' => ['branch' => ''],
                    'head' => ['branch' => ''],
                ],
            ];
        }
    }

    /**
     * Get specific issue details from GitHub repository.
     *
     * @param int $issueNumber The issue number
     *
     * @return array{
     *     number: int,
     *     title: string,
     *     body: string,
     *     state: string,
     *     user: string,
     *     created_at: string,
     *     updated_at: string,
     *     html_url: string,
     *     comments: array<int, array{body: string, user: string, created_at: string}>,
     * }
     */
    public function getIssue(int $issueNumber): array
    {
        try {
            // Get issue details
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/issues/{$issueNumber}", [
                'headers' => $this->getHeaders(),
            ]);

            $issue = $response->toArray();

            // Get comments
            $commentsResponse = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/issues/{$issueNumber}/comments", [
                'headers' => $this->getHeaders(),
                'query' => ['per_page' => 10],
            ]);

            $commentsData = $commentsResponse->toArray();
            $comments = [];

            foreach ($commentsData as $comment) {
                $comments[] = [
                    'body' => $comment['body'],
                    'user' => $comment['user']['login'] ?? '',
                    'created_at' => $comment['created_at'],
                ];
            }

            return [
                'number' => $issue['number'],
                'title' => $issue['title'],
                'body' => $issue['body'] ?? '',
                'state' => $issue['state'],
                'user' => $issue['user']['login'] ?? '',
                'created_at' => $issue['created_at'],
                'updated_at' => $issue['updated_at'],
                'html_url' => $issue['html_url'],
                'comments' => $comments,
            ];
        } catch (\Exception $e) {
            return [
                'number' => $issueNumber,
                'title' => 'Error',
                'body' => 'Unable to fetch issue: '.$e->getMessage(),
                'state' => 'error',
                'user' => '',
                'created_at' => '',
                'updated_at' => '',
                'html_url' => '',
                'comments' => [],
            ];
        }
    }

    /**
     * Get specific pull request details from GitHub repository.
     *
     * @param int $prNumber The pull request number
     *
     * @return array{
     *     number: int,
     *     title: string,
     *     body: string,
     *     state: string,
     *     user: string,
     *     created_at: string,
     *     updated_at: string,
     *     html_url: string,
     *     base: array{branch: string},
     *     head: array{branch: string},
     *     comments: array<int, array{body: string, user: string, created_at: string}>,
     * }
     */
    public function getPullRequest(int $prNumber): array
    {
        try {
            // Get PR details
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/pulls/{$prNumber}", [
                'headers' => $this->getHeaders(),
            ]);

            $pr = $response->toArray();

            // Get comments
            $commentsResponse = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/issues/{$prNumber}/comments", [
                'headers' => $this->getHeaders(),
                'query' => ['per_page' => 10],
            ]);

            $commentsData = $commentsResponse->toArray();
            $comments = [];

            foreach ($commentsData as $comment) {
                $comments[] = [
                    'body' => $comment['body'],
                    'user' => $comment['user']['login'] ?? '',
                    'created_at' => $comment['created_at'],
                ];
            }

            return [
                'number' => $pr['number'],
                'title' => $pr['title'],
                'body' => $pr['body'] ?? '',
                'state' => $pr['state'],
                'user' => $pr['user']['login'] ?? '',
                'created_at' => $pr['created_at'],
                'updated_at' => $pr['updated_at'],
                'html_url' => $pr['html_url'],
                'base' => ['branch' => $pr['base']['ref']],
                'head' => ['branch' => $pr['head']['ref']],
                'comments' => $comments,
            ];
        } catch (\Exception $e) {
            return [
                'number' => $prNumber,
                'title' => 'Error',
                'body' => 'Unable to fetch pull request: '.$e->getMessage(),
                'state' => 'error',
                'user' => '',
                'created_at' => '',
                'updated_at' => '',
                'html_url' => '',
                'base' => ['branch' => ''],
                'head' => ['branch' => ''],
                'comments' => [],
            ];
        }
    }

    /**
     * List all branches in GitHub repository.
     *
     * @return array<int, array{
     *     name: string,
     *     protected: bool,
     *     commit: array{sha: string, url: string},
     * }>
     */
    public function getBranches(): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/branches", [
                'headers' => $this->getHeaders(),
                'query' => ['per_page' => 100],
            ]);

            $data = $response->toArray();
            $branches = [];

            foreach ($data as $branch) {
                $branches[] = [
                    'name' => $branch['name'],
                    'protected' => $branch['protected'] ?? false,
                    'commit' => [
                        'sha' => $branch['commit']['sha'],
                        'url' => $branch['commit']['url'],
                    ],
                ];
            }

            return $branches;
        } catch (\Exception $e) {
            return [
                [
                    'name' => 'error',
                    'protected' => false,
                    'commit' => ['sha' => '', 'url' => ''],
                ],
            ];
        }
    }

    /**
     * List files in GitHub repository.
     *
     * @param string $branch The branch to list files from (defaults to main)
     * @param string $path   The path to list (defaults to root)
     *
     * @return array<int, array{
     *     name: string,
     *     path: string,
     *     type: string,
     *     size: int,
     *     download_url: string|null,
     * }>
     */
    public function getFiles(string $branch = 'main', string $path = ''): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/contents/{$path}", [
                'headers' => $this->getHeaders(),
                'query' => ['ref' => $branch],
            ]);

            $data = $response->toArray();
            $files = [];

            foreach ($data as $file) {
                $files[] = [
                    'name' => $file['name'],
                    'path' => $file['path'],
                    'type' => $file['type'],
                    'size' => $file['size'] ?? 0,
                    'download_url' => $file['download_url'] ?? null,
                ];
            }

            return $files;
        } catch (\Exception $e) {
            return [
                [
                    'name' => 'error',
                    'path' => '',
                    'type' => 'error',
                    'size' => 0,
                    'download_url' => null,
                ],
            ];
        }
    }

    /**
     * Get content of a specific file from GitHub repository.
     *
     * @param string $filePath The path to the file
     * @param string $branch   The branch to get the file from (defaults to main)
     */
    public function getFileContent(string $filePath, string $branch = 'main'): string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/contents/{$filePath}", [
                'headers' => $this->getHeaders(),
                'query' => ['ref' => $branch],
            ]);

            $data = $response->toArray();

            if (isset($data['content'])) {
                return base64_decode($data['content']);
            }

            return 'File not found or not accessible';
        } catch (\Exception $e) {
            return 'Error fetching file content: '.$e->getMessage();
        }
    }

    /**
     * Search issues and pull requests in GitHub repository.
     *
     * @param string $query The search query
     *
     * @return array<int, array{
     *     number: int,
     *     title: string,
     *     state: string,
     *     html_url: string,
     * }>
     */
    public function searchIssues(string $query): array
    {
        try {
            $searchQuery = "repo:{$this->repository} {$query}";
            $response = $this->httpClient->request('GET', 'https://api.github.com/search/issues', [
                'headers' => $this->getHeaders(),
                'query' => [
                    'q' => $searchQuery,
                    'per_page' => 10,
                ],
            ]);

            $data = $response->toArray();
            $results = [];

            foreach ($data['items'] as $item) {
                $results[] = [
                    'number' => $item['number'],
                    'title' => $item['title'],
                    'state' => $item['state'],
                    'html_url' => $item['html_url'],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'number' => 0,
                    'title' => 'Error',
                    'state' => 'error',
                    'html_url' => '',
                ],
            ];
        }
    }

    /**
     * Search code in GitHub repository.
     *
     * @param string $query The search query
     *
     * @return array<int, array{
     *     name: string,
     *     path: string,
     *     html_url: string,
     *     repository: array{full_name: string},
     * }>
     */
    public function searchCode(string $query): array
    {
        try {
            $searchQuery = "{$query} repo:{$this->repository}";
            $response = $this->httpClient->request('GET', 'https://api.github.com/search/code', [
                'headers' => $this->getHeaders(),
                'query' => [
                    'q' => $searchQuery,
                    'per_page' => 10,
                ],
            ]);

            $data = $response->toArray();
            $results = [];

            foreach ($data['items'] as $item) {
                $results[] = [
                    'name' => $item['name'],
                    'path' => $item['path'],
                    'html_url' => $item['html_url'],
                    'repository' => ['full_name' => $item['repository']['full_name']],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'name' => 'error',
                    'path' => '',
                    'html_url' => '',
                    'repository' => ['full_name' => ''],
                ],
            ];
        }
    }

    /**
     * Get releases from GitHub repository.
     *
     * @return array<int, array{
     *     tag_name: string,
     *     name: string,
     *     body: string,
     *     created_at: string,
     *     published_at: string,
     *     html_url: string,
     * }>
     */
    public function getReleases(): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.github.com/repos/{$this->repository}/releases", [
                'headers' => $this->getHeaders(),
                'query' => ['per_page' => 10],
            ]);

            $data = $response->toArray();
            $releases = [];

            foreach ($data as $release) {
                $releases[] = [
                    'tag_name' => $release['tag_name'],
                    'name' => $release['name'] ?? '',
                    'body' => $release['body'] ?? '',
                    'created_at' => $release['created_at'],
                    'published_at' => $release['published_at'] ?? '',
                    'html_url' => $release['html_url'],
                ];
            }

            return $releases;
        } catch (\Exception $e) {
            return [
                [
                    'tag_name' => 'error',
                    'name' => 'Error',
                    'body' => 'Unable to fetch releases: '.$e->getMessage(),
                    'created_at' => '',
                    'published_at' => '',
                    'html_url' => '',
                ],
            ];
        }
    }

    /**
     * Get authentication headers.
     *
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => "token {$this->token}",
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Symfony-AI-Agent/1.0',
        ];
    }
}
