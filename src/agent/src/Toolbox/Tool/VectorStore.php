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
#[AsTool('vectorstore_create_collection', 'Tool that creates vector store collections')]
#[AsTool('vectorstore_add_documents', 'Tool that adds documents to vector store', method: 'addDocuments')]
#[AsTool('vectorstore_search_similar', 'Tool that searches for similar documents', method: 'searchSimilar')]
#[AsTool('vectorstore_delete_documents', 'Tool that deletes documents from vector store', method: 'deleteDocuments')]
#[AsTool('vectorstore_list_collections', 'Tool that lists vector store collections', method: 'listCollections')]
#[AsTool('vectorstore_get_collection_info', 'Tool that gets collection information', method: 'getCollectionInfo')]
#[AsTool('vectorstore_update_documents', 'Tool that updates documents in vector store', method: 'updateDocuments')]
#[AsTool('vectorstore_get_document', 'Tool that gets a specific document', method: 'getDocument')]
final readonly class VectorStore
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey = '',
        private string $baseUrl = 'https://api.pinecone.io',
        private array $options = [],
    ) {
    }

    /**
     * Create vector store collection.
     *
     * @param string               $name        Collection name
     * @param int                  $dimension   Vector dimension
     * @param string               $metric      Distance metric (cosine, euclidean, dotproduct)
     * @param array<string, mixed> $metadata    Collection metadata
     * @param string               $environment Pinecone environment
     *
     * @return array{
     *     success: bool,
     *     collection: array{
     *         name: string,
     *         dimension: int,
     *         metric: string,
     *         status: string,
     *         indexCount: int,
     *         vectorCount: int,
     *         recordCount: int,
     *         metadata: array<string, mixed>,
     *         createdAt: string,
     *         updatedAt: string,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(
        string $name,
        int $dimension,
        string $metric = 'cosine',
        array $metadata = [],
        string $environment = 'us-east-1',
    ): array {
        try {
            $requestData = [
                'name' => $name,
                'dimension' => $dimension,
                'metric' => $metric,
                'metadata' => $metadata,
            ];

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Api-Key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/collections", [
                'headers' => $headers,
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'collection' => [
                    'name' => $data['name'] ?? $name,
                    'dimension' => $data['dimension'] ?? $dimension,
                    'metric' => $data['metric'] ?? $metric,
                    'status' => $data['status'] ?? 'initializing',
                    'indexCount' => $data['index_count'] ?? 0,
                    'vectorCount' => $data['vector_count'] ?? 0,
                    'recordCount' => $data['record_count'] ?? 0,
                    'metadata' => $data['metadata'] ?? $metadata,
                    'createdAt' => $data['created_at'] ?? date('c'),
                    'updatedAt' => $data['updated_at'] ?? date('c'),
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'collection' => [
                    'name' => $name,
                    'dimension' => $dimension,
                    'metric' => $metric,
                    'status' => 'error',
                    'indexCount' => 0,
                    'vectorCount' => 0,
                    'recordCount' => 0,
                    'metadata' => $metadata,
                    'createdAt' => '',
                    'updatedAt' => '',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add documents to vector store.
     *
     * @param string $collectionName Collection name
     * @param array<int, array{
     *     id: string,
     *     values: array<int, float>,
     *     metadata: array<string, mixed>,
     * }> $vectors Document vectors
     * @param string $namespace Namespace (optional)
     *
     * @return array{
     *     success: bool,
     *     upsertedCount: int,
     *     message: string,
     *     error: string,
     * }
     */
    public function addDocuments(
        string $collectionName,
        array $vectors,
        string $namespace = '',
    ): array {
        try {
            $requestData = [
                'vectors' => $vectors,
            ];

            if ($namespace) {
                $requestData['namespace'] = $namespace;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Api-Key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/collections/{$collectionName}/vectors/upsert", [
                'headers' => $headers,
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'upsertedCount' => $data['upserted_count'] ?? \count($vectors),
                'message' => 'Documents added successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'upsertedCount' => 0,
                'message' => 'Failed to add documents',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search for similar documents.
     *
     * @param string               $collectionName  Collection name
     * @param array<int, float>    $queryVector     Query vector
     * @param int                  $topK            Number of results
     * @param string               $namespace       Namespace (optional)
     * @param array<string, mixed> $filter          Filter criteria
     * @param bool                 $includeMetadata Include metadata in results
     * @param bool                 $includeValues   Include values in results
     *
     * @return array{
     *     success: bool,
     *     matches: array<int, array{
     *         id: string,
     *         score: float,
     *         values: array<int, float>,
     *         metadata: array<string, mixed>,
     *     }>,
     *     namespace: string,
     *     usage: array{
     *         readUnits: int,
     *     },
     *     error: string,
     * }
     */
    public function searchSimilar(
        string $collectionName,
        array $queryVector,
        int $topK = 10,
        string $namespace = '',
        array $filter = [],
        bool $includeMetadata = true,
        bool $includeValues = false,
    ): array {
        try {
            $requestData = [
                'vector' => $queryVector,
                'topK' => max(1, min($topK, 10000)),
                'includeMetadata' => $includeMetadata,
                'includeValues' => $includeValues,
            ];

            if ($namespace) {
                $requestData['namespace'] = $namespace;
            }

            if (!empty($filter)) {
                $requestData['filter'] = $filter;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Api-Key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/collections/{$collectionName}/query", [
                'headers' => $headers,
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'matches' => array_map(fn ($match) => [
                    'id' => $match['id'] ?? '',
                    'score' => $match['score'] ?? 0.0,
                    'values' => $match['values'] ?? [],
                    'metadata' => $match['metadata'] ?? [],
                ], $data['matches'] ?? []),
                'namespace' => $data['namespace'] ?? $namespace,
                'usage' => [
                    'readUnits' => $data['usage']['readUnits'] ?? 0,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'matches' => [],
                'namespace' => $namespace,
                'usage' => ['readUnits' => 0],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete documents from vector store.
     *
     * @param string               $collectionName Collection name
     * @param array<int, string>   $ids            Document IDs to delete
     * @param string               $namespace      Namespace (optional)
     * @param array<string, mixed> $filter         Filter criteria
     * @param bool                 $deleteAll      Delete all vectors
     *
     * @return array{
     *     success: bool,
     *     deletedCount: int,
     *     message: string,
     *     error: string,
     * }
     */
    public function deleteDocuments(
        string $collectionName,
        array $ids = [],
        string $namespace = '',
        array $filter = [],
        bool $deleteAll = false,
    ): array {
        try {
            $requestData = [];

            if ($deleteAll) {
                $requestData['deleteAll'] = true;
            } elseif (!empty($ids)) {
                $requestData['ids'] = $ids;
            } elseif (!empty($filter)) {
                $requestData['filter'] = $filter;
            }

            if ($namespace) {
                $requestData['namespace'] = $namespace;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Api-Key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/collections/{$collectionName}/vectors/delete", [
                'headers' => $headers,
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'deletedCount' => $data['deleted_count'] ?? \count($ids),
                'message' => 'Documents deleted successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'deletedCount' => 0,
                'message' => 'Failed to delete documents',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List vector store collections.
     *
     * @return array{
     *     success: bool,
     *     collections: array<int, array{
     *         name: string,
     *         dimension: int,
     *         metric: string,
     *         status: string,
     *         indexCount: int,
     *         vectorCount: int,
     *         recordCount: int,
     *         metadata: array<string, mixed>,
     *         createdAt: string,
     *         updatedAt: string,
     *     }>,
     *     error: string,
     * }
     */
    public function listCollections(): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Api-Key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/collections", [
                'headers' => $headers,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'collections' => array_map(fn ($collection) => [
                    'name' => $collection['name'] ?? '',
                    'dimension' => $collection['dimension'] ?? 0,
                    'metric' => $collection['metric'] ?? '',
                    'status' => $collection['status'] ?? '',
                    'indexCount' => $collection['index_count'] ?? 0,
                    'vectorCount' => $collection['vector_count'] ?? 0,
                    'recordCount' => $collection['record_count'] ?? 0,
                    'metadata' => $collection['metadata'] ?? [],
                    'createdAt' => $collection['created_at'] ?? '',
                    'updatedAt' => $collection['updated_at'] ?? '',
                ], $data['collections'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'collections' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get collection information.
     *
     * @param string $collectionName Collection name
     *
     * @return array{
     *     success: bool,
     *     collection: array{
     *         name: string,
     *         dimension: int,
     *         metric: string,
     *         status: string,
     *         indexCount: int,
     *         vectorCount: int,
     *         recordCount: int,
     *         metadata: array<string, mixed>,
     *         createdAt: string,
     *         updatedAt: string,
     *     },
     *     error: string,
     * }
     */
    public function getCollectionInfo(string $collectionName): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Api-Key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/collections/{$collectionName}", [
                'headers' => $headers,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'collection' => [
                    'name' => $data['name'] ?? $collectionName,
                    'dimension' => $data['dimension'] ?? 0,
                    'metric' => $data['metric'] ?? '',
                    'status' => $data['status'] ?? '',
                    'indexCount' => $data['index_count'] ?? 0,
                    'vectorCount' => $data['vector_count'] ?? 0,
                    'recordCount' => $data['record_count'] ?? 0,
                    'metadata' => $data['metadata'] ?? [],
                    'createdAt' => $data['created_at'] ?? '',
                    'updatedAt' => $data['updated_at'] ?? '',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'collection' => [
                    'name' => $collectionName,
                    'dimension' => 0,
                    'metric' => '',
                    'status' => 'error',
                    'indexCount' => 0,
                    'vectorCount' => 0,
                    'recordCount' => 0,
                    'metadata' => [],
                    'createdAt' => '',
                    'updatedAt' => '',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update documents in vector store.
     *
     * @param string $collectionName Collection name
     * @param array<int, array{
     *     id: string,
     *     values: array<int, float>,
     *     metadata: array<string, mixed>,
     * }> $vectors Document vectors
     * @param string $namespace Namespace (optional)
     *
     * @return array{
     *     success: bool,
     *     upsertedCount: int,
     *     message: string,
     *     error: string,
     * }
     */
    public function updateDocuments(
        string $collectionName,
        array $vectors,
        string $namespace = '',
    ): array {
        try {
            $requestData = [
                'vectors' => $vectors,
            ];

            if ($namespace) {
                $requestData['namespace'] = $namespace;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Api-Key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/collections/{$collectionName}/vectors/upsert", [
                'headers' => $headers,
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'upsertedCount' => $data['upserted_count'] ?? \count($vectors),
                'message' => 'Documents updated successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'upsertedCount' => 0,
                'message' => 'Failed to update documents',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get a specific document.
     *
     * @param string $collectionName Collection name
     * @param string $id             Document ID
     * @param string $namespace      Namespace (optional)
     *
     * @return array{
     *     success: bool,
     *     document: array{
     *         id: string,
     *         values: array<int, float>,
     *         metadata: array<string, mixed>,
     *     },
     *     namespace: string,
     *     error: string,
     * }
     */
    public function getDocument(
        string $collectionName,
        string $id,
        string $namespace = '',
    ): array {
        try {
            $params = [];

            if ($namespace) {
                $params['namespace'] = $namespace;
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['Api-Key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/collections/{$collectionName}/vectors/{$id}", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'document' => [
                    'id' => $data['id'] ?? $id,
                    'values' => $data['values'] ?? [],
                    'metadata' => $data['metadata'] ?? [],
                ],
                'namespace' => $data['namespace'] ?? $namespace,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'document' => [
                    'id' => $id,
                    'values' => [],
                    'metadata' => [],
                ],
                'namespace' => $namespace,
                'error' => $e->getMessage(),
            ];
        }
    }
}
