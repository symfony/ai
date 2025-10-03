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
#[AsTool('linear_get_issues', 'Tool that gets Linear issues')]
#[AsTool('linear_create_issue', 'Tool that creates Linear issues', method: 'createIssue')]
#[AsTool('linear_update_issue', 'Tool that updates Linear issues', method: 'updateIssue')]
#[AsTool('linear_get_projects', 'Tool that gets Linear projects', method: 'getProjects')]
#[AsTool('linear_get_teams', 'Tool that gets Linear teams', method: 'getTeams')]
#[AsTool('linear_get_cycles', 'Tool that gets Linear cycles', method: 'getCycles')]
final readonly class Linear
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $apiVersion = '2023-10-16',
        private array $options = [],
    ) {
    }

    /**
     * Get Linear issues.
     *
     * @param string $teamId     Team ID to filter issues
     * @param string $projectId  Project ID to filter issues
     * @param string $state      Issue state (triage, backlog, unstarted, started, completed, canceled)
     * @param string $priority   Issue priority (urgent, high, normal, low)
     * @param string $assigneeId Assignee ID to filter issues
     * @param int    $first      Number of issues to retrieve
     * @param string $after      Cursor for pagination
     *
     * @return array{
     *     issues: array<int, array{
     *         id: string,
     *         identifier: string,
     *         title: string,
     *         description: string,
     *         priority: int,
     *         estimate: int|null,
     *         state: array{id: string, name: string, type: string},
     *         team: array{id: string, name: string, key: string},
     *         project: array{id: string, name: string}|null,
     *         assignee: array{id: string, name: string, email: string}|null,
     *         creator: array{id: string, name: string, email: string},
     *         labels: array<int, array{id: string, name: string, color: string}>,
     *         createdAt: string,
     *         updatedAt: string,
     *         completedAt: string|null,
     *         url: string,
     *     }>,
     *     pageInfo: array{hasNextPage: bool, endCursor: string|null},
     * }|string
     */
    public function __invoke(
        string $teamId = '',
        string $projectId = '',
        string $state = '',
        string $priority = '',
        string $assigneeId = '',
        int $first = 50,
        string $after = '',
    ): array|string {
        try {
            $query = $this->buildIssuesQuery($teamId, $projectId, $state, $priority, $assigneeId, $first, $after);

            $response = $this->httpClient->request('POST', 'https://api.linear.app/graphql', [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error getting issues: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $issues = $data['data']['issues']['nodes'] ?? [];
            $pageInfo = $data['data']['issues']['pageInfo'] ?? ['hasNextPage' => false, 'endCursor' => null];

            return [
                'issues' => array_map(fn ($issue) => [
                    'id' => $issue['id'],
                    'identifier' => $issue['identifier'],
                    'title' => $issue['title'],
                    'description' => $issue['description'] ?? '',
                    'priority' => $issue['priority'],
                    'estimate' => $issue['estimate'],
                    'state' => [
                        'id' => $issue['state']['id'],
                        'name' => $issue['state']['name'],
                        'type' => $issue['state']['type'],
                    ],
                    'team' => [
                        'id' => $issue['team']['id'],
                        'name' => $issue['team']['name'],
                        'key' => $issue['team']['key'],
                    ],
                    'project' => $issue['project'] ? [
                        'id' => $issue['project']['id'],
                        'name' => $issue['project']['name'],
                    ] : null,
                    'assignee' => $issue['assignee'] ? [
                        'id' => $issue['assignee']['id'],
                        'name' => $issue['assignee']['name'],
                        'email' => $issue['assignee']['email'],
                    ] : null,
                    'creator' => [
                        'id' => $issue['creator']['id'],
                        'name' => $issue['creator']['name'],
                        'email' => $issue['creator']['email'],
                    ],
                    'labels' => array_map(fn ($label) => [
                        'id' => $label['id'],
                        'name' => $label['name'],
                        'color' => $label['color'],
                    ], $issue['labels']['nodes'] ?? []),
                    'createdAt' => $issue['createdAt'],
                    'updatedAt' => $issue['updatedAt'],
                    'completedAt' => $issue['completedAt'],
                    'url' => $issue['url'],
                ], $issues),
                'pageInfo' => $pageInfo,
            ];
        } catch (\Exception $e) {
            return 'Error getting issues: '.$e->getMessage();
        }
    }

    /**
     * Create a Linear issue.
     *
     * @param string             $teamId      Team ID
     * @param string             $title       Issue title
     * @param string             $description Issue description
     * @param string             $projectId   Project ID (optional)
     * @param string             $assigneeId  Assignee ID (optional)
     * @param string             $stateId     State ID (optional)
     * @param int                $priority    Priority (0=urgent, 1=high, 2=normal, 3=low)
     * @param int                $estimate    Story points estimate (optional)
     * @param array<int, string> $labelIds    Label IDs (optional)
     *
     * @return array{
     *     id: string,
     *     identifier: string,
     *     title: string,
     *     description: string,
     *     priority: int,
     *     estimate: int|null,
     *     state: array{id: string, name: string, type: string},
     *     team: array{id: string, name: string, key: string},
     *     project: array{id: string, name: string}|null,
     *     assignee: array{id: string, name: string, email: string}|null,
     *     creator: array{id: string, name: string, email: string},
     *     labels: array<int, array{id: string, name: string, color: string}>,
     *     createdAt: string,
     *     updatedAt: string,
     *     url: string,
     * }|string
     */
    public function createIssue(
        string $teamId,
        string $title,
        string $description = '',
        string $projectId = '',
        string $assigneeId = '',
        string $stateId = '',
        int $priority = 2,
        int $estimate = 0,
        array $labelIds = [],
    ): array|string {
        try {
            $mutation = $this->buildCreateIssueMutation($teamId, $title, $description, $projectId, $assigneeId, $stateId, $priority, $estimate, $labelIds);

            $response = $this->httpClient->request('POST', 'https://api.linear.app/graphql', [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $mutation,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error creating issue: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $issue = $data['data']['issueCreate']['issue'];

            return [
                'id' => $issue['id'],
                'identifier' => $issue['identifier'],
                'title' => $issue['title'],
                'description' => $issue['description'] ?? '',
                'priority' => $issue['priority'],
                'estimate' => $issue['estimate'],
                'state' => [
                    'id' => $issue['state']['id'],
                    'name' => $issue['state']['name'],
                    'type' => $issue['state']['type'],
                ],
                'team' => [
                    'id' => $issue['team']['id'],
                    'name' => $issue['team']['name'],
                    'key' => $issue['team']['key'],
                ],
                'project' => $issue['project'] ? [
                    'id' => $issue['project']['id'],
                    'name' => $issue['project']['name'],
                ] : null,
                'assignee' => $issue['assignee'] ? [
                    'id' => $issue['assignee']['id'],
                    'name' => $issue['assignee']['name'],
                    'email' => $issue['assignee']['email'],
                ] : null,
                'creator' => [
                    'id' => $issue['creator']['id'],
                    'name' => $issue['creator']['name'],
                    'email' => $issue['creator']['email'],
                ],
                'labels' => array_map(fn ($label) => [
                    'id' => $label['id'],
                    'name' => $label['name'],
                    'color' => $label['color'],
                ], $issue['labels']['nodes'] ?? []),
                'createdAt' => $issue['createdAt'],
                'updatedAt' => $issue['updatedAt'],
                'url' => $issue['url'],
            ];
        } catch (\Exception $e) {
            return 'Error creating issue: '.$e->getMessage();
        }
    }

    /**
     * Update a Linear issue.
     *
     * @param string $issueId     Issue ID to update
     * @param string $title       New title (optional)
     * @param string $description New description (optional)
     * @param string $stateId     New state ID (optional)
     * @param string $assigneeId  New assignee ID (optional)
     * @param int    $priority    New priority (optional)
     * @param int    $estimate    New estimate (optional)
     */
    public function updateIssue(
        string $issueId,
        string $title = '',
        string $description = '',
        string $stateId = '',
        string $assigneeId = '',
        int $priority = -1,
        int $estimate = -1,
    ): string {
        try {
            $mutation = $this->buildUpdateIssueMutation($issueId, $title, $description, $stateId, $assigneeId, $priority, $estimate);

            $response = $this->httpClient->request('POST', 'https://api.linear.app/graphql', [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $mutation,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error updating issue: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            return 'Issue updated successfully';
        } catch (\Exception $e) {
            return 'Error updating issue: '.$e->getMessage();
        }
    }

    /**
     * Get Linear projects.
     *
     * @param string $teamId Team ID to filter projects
     * @param int    $first  Number of projects to retrieve
     *
     * @return array{
     *     projects: array<int, array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         state: string,
     *         progress: float,
     *         startDate: string|null,
     *         targetDate: string|null,
     *         team: array{id: string, name: string, key: string},
     *         creator: array{id: string, name: string, email: string},
     *         createdAt: string,
     *         updatedAt: string,
     *         url: string,
     *     }>,
     * }|string
     */
    public function getProjects(
        string $teamId = '',
        int $first = 50,
    ): array|string {
        try {
            $query = $this->buildProjectsQuery($teamId, $first);

            $response = $this->httpClient->request('POST', 'https://api.linear.app/graphql', [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error getting projects: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $projects = $data['data']['projects']['nodes'] ?? [];

            return [
                'projects' => array_map(fn ($project) => [
                    'id' => $project['id'],
                    'name' => $project['name'],
                    'description' => $project['description'] ?? '',
                    'state' => $project['state'],
                    'progress' => $project['progress'],
                    'startDate' => $project['startDate'],
                    'targetDate' => $project['targetDate'],
                    'team' => [
                        'id' => $project['team']['id'],
                        'name' => $project['team']['name'],
                        'key' => $project['team']['key'],
                    ],
                    'creator' => [
                        'id' => $project['creator']['id'],
                        'name' => $project['creator']['name'],
                        'email' => $project['creator']['email'],
                    ],
                    'createdAt' => $project['createdAt'],
                    'updatedAt' => $project['updatedAt'],
                    'url' => $project['url'],
                ], $projects),
            ];
        } catch (\Exception $e) {
            return 'Error getting projects: '.$e->getMessage();
        }
    }

    /**
     * Get Linear teams.
     *
     * @param int $first Number of teams to retrieve
     *
     * @return array{
     *     teams: array<int, array{
     *         id: string,
     *         name: string,
     *         key: string,
     *         description: string,
     *         private: bool,
     *         timezone: string,
     *         issueOrdering: string,
     *         createdAt: string,
     *         updatedAt: string,
     *     }>,
     * }|string
     */
    public function getTeams(int $first = 50): array|string
    {
        try {
            $query = '
                query GetTeams($first: Int!) {
                    teams(first: $first) {
                        nodes {
                            id
                            name
                            key
                            description
                            private
                            timezone
                            issueOrdering
                            createdAt
                            updatedAt
                        }
                    }
                }
            ';

            $response = $this->httpClient->request('POST', 'https://api.linear.app/graphql', [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                    'variables' => ['first' => $first],
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error getting teams: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $teams = $data['data']['teams']['nodes'] ?? [];

            return [
                'teams' => array_map(fn ($team) => [
                    'id' => $team['id'],
                    'name' => $team['name'],
                    'key' => $team['key'],
                    'description' => $team['description'] ?? '',
                    'private' => $team['private'],
                    'timezone' => $team['timezone'],
                    'issueOrdering' => $team['issueOrdering'],
                    'createdAt' => $team['createdAt'],
                    'updatedAt' => $team['updatedAt'],
                ], $teams),
            ];
        } catch (\Exception $e) {
            return 'Error getting teams: '.$e->getMessage();
        }
    }

    /**
     * Get Linear cycles.
     *
     * @param string $teamId Team ID to filter cycles
     * @param int    $first  Number of cycles to retrieve
     *
     * @return array{
     *     cycles: array<int, array{
     *         id: string,
     *         number: int,
     *         name: string,
     *         description: string,
     *         state: string,
     *         startDate: string,
     *         endDate: string,
     *         completedAt: string|null,
     *         team: array{id: string, name: string, key: string},
     *         createdAt: string,
     *         updatedAt: string,
     *     }>,
     * }|string
     */
    public function getCycles(
        string $teamId = '',
        int $first = 50,
    ): array|string {
        try {
            $query = $this->buildCyclesQuery($teamId, $first);

            $response = $this->httpClient->request('POST', 'https://api.linear.app/graphql', [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error getting cycles: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $cycles = $data['data']['cycles']['nodes'] ?? [];

            return [
                'cycles' => array_map(fn ($cycle) => [
                    'id' => $cycle['id'],
                    'number' => $cycle['number'],
                    'name' => $cycle['name'],
                    'description' => $cycle['description'] ?? '',
                    'state' => $cycle['state'],
                    'startDate' => $cycle['startDate'],
                    'endDate' => $cycle['endDate'],
                    'completedAt' => $cycle['completedAt'],
                    'team' => [
                        'id' => $cycle['team']['id'],
                        'name' => $cycle['team']['name'],
                        'key' => $cycle['team']['key'],
                    ],
                    'createdAt' => $cycle['createdAt'],
                    'updatedAt' => $cycle['updatedAt'],
                ], $cycles),
            ];
        } catch (\Exception $e) {
            return 'Error getting cycles: '.$e->getMessage();
        }
    }

    /**
     * Build GraphQL query for getting issues.
     */
    private function buildIssuesQuery(string $teamId, string $projectId, string $state, string $priority, string $assigneeId, int $first, string $after): string
    {
        $filters = [];
        if ($teamId) {
            $filters[] = "team: { id: \"$teamId\" }";
        }
        if ($projectId) {
            $filters[] = "project: { id: \"$projectId\" }";
        }
        if ($state) {
            $filters[] = "state: { name: { eq: \"$state\" } }";
        }
        if ($priority) {
            $filters[] = 'priority: { eq: '.$this->priorityToInt($priority).' }';
        }
        if ($assigneeId) {
            $filters[] = "assignee: { id: \"$assigneeId\" }";
        }

        $filterString = !empty($filters) ? '{ '.implode(', ', $filters).' }' : '';
        $afterString = $after ? ", after: \"$after\"" : '';

        return "
            query GetIssues(\$first: Int!) {
                issues(filter: $filterString, first: \$first$afterString, orderBy: updatedAt) {
                    nodes {
                        id
                        identifier
                        title
                        description
                        priority
                        estimate
                        state {
                            id
                            name
                            type
                        }
                        team {
                            id
                            name
                            key
                        }
                        project {
                            id
                            name
                        }
                        assignee {
                            id
                            name
                            email
                        }
                        creator {
                            id
                            name
                            email
                        }
                        labels {
                            nodes {
                                id
                                name
                                color
                            }
                        }
                        createdAt
                        updatedAt
                        completedAt
                        url
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ";
    }

    /**
     * Build GraphQL mutation for creating issues.
     */
    private function buildCreateIssueMutation(string $teamId, string $title, string $description, string $projectId, string $assigneeId, string $stateId, int $priority, int $estimate, array $labelIds): string
    {
        $input = "teamId: \"$teamId\", title: \"$title\"";
        if ($description) {
            $input .= ", description: \"$description\"";
        }
        if ($projectId) {
            $input .= ", projectId: \"$projectId\"";
        }
        if ($assigneeId) {
            $input .= ", assigneeId: \"$assigneeId\"";
        }
        if ($stateId) {
            $input .= ", stateId: \"$stateId\"";
        }
        if ($priority >= 0) {
            $input .= ", priority: $priority";
        }
        if ($estimate > 0) {
            $input .= ", estimate: $estimate";
        }
        if (!empty($labelIds)) {
            $input .= ', labelIds: ["'.implode('", "', $labelIds).'"]';
        }

        return "
            mutation CreateIssue {
                issueCreate(input: { $input }) {
                    issue {
                        id
                        identifier
                        title
                        description
                        priority
                        estimate
                        state {
                            id
                            name
                            type
                        }
                        team {
                            id
                            name
                            key
                        }
                        project {
                            id
                            name
                        }
                        assignee {
                            id
                            name
                            email
                        }
                        creator {
                            id
                            name
                            email
                        }
                        labels {
                            nodes {
                                id
                                name
                                color
                            }
                        }
                        createdAt
                        updatedAt
                        url
                    }
                }
            }
        ";
    }

    /**
     * Build GraphQL mutation for updating issues.
     */
    private function buildUpdateIssueMutation(string $issueId, string $title, string $description, string $stateId, string $assigneeId, int $priority, int $estimate): string
    {
        $input = "id: \"$issueId\"";
        if ($title) {
            $input .= ", title: \"$title\"";
        }
        if ($description) {
            $input .= ", description: \"$description\"";
        }
        if ($stateId) {
            $input .= ", stateId: \"$stateId\"";
        }
        if ($assigneeId) {
            $input .= ", assigneeId: \"$assigneeId\"";
        }
        if ($priority >= 0) {
            $input .= ", priority: $priority";
        }
        if ($estimate >= 0) {
            $input .= ", estimate: $estimate";
        }

        return "
            mutation UpdateIssue {
                issueUpdate(input: { $input }) {
                    success
                }
            }
        ";
    }

    /**
     * Build GraphQL query for getting projects.
     */
    private function buildProjectsQuery(string $teamId, int $first): string
    {
        $filter = $teamId ? "{ team: { id: \"$teamId\" } }" : '';

        return "
            query GetProjects(\$first: Int!) {
                projects(filter: $filter, first: \$first, orderBy: updatedAt) {
                    nodes {
                        id
                        name
                        description
                        state
                        progress
                        startDate
                        targetDate
                        team {
                            id
                            name
                            key
                        }
                        creator {
                            id
                            name
                            email
                        }
                        createdAt
                        updatedAt
                        url
                    }
                }
            }
        ";
    }

    /**
     * Build GraphQL query for getting cycles.
     */
    private function buildCyclesQuery(string $teamId, int $first): string
    {
        $filter = $teamId ? "{ team: { id: \"$teamId\" } }" : '';

        return "
            query GetCycles(\$first: Int!) {
                cycles(filter: $filter, first: \$first, orderBy: updatedAt) {
                    nodes {
                        id
                        number
                        name
                        description
                        state
                        startDate
                        endDate
                        completedAt
                        team {
                            id
                            name
                            key
                        }
                        createdAt
                        updatedAt
                    }
                }
            }
        ";
    }

    /**
     * Convert priority string to integer.
     */
    private function priorityToInt(string $priority): int
    {
        return match (strtolower($priority)) {
            'urgent' => 0,
            'high' => 1,
            'normal' => 2,
            'low' => 3,
            default => 2,
        };
    }
}
