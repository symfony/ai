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
#[AsTool('notion_create_page', 'Tool that creates Notion pages')]
#[AsTool('notion_get_page', 'Tool that gets Notion pages', method: 'getPage')]
#[AsTool('notion_update_page', 'Tool that updates Notion pages', method: 'updatePage')]
#[AsTool('notion_query_database', 'Tool that queries Notion databases', method: 'queryDatabase')]
#[AsTool('notion_create_database_entry', 'Tool that creates Notion database entries', method: 'createDatabaseEntry')]
#[AsTool('notion_search', 'Tool that searches Notion content', method: 'search')]
final readonly class Notion
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = '2022-06-28',
        private array $options = [],
    ) {
    }

    /**
     * Create a Notion page.
     *
     * @param string               $parentId   Parent page or database ID
     * @param string               $title      Page title
     * @param string               $content    Page content (markdown)
     * @param array<string, mixed> $properties Page properties
     *
     * @return array{
     *     id: string,
     *     object: string,
     *     created_time: string,
     *     last_edited_time: string,
     *     parent: array{type: string, page_id: string}|array{type: string, database_id: string},
     *     properties: array<string, mixed>,
     *     url: string,
     * }|string
     */
    public function __invoke(
        string $parentId,
        string $title,
        string $content = '',
        array $properties = [],
    ): array|string {
        try {
            $payload = [
                'parent' => [
                    'page_id' => $parentId,
                ],
                'properties' => array_merge([
                    'title' => [
                        'title' => [
                            [
                                'text' => [
                                    'content' => $title,
                                ],
                            ],
                        ],
                    ],
                ], $properties),
            ];

            if ($content) {
                $payload['children'] = $this->convertMarkdownToBlocks($content);
            }

            $response = $this->httpClient->request('POST', 'https://api.notion.com/v1/pages', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                    'Notion-Version' => $this->apiVersion,
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating page: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'object' => $data['object'],
                'created_time' => $data['created_time'],
                'last_edited_time' => $data['last_edited_time'],
                'parent' => $data['parent'],
                'properties' => $data['properties'],
                'url' => $data['url'],
            ];
        } catch (\Exception $e) {
            return 'Error creating page: '.$e->getMessage();
        }
    }

    /**
     * Get a Notion page.
     *
     * @param string $pageId Page ID
     *
     * @return array{
     *     id: string,
     *     object: string,
     *     created_time: string,
     *     last_edited_time: string,
     *     parent: array{type: string, page_id: string}|array{type: string, database_id: string},
     *     properties: array<string, mixed>,
     *     url: string,
     * }|string
     */
    public function getPage(string $pageId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.notion.com/v1/pages/{$pageId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                    'Notion-Version' => $this->apiVersion,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting page: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'object' => $data['object'],
                'created_time' => $data['created_time'],
                'last_edited_time' => $data['last_edited_time'],
                'parent' => $data['parent'],
                'properties' => $data['properties'],
                'url' => $data['url'],
            ];
        } catch (\Exception $e) {
            return 'Error getting page: '.$e->getMessage();
        }
    }

    /**
     * Update a Notion page.
     *
     * @param string               $pageId     Page ID
     * @param array<string, mixed> $properties Properties to update
     *
     * @return array{
     *     id: string,
     *     object: string,
     *     created_time: string,
     *     last_edited_time: string,
     *     parent: array{type: string, page_id: string}|array{type: string, database_id: string},
     *     properties: array<string, mixed>,
     *     url: string,
     * }|string
     */
    public function updatePage(
        string $pageId,
        array $properties,
    ): array|string {
        try {
            $payload = [
                'properties' => $properties,
            ];

            $response = $this->httpClient->request('PATCH', "https://api.notion.com/v1/pages/{$pageId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                    'Notion-Version' => $this->apiVersion,
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error updating page: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'object' => $data['object'],
                'created_time' => $data['created_time'],
                'last_edited_time' => $data['last_edited_time'],
                'parent' => $data['parent'],
                'properties' => $data['properties'],
                'url' => $data['url'],
            ];
        } catch (\Exception $e) {
            return 'Error updating page: '.$e->getMessage();
        }
    }

    /**
     * Query a Notion database.
     *
     * @param string                                                 $databaseId  Database ID
     * @param array<string, mixed>                                   $filter      Database filter
     * @param array<int, array{property: string, direction: string}> $sorts       Sort criteria
     * @param int                                                    $pageSize    Number of results per page
     * @param string                                                 $startCursor Pagination cursor
     *
     * @return array{
     *     results: array<int, array{
     *         id: string,
     *         object: string,
     *         created_time: string,
     *         last_edited_time: string,
     *         parent: array{type: string, database_id: string},
     *         properties: array<string, mixed>,
     *         url: string,
     *     }>,
     *     next_cursor: string|null,
     *     has_more: bool,
     * }|string
     */
    public function queryDatabase(
        string $databaseId,
        array $filter = [],
        array $sorts = [],
        int $pageSize = 100,
        string $startCursor = '',
    ): array|string {
        try {
            $payload = [
                'page_size' => min(max($pageSize, 1), 100),
            ];

            if (!empty($filter)) {
                $payload['filter'] = $filter;
            }

            if (!empty($sorts)) {
                $payload['sorts'] = $sorts;
            }

            if ($startCursor) {
                $payload['start_cursor'] = $startCursor;
            }

            $response = $this->httpClient->request('POST', "https://api.notion.com/v1/databases/{$databaseId}/query", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                    'Notion-Version' => $this->apiVersion,
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error querying database: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'results' => array_map(fn ($result) => [
                    'id' => $result['id'],
                    'object' => $result['object'],
                    'created_time' => $result['created_time'],
                    'last_edited_time' => $result['last_edited_time'],
                    'parent' => $result['parent'],
                    'properties' => $result['properties'],
                    'url' => $result['url'],
                ], $data['results']),
                'next_cursor' => $data['next_cursor'] ?? null,
                'has_more' => $data['has_more'],
            ];
        } catch (\Exception $e) {
            return 'Error querying database: '.$e->getMessage();
        }
    }

    /**
     * Create a Notion database entry.
     *
     * @param string               $databaseId Database ID
     * @param array<string, mixed> $properties Entry properties
     *
     * @return array{
     *     id: string,
     *     object: string,
     *     created_time: string,
     *     last_edited_time: string,
     *     parent: array{type: string, database_id: string},
     *     properties: array<string, mixed>,
     *     url: string,
     * }|string
     */
    public function createDatabaseEntry(
        string $databaseId,
        array $properties,
    ): array|string {
        try {
            $payload = [
                'parent' => [
                    'database_id' => $databaseId,
                ],
                'properties' => $properties,
            ];

            $response = $this->httpClient->request('POST', 'https://api.notion.com/v1/pages', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                    'Notion-Version' => $this->apiVersion,
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating database entry: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'object' => $data['object'],
                'created_time' => $data['created_time'],
                'last_edited_time' => $data['last_edited_time'],
                'parent' => $data['parent'],
                'properties' => $data['properties'],
                'url' => $data['url'],
            ];
        } catch (\Exception $e) {
            return 'Error creating database entry: '.$e->getMessage();
        }
    }

    /**
     * Search Notion content.
     *
     * @param string $query       Search query
     * @param string $filter      Filter by object type (page, database)
     * @param string $sort        Sort criteria
     * @param int    $pageSize    Number of results per page
     * @param string $startCursor Pagination cursor
     *
     * @return array{
     *     results: array<int, array{
     *         id: string,
     *         object: string,
     *         created_time: string,
     *         last_edited_time: string,
     *         parent: array<string, mixed>,
     *         properties: array<string, mixed>|null,
     *         url: string,
     *         title: string,
     *     }>,
     *     next_cursor: string|null,
     *     has_more: bool,
     * }|string
     */
    public function search(
        string $query,
        string $filter = '',
        string $sort = '',
        int $pageSize = 100,
        string $startCursor = '',
    ): array|string {
        try {
            $payload = [
                'query' => $query,
                'page_size' => min(max($pageSize, 1), 100),
            ];

            if ($filter) {
                $payload['filter'] = ['property' => 'object', 'value' => $filter];
            }

            if ($sort) {
                $payload['sort'] = ['direction' => $sort, 'timestamp' => 'last_edited_time'];
            }

            if ($startCursor) {
                $payload['start_cursor'] = $startCursor;
            }

            $response = $this->httpClient->request('POST', 'https://api.notion.com/v1/search', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                    'Notion-Version' => $this->apiVersion,
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error searching: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'results' => array_map(fn ($result) => [
                    'id' => $result['id'],
                    'object' => $result['object'],
                    'created_time' => $result['created_time'],
                    'last_edited_time' => $result['last_edited_time'],
                    'parent' => $result['parent'],
                    'properties' => $result['properties'] ?? null,
                    'url' => $result['url'],
                    'title' => $this->extractTitle($result),
                ], $data['results']),
                'next_cursor' => $data['next_cursor'] ?? null,
                'has_more' => $data['has_more'],
            ];
        } catch (\Exception $e) {
            return 'Error searching: '.$e->getMessage();
        }
    }

    /**
     * Convert markdown to Notion blocks.
     *
     * @param string $markdown Markdown content
     *
     * @return array<int, array{type: string, paragraph: array{rich_text: array<int, array{type: string, text: array{content: string}}>}}>
     */
    private function convertMarkdownToBlocks(string $markdown): array
    {
        $lines = explode("\n", $markdown);
        $blocks = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Simple markdown to Notion block conversion
            if (str_starts_with($line, '# ')) {
                $blocks[] = [
                    'type' => 'heading_1',
                    'heading_1' => [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => substr($line, 2)],
                            ],
                        ],
                    ],
                ];
            } elseif (str_starts_with($line, '## ')) {
                $blocks[] = [
                    'type' => 'heading_2',
                    'heading_2' => [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => substr($line, 3)],
                            ],
                        ],
                    ],
                ];
            } elseif (str_starts_with($line, '### ')) {
                $blocks[] = [
                    'type' => 'heading_3',
                    'heading_3' => [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => substr($line, 4)],
                            ],
                        ],
                    ],
                ];
            } elseif (str_starts_with($line, '- ') || str_starts_with($line, '* ')) {
                $blocks[] = [
                    'type' => 'bulleted_list_item',
                    'bulleted_list_item' => [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => substr($line, 2)],
                            ],
                        ],
                    ],
                ];
            } elseif (str_starts_with($line, '1. ')) {
                $blocks[] = [
                    'type' => 'numbered_list_item',
                    'numbered_list_item' => [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => substr($line, 3)],
                            ],
                        ],
                    ],
                ];
            } else {
                $blocks[] = [
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [
                            [
                                'type' => 'text',
                                'text' => ['content' => $line],
                            ],
                        ],
                    ],
                ];
            }
        }

        return $blocks;
    }

    /**
     * Extract title from Notion object.
     *
     * @param array<string, mixed> $object Notion object
     */
    private function extractTitle(array $object): string
    {
        if (isset($object['properties']['title']['title'][0]['text']['content'])) {
            return $object['properties']['title']['title'][0]['text']['content'];
        }

        if (isset($object['properties']['Name']['title'][0]['text']['content'])) {
            return $object['properties']['Name']['title'][0]['text']['content'];
        }

        // Try to find any title property
        foreach ($object['properties'] ?? [] as $property) {
            if (isset($property['title'][0]['text']['content'])) {
                return $property['title'][0]['text']['content'];
            }
        }

        return 'Untitled';
    }
}
