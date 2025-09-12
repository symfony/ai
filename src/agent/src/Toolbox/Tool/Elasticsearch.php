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
#[AsTool('elasticsearch_search', 'Tool that searches Elasticsearch documents')]
#[AsTool('elasticsearch_get_indices', 'Tool that gets Elasticsearch indices', method: 'getIndices')]
#[AsTool('elasticsearch_create_index', 'Tool that creates Elasticsearch indices', method: 'createIndex')]
#[AsTool('elasticsearch_delete_index', 'Tool that deletes Elasticsearch indices', method: 'deleteIndex')]
#[AsTool('elasticsearch_index_document', 'Tool that indexes documents in Elasticsearch', method: 'indexDocument')]
#[AsTool('elasticsearch_get_document', 'Tool that gets documents from Elasticsearch', method: 'getDocument')]
final readonly class Elasticsearch
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $username,
        #[\SensitiveParameter] private string $password,
        private string $baseUrl,
        private string $apiVersion = '7.17',
        private array $options = [],
    ) {
    }

    /**
     * Search Elasticsearch documents.
     *
     * @param string                $index        Index name (optional for multi-index search)
     * @param array<string, mixed>  $query        Search query DSL
     * @param int                   $from         Starting offset
     * @param int                   $size         Number of results to return
     * @param array<string, string> $sort         Sort specification
     * @param array<int, string>    $sourceFields Source fields to return
     *
     * @return array{
     *     took: int,
     *     timed_out: bool,
     *     _shards: array{
     *         total: int,
     *         successful: int,
     *         skipped: int,
     *         failed: int,
     *     },
     *     hits: array{
     *         total: array{
     *             value: int,
     *             relation: string,
     *         },
     *         max_score: float,
     *         hits: array<int, array{
     *             _index: string,
     *             _type: string,
     *             _id: string,
     *             _score: float,
     *             _source: array<string, mixed>,
     *             _version: int,
     *             _seq_no: int,
     *             _primary_term: int,
     *         }>,
     *     },
     *     aggregations: array<string, mixed>|null,
     * }|string
     */
    public function __invoke(
        string $index = '',
        array $query = [],
        int $from = 0,
        int $size = 10,
        array $sort = [],
        array $sourceFields = [],
    ): array|string {
        try {
            $searchBody = [
                'from' => $from,
                'size' => min(max($size, 1), 10000),
            ];

            if (!empty($query)) {
                $searchBody['query'] = $query;
            }
            if (!empty($sort)) {
                $searchBody['sort'] = $sort;
            }
            if (!empty($sourceFields)) {
                $searchBody['_source'] = $sourceFields;
            }

            $url = $index
                ? "{$this->baseUrl}/{$index}/_search"
                : "{$this->baseUrl}/_search";

            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->password) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->password);
            }

            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $searchBody,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error searching Elasticsearch: '.($data['error']['reason'] ?? 'Unknown error');
            }

            return [
                'took' => $data['took'],
                'timed_out' => $data['timed_out'],
                '_shards' => [
                    'total' => $data['_shards']['total'],
                    'successful' => $data['_shards']['successful'],
                    'skipped' => $data['_shards']['skipped'],
                    'failed' => $data['_shards']['failed'],
                ],
                'hits' => [
                    'total' => [
                        'value' => $data['hits']['total']['value'] ?? $data['hits']['total'],
                        'relation' => $data['hits']['total']['relation'] ?? 'eq',
                    ],
                    'max_score' => $data['hits']['max_score'],
                    'hits' => array_map(fn ($hit) => [
                        '_index' => $hit['_index'],
                        '_type' => $hit['_type'] ?? '_doc',
                        '_id' => $hit['_id'],
                        '_score' => $hit['_score'],
                        '_source' => $hit['_source'],
                        '_version' => $hit['_version'] ?? 1,
                        '_seq_no' => $hit['_seq_no'] ?? 0,
                        '_primary_term' => $hit['_primary_term'] ?? 1,
                    ], $data['hits']['hits']),
                ],
                'aggregations' => $data['aggregations'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error searching Elasticsearch: '.$e->getMessage();
        }
    }

    /**
     * Get Elasticsearch indices.
     *
     * @param string $index          Index name pattern (optional)
     * @param string $status         Index status filter (green, yellow, red)
     * @param bool   $includeAliases Include aliases in response
     *
     * @return array<string, array{
     *     aliases: array<string, array<string, mixed>>,
     *     mappings: array{
     *         properties: array<string, mixed>,
     *     },
     *     settings: array{
     *         index: array<string, mixed>,
     *     },
     *     health: string,
     *     status: string,
     *     uuid: string,
     *     pri: int,
     *     rep: int,
     *     docs: array{
     *         count: int,
     *         deleted: int,
     *     },
     *     store: array{
     *         size_in_bytes: int,
     *         reserved_in_bytes: int,
     *     },
     * }>
     */
    public function getIndices(
        string $index = '',
        string $status = '',
        bool $includeAliases = true,
    ): array {
        try {
            $params = [];

            if ($status) {
                $params['status'] = $status;
            }
            if ($includeAliases) {
                $params['include_aliases'] = 'true';
            }

            $url = $index
                ? "{$this->baseUrl}/{$index}"
                : "{$this->baseUrl}/_cat/indices?format=json";

            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->password) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->password);
            }

            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            if ($index) {
                // Single index response
                return [
                    $index => [
                        'aliases' => $data[$index]['aliases'] ?? [],
                        'mappings' => [
                            'properties' => $data[$index]['mappings']['properties'] ?? [],
                        ],
                        'settings' => [
                            'index' => $data[$index]['settings']['index'] ?? [],
                        ],
                        'health' => $data[$index]['health'] ?? 'unknown',
                        'status' => $data[$index]['status'] ?? 'unknown',
                        'uuid' => $data[$index]['uuid'] ?? '',
                        'pri' => $data[$index]['pri'] ?? 0,
                        'rep' => $data[$index]['rep'] ?? 0,
                        'docs' => [
                            'count' => $data[$index]['docs.count'] ?? 0,
                            'deleted' => $data[$index]['docs.deleted'] ?? 0,
                        ],
                        'store' => [
                            'size_in_bytes' => $data[$index]['store.size_in_bytes'] ?? 0,
                            'reserved_in_bytes' => $data[$index]['store.reserved_in_bytes'] ?? 0,
                        ],
                    ],
                ];
            }

            // Multiple indices response
            $result = [];
            foreach ($data as $indexData) {
                $indexName = $indexData['index'];
                $result[$indexName] = [
                    'aliases' => [],
                    'mappings' => [
                        'properties' => [],
                    ],
                    'settings' => [
                        'index' => [],
                    ],
                    'health' => $indexData['health'] ?? 'unknown',
                    'status' => $indexData['status'] ?? 'unknown',
                    'uuid' => $indexData['uuid'] ?? '',
                    'pri' => (int) ($indexData['pri'] ?? 0),
                    'rep' => (int) ($indexData['rep'] ?? 0),
                    'docs' => [
                        'count' => (int) ($indexData['docs.count'] ?? 0),
                        'deleted' => (int) ($indexData['docs.deleted'] ?? 0),
                    ],
                    'store' => [
                        'size_in_bytes' => (int) ($indexData['store.size_in_bytes'] ?? 0),
                        'reserved_in_bytes' => (int) ($indexData['store.reserved_in_bytes'] ?? 0),
                    ],
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create Elasticsearch index.
     *
     * @param string                              $index    Index name
     * @param array<string, mixed>                $mappings Index mappings
     * @param array<string, mixed>                $settings Index settings
     * @param array<string, array<string, mixed>> $aliases  Index aliases
     *
     * @return array{
     *     acknowledged: bool,
     *     shards_acknowledged: bool,
     *     index: string,
     * }|string
     */
    public function createIndex(
        string $index,
        array $mappings = [],
        array $settings = [],
        array $aliases = [],
    ): array|string {
        try {
            $body = [];

            if (!empty($mappings)) {
                $body['mappings'] = $mappings;
            }
            if (!empty($settings)) {
                $body['settings'] = $settings;
            }
            if (!empty($aliases)) {
                $body['aliases'] = $aliases;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->password) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->password);
            }

            $response = $this->httpClient->request('PUT', "{$this->baseUrl}/{$index}", [
                'headers' => $headers,
                'json' => $body,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating Elasticsearch index: '.($data['error']['reason'] ?? 'Unknown error');
            }

            return [
                'acknowledged' => $data['acknowledged'],
                'shards_acknowledged' => $data['shards_acknowledged'] ?? false,
                'index' => $data['index'],
            ];
        } catch (\Exception $e) {
            return 'Error creating Elasticsearch index: '.$e->getMessage();
        }
    }

    /**
     * Delete Elasticsearch index.
     *
     * @param string $index Index name
     *
     * @return array{
     *     acknowledged: bool,
     * }|string
     */
    public function deleteIndex(string $index): array|string
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->password) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->password);
            }

            $response = $this->httpClient->request('DELETE', "{$this->baseUrl}/{$index}", [
                'headers' => $headers,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error deleting Elasticsearch index: '.($data['error']['reason'] ?? 'Unknown error');
            }

            return [
                'acknowledged' => $data['acknowledged'],
            ];
        } catch (\Exception $e) {
            return 'Error deleting Elasticsearch index: '.$e->getMessage();
        }
    }

    /**
     * Index document in Elasticsearch.
     *
     * @param string               $index    Index name
     * @param string               $id       Document ID (optional)
     * @param array<string, mixed> $document Document body
     * @param string               $type     Document type (default: _doc)
     * @param string               $routing  Routing value (optional)
     * @param string               $pipeline Ingest pipeline (optional)
     *
     * @return array{
     *     _index: string,
     *     _type: string,
     *     _id: string,
     *     _version: int,
     *     result: string,
     *     _shards: array{
     *         total: int,
     *         successful: int,
     *         failed: int,
     *     },
     *     _seq_no: int,
     *     _primary_term: int,
     * }|string
     */
    public function indexDocument(
        string $index,
        string $id = '',
        array $document = [],
        string $type = '_doc',
        string $routing = '',
        string $pipeline = '',
    ): array|string {
        try {
            $params = [];

            if ($routing) {
                $params['routing'] = $routing;
            }
            if ($pipeline) {
                $params['pipeline'] = $pipeline;
            }

            $url = $id
                ? "{$this->baseUrl}/{$index}/{$type}/{$id}"
                : "{$this->baseUrl}/{$index}/{$type}";

            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->password) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->password);
            }

            $method = $id ? 'PUT' : 'POST';

            $response = $this->httpClient->request($method, $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
                'json' => $document,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error indexing document: '.($data['error']['reason'] ?? 'Unknown error');
            }

            return [
                '_index' => $data['_index'],
                '_type' => $data['_type'],
                '_id' => $data['_id'],
                '_version' => $data['_version'],
                'result' => $data['result'],
                '_shards' => [
                    'total' => $data['_shards']['total'],
                    'successful' => $data['_shards']['successful'],
                    'failed' => $data['_shards']['failed'],
                ],
                '_seq_no' => $data['_seq_no'],
                '_primary_term' => $data['_primary_term'],
            ];
        } catch (\Exception $e) {
            return 'Error indexing document: '.$e->getMessage();
        }
    }

    /**
     * Get document from Elasticsearch.
     *
     * @param string             $index        Index name
     * @param string             $id           Document ID
     * @param string             $type         Document type (default: _doc)
     * @param array<int, string> $sourceFields Source fields to return
     * @param string             $routing      Routing value (optional)
     *
     * @return array{
     *     _index: string,
     *     _type: string,
     *     _id: string,
     *     _version: int,
     *     _seq_no: int,
     *     _primary_term: int,
     *     found: bool,
     *     _source: array<string, mixed>,
     * }|string
     */
    public function getDocument(
        string $index,
        string $id,
        string $type = '_doc',
        array $sourceFields = [],
        string $routing = '',
    ): array|string {
        try {
            $params = [];

            if (!empty($sourceFields)) {
                $params['_source'] = implode(',', $sourceFields);
            }
            if ($routing) {
                $params['routing'] = $routing;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->password) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->password);
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/{$index}/{$type}/{$id}", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting document: '.($data['error']['reason'] ?? 'Unknown error');
            }

            return [
                '_index' => $data['_index'],
                '_type' => $data['_type'],
                '_id' => $data['_id'],
                '_version' => $data['_version'],
                '_seq_no' => $data['_seq_no'],
                '_primary_term' => $data['_primary_term'],
                'found' => $data['found'],
                '_source' => $data['_source'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error getting document: '.$e->getMessage();
        }
    }
}
