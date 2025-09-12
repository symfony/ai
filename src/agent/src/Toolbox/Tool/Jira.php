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
#[AsTool('jira_search', 'Tool that searches Jira issues')]
#[AsTool('jira_create_issue', 'Tool that creates a new Jira issue', method: 'createIssue')]
#[AsTool('jira_update_issue', 'Tool that updates an existing Jira issue', method: 'updateIssue')]
#[AsTool('jira_get_issue', 'Tool that gets details of a specific Jira issue', method: 'getIssue')]
#[AsTool('jira_add_comment', 'Tool that adds a comment to a Jira issue', method: 'addComment')]
#[AsTool('jira_get_projects', 'Tool that gets list of Jira projects', method: 'getProjects')]
final readonly class Jira
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        #[\SensitiveParameter] private string $username,
        #[\SensitiveParameter] private string $apiToken,
        private bool $isCloud = true,
        private array $options = [],
    ) {
    }

    /**
     * Search Jira issues.
     *
     * @param string                $jql        JQL (Jira Query Language) query
     * @param int                   $maxResults Maximum number of results to return
     * @param array<string, string> $fields     Fields to include in results
     *
     * @return array<int, array{
     *     key: string,
     *     summary: string,
     *     description: string,
     *     status: string,
     *     priority: string,
     *     assignee: string,
     *     reporter: string,
     *     created: string,
     *     updated: string,
     *     issue_type: string,
     *     project: string,
     *     labels: array<int, string>,
     *     url: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 1000)]
        string $jql,
        int $maxResults = 50,
        array $fields = ['summary', 'status', 'assignee', 'priority'],
    ): array {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.'/rest/api/3/search', [
                'headers' => $this->getHeaders(),
                'json' => [
                    'jql' => $jql,
                    'maxResults' => $maxResults,
                    'fields' => $fields,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['issues'])) {
                return [];
            }

            $results = [];
            foreach ($data['issues'] as $issue) {
                $fields = $issue['fields'];

                $results[] = [
                    'key' => $issue['key'],
                    'summary' => $fields['summary'] ?? '',
                    'description' => $this->extractDescription($fields['description'] ?? null),
                    'status' => $fields['status']['name'] ?? '',
                    'priority' => $fields['priority']['name'] ?? '',
                    'assignee' => $fields['assignee']['displayName'] ?? 'Unassigned',
                    'reporter' => $fields['reporter']['displayName'] ?? '',
                    'created' => $fields['created'] ?? '',
                    'updated' => $fields['updated'] ?? '',
                    'issue_type' => $fields['issuetype']['name'] ?? '',
                    'project' => $fields['project']['name'] ?? '',
                    'labels' => $fields['labels'] ?? [],
                    'url' => $this->baseUrl.'/browse/'.$issue['key'],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'key' => 'ERROR',
                    'summary' => 'Search Error',
                    'description' => 'Unable to search Jira issues: '.$e->getMessage(),
                    'status' => '',
                    'priority' => '',
                    'assignee' => '',
                    'reporter' => '',
                    'created' => '',
                    'updated' => '',
                    'issue_type' => '',
                    'project' => '',
                    'labels' => [],
                    'url' => '',
                ],
            ];
        }
    }

    /**
     * Create a new Jira issue.
     *
     * @param string             $projectKey  Project key (e.g., 'PROJ')
     * @param string             $issueType   Issue type (e.g., 'Task', 'Bug', 'Story')
     * @param string             $summary     Issue summary/title
     * @param string             $description Issue description
     * @param string             $assignee    Assignee username (optional)
     * @param string             $priority    Priority (e.g., 'High', 'Medium', 'Low')
     * @param array<int, string> $labels      Issue labels
     *
     * @return array{
     *     key: string,
     *     id: string,
     *     url: string,
     *     summary: string,
     * }|string
     */
    public function createIssue(
        string $projectKey,
        string $issueType,
        string $summary,
        string $description = '',
        string $assignee = '',
        string $priority = '',
        array $labels = [],
    ): array|string {
        try {
            $issueData = [
                'fields' => [
                    'project' => ['key' => $projectKey],
                    'summary' => $summary,
                    'issuetype' => ['name' => $issueType],
                ],
            ];

            if ($description) {
                $issueData['fields']['description'] = [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $description,
                                ],
                            ],
                        ],
                    ],
                ];
            }

            if ($assignee) {
                $issueData['fields']['assignee'] = ['name' => $assignee];
            }

            if ($priority) {
                $issueData['fields']['priority'] = ['name' => $priority];
            }

            if (!empty($labels)) {
                $issueData['fields']['labels'] = $labels;
            }

            $response = $this->httpClient->request('POST', $this->baseUrl.'/rest/api/3/issue', [
                'headers' => $this->getHeaders(),
                'json' => $issueData,
            ]);

            $data = $response->toArray();

            return [
                'key' => $data['key'],
                'id' => $data['id'],
                'url' => $this->baseUrl.'/browse/'.$data['key'],
                'summary' => $summary,
            ];
        } catch (\Exception $e) {
            return 'Error creating issue: '.$e->getMessage();
        }
    }

    /**
     * Update an existing Jira issue.
     *
     * @param string               $issueKey Issue key (e.g., 'PROJ-123')
     * @param array<string, mixed> $updates  Fields to update
     */
    public function updateIssue(string $issueKey, array $updates): string
    {
        try {
            $response = $this->httpClient->request('PUT', $this->baseUrl."/rest/api/3/issue/{$issueKey}", [
                'headers' => $this->getHeaders(),
                'json' => [
                    'fields' => $updates,
                ],
            ]);

            if (204 === $response->getStatusCode()) {
                return "Issue {$issueKey} updated successfully";
            } else {
                return "Failed to update issue {$issueKey}";
            }
        } catch (\Exception $e) {
            return 'Error updating issue: '.$e->getMessage();
        }
    }

    /**
     * Get details of a specific Jira issue.
     *
     * @param string $issueKey Issue key (e.g., 'PROJ-123')
     *
     * @return array<string, mixed>|string
     */
    public function getIssue(string $issueKey): array|string
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl."/rest/api/3/issue/{$issueKey}", [
                'headers' => $this->getHeaders(),
                'query' => [
                    'expand' => 'renderedFields,changelog',
                ],
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return 'Error getting issue: '.$e->getMessage();
        }
    }

    /**
     * Add a comment to a Jira issue.
     *
     * @param string $issueKey Issue key (e.g., 'PROJ-123')
     * @param string $comment  Comment text
     *
     * @return array{
     *     id: string,
     *     body: string,
     *     created: string,
     *     author: string,
     * }|string
     */
    public function addComment(string $issueKey, string $comment): array|string
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl."/rest/api/3/issue/{$issueKey}/comment", [
                'headers' => $this->getHeaders(),
                'json' => [
                    'body' => [
                        'type' => 'doc',
                        'version' => 1,
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => $comment,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $data = $response->toArray();

            return [
                'id' => $data['id'],
                'body' => $comment,
                'created' => $data['created'],
                'author' => $data['author']['displayName'],
            ];
        } catch (\Exception $e) {
            return 'Error adding comment: '.$e->getMessage();
        }
    }

    /**
     * Get list of Jira projects.
     *
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     project_type: string,
     *     lead: string,
     *     url: string,
     * }>
     */
    public function getProjects(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/rest/api/3/project', [
                'headers' => $this->getHeaders(),
            ]);

            $data = $response->toArray();

            $results = [];
            foreach ($data as $project) {
                $results[] = [
                    'key' => $project['key'],
                    'name' => $project['name'],
                    'description' => $project['description'] ?? '',
                    'project_type' => $project['projectTypeKey'],
                    'lead' => $project['lead']['displayName'],
                    'url' => $this->baseUrl.'/browse/'.$project['key'],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'key' => 'ERROR',
                    'name' => 'Error',
                    'description' => 'Unable to get projects: '.$e->getMessage(),
                    'project_type' => '',
                    'lead' => '',
                    'url' => '',
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
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->isCloud) {
            $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->apiToken);
        } else {
            $headers['Authorization'] = 'Bearer '.$this->apiToken;
        }

        return $headers;
    }

    /**
     * Extract text description from Jira's rich text format.
     */
    private function extractDescription(?array $description): string
    {
        if (!$description) {
            return '';
        }

        if (isset($description['content'])) {
            return $this->extractTextFromContent($description['content']);
        }

        if (\is_string($description)) {
            return $description;
        }

        return '';
    }

    /**
     * Extract plain text from Jira's content structure.
     *
     * @param array<int, array<string, mixed>> $content
     */
    private function extractTextFromContent(array $content): string
    {
        $text = '';

        foreach ($content as $block) {
            if (isset($block['content'])) {
                foreach ($block['content'] as $item) {
                    if (isset($item['text'])) {
                        $text .= $item['text'];
                    }
                }
            }
        }

        return $text;
    }
}
