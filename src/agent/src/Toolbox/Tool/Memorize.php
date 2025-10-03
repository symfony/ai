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
#[AsTool('memorize_store', 'Tool that stores information in memory')]
#[AsTool('memorize_retrieve', 'Tool that retrieves information from memory', method: 'retrieve')]
#[AsTool('memorize_search', 'Tool that searches memory for information', method: 'search')]
#[AsTool('memorize_delete', 'Tool that deletes information from memory', method: 'delete')]
#[AsTool('memorize_list', 'Tool that lists all memory entries', method: 'list')]
#[AsTool('memorize_clear', 'Tool that clears all memory', method: 'clear')]
#[AsTool('memorize_update', 'Tool that updates information in memory', method: 'update')]
#[AsTool('memorize_get_stats', 'Tool that gets memory statistics', method: 'getStats')]
final readonly class Memorize
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey = '',
        private string $baseUrl = 'https://api.memorize.ai',
        private array $options = [],
    ) {
    }

    /**
     * Store information in memory.
     *
     * @param string               $content   Content to store
     * @param array<string, mixed> $metadata  Metadata associated with the content
     * @param string               $category  Category for organization
     * @param array<string, mixed> $tags      Tags for easier retrieval
     * @param int                  $priority  Priority level (1-10)
     * @param string               $expiresAt Expiration date (ISO 8601 format)
     *
     * @return array{
     *     success: bool,
     *     memory: array{
     *         id: string,
     *         content: string,
     *         metadata: array<string, mixed>,
     *         category: string,
     *         tags: array<string, mixed>,
     *         priority: int,
     *         expiresAt: string,
     *         createdAt: string,
     *         updatedAt: string,
     *         accessCount: int,
     *         lastAccessedAt: string,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(
        string $content,
        array $metadata = [],
        string $category = 'general',
        array $tags = [],
        int $priority = 5,
        string $expiresAt = '',
    ): array {
        try {
            $requestData = [
                'content' => $content,
                'metadata' => $metadata,
                'category' => $category,
                'tags' => $tags,
                'priority' => max(1, min($priority, 10)),
            ];

            if ($expiresAt) {
                $requestData['expires_at'] = $expiresAt;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/memories", [
                'headers' => $headers,
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'memory' => [
                    'id' => $data['id'] ?? '',
                    'content' => $data['content'] ?? $content,
                    'metadata' => $data['metadata'] ?? $metadata,
                    'category' => $data['category'] ?? $category,
                    'tags' => $data['tags'] ?? $tags,
                    'priority' => $data['priority'] ?? $priority,
                    'expiresAt' => $data['expires_at'] ?? $expiresAt,
                    'createdAt' => $data['created_at'] ?? date('c'),
                    'updatedAt' => $data['updated_at'] ?? date('c'),
                    'accessCount' => $data['access_count'] ?? 0,
                    'lastAccessedAt' => $data['last_accessed_at'] ?? '',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'memory' => [
                    'id' => '',
                    'content' => $content,
                    'metadata' => $metadata,
                    'category' => $category,
                    'tags' => $tags,
                    'priority' => $priority,
                    'expiresAt' => $expiresAt,
                    'createdAt' => '',
                    'updatedAt' => '',
                    'accessCount' => 0,
                    'lastAccessedAt' => '',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve information from memory.
     *
     * @param string $id Memory ID
     *
     * @return array{
     *     success: bool,
     *     memory: array{
     *         id: string,
     *         content: string,
     *         metadata: array<string, mixed>,
     *         category: string,
     *         tags: array<string, mixed>,
     *         priority: int,
     *         expiresAt: string,
     *         createdAt: string,
     *         updatedAt: string,
     *         accessCount: int,
     *         lastAccessedAt: string,
     *     },
     *     error: string,
     * }
     */
    public function retrieve(string $id): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/memories/{$id}", [
                'headers' => $headers,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'memory' => [
                    'id' => $data['id'] ?? $id,
                    'content' => $data['content'] ?? '',
                    'metadata' => $data['metadata'] ?? [],
                    'category' => $data['category'] ?? '',
                    'tags' => $data['tags'] ?? [],
                    'priority' => $data['priority'] ?? 0,
                    'expiresAt' => $data['expires_at'] ?? '',
                    'createdAt' => $data['created_at'] ?? '',
                    'updatedAt' => $data['updated_at'] ?? '',
                    'accessCount' => $data['access_count'] ?? 0,
                    'lastAccessedAt' => $data['last_accessed_at'] ?? '',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'memory' => [
                    'id' => $id,
                    'content' => '',
                    'metadata' => [],
                    'category' => '',
                    'tags' => [],
                    'priority' => 0,
                    'expiresAt' => '',
                    'createdAt' => '',
                    'updatedAt' => '',
                    'accessCount' => 0,
                    'lastAccessedAt' => '',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search memory for information.
     *
     * @param string               $query    Search query
     * @param string               $category Category filter
     * @param array<string, mixed> $tags     Tags filter
     * @param int                  $limit    Number of results
     * @param int                  $offset   Offset for pagination
     * @param string               $sort     Sort field (created_at, updated_at, priority, access_count)
     * @param string               $order    Sort order (asc, desc)
     *
     * @return array{
     *     success: bool,
     *     memories: array<int, array{
     *         id: string,
     *         content: string,
     *         metadata: array<string, mixed>,
     *         category: string,
     *         tags: array<string, mixed>,
     *         priority: int,
     *         expiresAt: string,
     *         createdAt: string,
     *         updatedAt: string,
     *         accessCount: int,
     *         lastAccessedAt: string,
     *         relevanceScore: float,
     *     }>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     error: string,
     * }
     */
    public function search(
        string $query,
        string $category = '',
        array $tags = [],
        int $limit = 20,
        int $offset = 0,
        string $sort = 'created_at',
        string $order = 'desc',
    ): array {
        try {
            $params = [
                'q' => $query,
                'limit' => max(1, min($limit, 100)),
                'offset' => max(0, $offset),
                'sort' => $sort,
                'order' => $order,
            ];

            if ($category) {
                $params['category'] = $category;
            }

            if (!empty($tags)) {
                $params['tags'] = implode(',', $tags);
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/memories/search", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'memories' => array_map(fn ($memory) => [
                    'id' => $memory['id'] ?? '',
                    'content' => $memory['content'] ?? '',
                    'metadata' => $memory['metadata'] ?? [],
                    'category' => $memory['category'] ?? '',
                    'tags' => $memory['tags'] ?? [],
                    'priority' => $memory['priority'] ?? 0,
                    'expiresAt' => $memory['expires_at'] ?? '',
                    'createdAt' => $memory['created_at'] ?? '',
                    'updatedAt' => $memory['updated_at'] ?? '',
                    'accessCount' => $memory['access_count'] ?? 0,
                    'lastAccessedAt' => $memory['last_accessed_at'] ?? '',
                    'relevanceScore' => $memory['relevance_score'] ?? 0.0,
                ], $data['results'] ?? []),
                'total' => $data['total'] ?? 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'memories' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete information from memory.
     *
     * @param string $id Memory ID
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     error: string,
     * }
     */
    public function delete(string $id): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request('DELETE', "{$this->baseUrl}/memories/{$id}", [
                'headers' => $headers,
            ] + $this->options);

            return [
                'success' => true,
                'message' => 'Memory deleted successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete memory',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List all memory entries.
     *
     * @param string $category Category filter
     * @param int    $limit    Number of results
     * @param int    $offset   Offset for pagination
     * @param string $sort     Sort field
     * @param string $order    Sort order
     *
     * @return array{
     *     success: bool,
     *     memories: array<int, array{
     *         id: string,
     *         content: string,
     *         metadata: array<string, mixed>,
     *         category: string,
     *         tags: array<string, mixed>,
     *         priority: int,
     *         expiresAt: string,
     *         createdAt: string,
     *         updatedAt: string,
     *         accessCount: int,
     *         lastAccessedAt: string,
     *     }>,
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     error: string,
     * }
     */
    public function list(
        string $category = '',
        int $limit = 50,
        int $offset = 0,
        string $sort = 'created_at',
        string $order = 'desc',
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 100)),
                'offset' => max(0, $offset),
                'sort' => $sort,
                'order' => $order,
            ];

            if ($category) {
                $params['category'] = $category;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/memories", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'memories' => array_map(fn ($memory) => [
                    'id' => $memory['id'] ?? '',
                    'content' => $memory['content'] ?? '',
                    'metadata' => $memory['metadata'] ?? [],
                    'category' => $memory['category'] ?? '',
                    'tags' => $memory['tags'] ?? [],
                    'priority' => $memory['priority'] ?? 0,
                    'expiresAt' => $memory['expires_at'] ?? '',
                    'createdAt' => $memory['created_at'] ?? '',
                    'updatedAt' => $memory['updated_at'] ?? '',
                    'accessCount' => $memory['access_count'] ?? 0,
                    'lastAccessedAt' => $memory['last_accessed_at'] ?? '',
                ], $data['results'] ?? []),
                'total' => $data['total'] ?? 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'memories' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear all memory.
     *
     * @param string $category Category to clear (empty for all)
     *
     * @return array{
     *     success: bool,
     *     deletedCount: int,
     *     message: string,
     *     error: string,
     * }
     */
    public function clear(string $category = ''): array
    {
        try {
            $params = [];

            if ($category) {
                $params['category'] = $category;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request('DELETE', "{$this->baseUrl}/memories", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'deletedCount' => $data['deleted_count'] ?? 0,
                'message' => $category ? "Cleared {$category} category" : 'Cleared all memory',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'deletedCount' => 0,
                'message' => 'Failed to clear memory',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update information in memory.
     *
     * @param string               $id        Memory ID
     * @param string               $content   New content
     * @param array<string, mixed> $metadata  New metadata
     * @param string               $category  New category
     * @param array<string, mixed> $tags      New tags
     * @param int                  $priority  New priority
     * @param string               $expiresAt New expiration date
     *
     * @return array{
     *     success: bool,
     *     memory: array{
     *         id: string,
     *         content: string,
     *         metadata: array<string, mixed>,
     *         category: string,
     *         tags: array<string, mixed>,
     *         priority: int,
     *         expiresAt: string,
     *         createdAt: string,
     *         updatedAt: string,
     *         accessCount: int,
     *         lastAccessedAt: string,
     *     },
     *     error: string,
     * }
     */
    public function update(
        string $id,
        string $content = '',
        array $metadata = [],
        string $category = '',
        array $tags = [],
        int $priority = 0,
        string $expiresAt = '',
    ): array {
        try {
            $requestData = [];

            if ($content) {
                $requestData['content'] = $content;
            }

            if (!empty($metadata)) {
                $requestData['metadata'] = $metadata;
            }

            if ($category) {
                $requestData['category'] = $category;
            }

            if (!empty($tags)) {
                $requestData['tags'] = $tags;
            }

            if ($priority > 0) {
                $requestData['priority'] = max(1, min($priority, 10));
            }

            if ($expiresAt) {
                $requestData['expires_at'] = $expiresAt;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request('PUT', "{$this->baseUrl}/memories/{$id}", [
                'headers' => $headers,
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'memory' => [
                    'id' => $data['id'] ?? $id,
                    'content' => $data['content'] ?? $content,
                    'metadata' => $data['metadata'] ?? $metadata,
                    'category' => $data['category'] ?? $category,
                    'tags' => $data['tags'] ?? $tags,
                    'priority' => $data['priority'] ?? $priority,
                    'expiresAt' => $data['expires_at'] ?? $expiresAt,
                    'createdAt' => $data['created_at'] ?? '',
                    'updatedAt' => $data['updated_at'] ?? date('c'),
                    'accessCount' => $data['access_count'] ?? 0,
                    'lastAccessedAt' => $data['last_accessed_at'] ?? '',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'memory' => [
                    'id' => $id,
                    'content' => $content,
                    'metadata' => $metadata,
                    'category' => $category,
                    'tags' => $tags,
                    'priority' => $priority,
                    'expiresAt' => $expiresAt,
                    'createdAt' => '',
                    'updatedAt' => '',
                    'accessCount' => 0,
                    'lastAccessedAt' => '',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get memory statistics.
     *
     * @return array{
     *     success: bool,
     *     stats: array{
     *         totalMemories: int,
     *         categories: array<string, int>,
     *         totalAccessCount: int,
     *         averagePriority: float,
     *         oldestMemory: string,
     *         newestMemory: string,
     *         expiredMemories: int,
     *         memoryByMonth: array<string, int>,
     *     },
     *     error: string,
     * }
     */
    public function getStats(): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/memories/stats", [
                'headers' => $headers,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'stats' => [
                    'totalMemories' => $data['total_memories'] ?? 0,
                    'categories' => $data['categories'] ?? [],
                    'totalAccessCount' => $data['total_access_count'] ?? 0,
                    'averagePriority' => $data['average_priority'] ?? 0.0,
                    'oldestMemory' => $data['oldest_memory'] ?? '',
                    'newestMemory' => $data['newest_memory'] ?? '',
                    'expiredMemories' => $data['expired_memories'] ?? 0,
                    'memoryByMonth' => $data['memory_by_month'] ?? [],
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'stats' => [
                    'totalMemories' => 0,
                    'categories' => [],
                    'totalAccessCount' => 0,
                    'averagePriority' => 0.0,
                    'oldestMemory' => '',
                    'newestMemory' => '',
                    'expiredMemories' => 0,
                    'memoryByMonth' => [],
                ],
                'error' => $e->getMessage(),
            ];
        }
    }
}
