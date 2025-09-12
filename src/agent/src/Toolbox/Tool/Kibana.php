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
#[AsTool('kibana_get_saved_objects', 'Tool that gets Kibana saved objects')]
#[AsTool('kibana_search', 'Tool that searches Kibana data', method: 'search')]
#[AsTool('kibana_get_dashboards', 'Tool that gets Kibana dashboards', method: 'getDashboards')]
#[AsTool('kibana_get_visualizations', 'Tool that gets Kibana visualizations', method: 'getVisualizations')]
#[AsTool('kibana_get_index_patterns', 'Tool that gets Kibana index patterns', method: 'getIndexPatterns')]
#[AsTool('kibana_get_spaces', 'Tool that gets Kibana spaces', method: 'getSpaces')]
final readonly class Kibana
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $baseUrl,
        private string $apiVersion = '7.17',
        private array $options = [],
    ) {
    }

    /**
     * Get Kibana saved objects.
     *
     * @param string             $type      Object type (dashboard, visualization, index-pattern, etc.)
     * @param string             $search    Search query
     * @param int                $perPage   Number of objects per page
     * @param int                $page      Page number
     * @param array<int, string> $fields    Fields to include in response
     * @param string             $sortField Field to sort by
     * @param string             $sortOrder Sort order (asc, desc)
     *
     * @return array<int, array{
     *     id: string,
     *     type: string,
     *     updated_at: string,
     *     version: string,
     *     attributes: array<string, mixed>,
     *     references: array<int, array{
     *         id: string,
     *         name: string,
     *         type: string,
     *     }>,
     *     migrationVersion: array<string, string>,
     *     coreMigrationVersion: string,
     *     namespaceType: string,
     *     managed: bool,
     *     namespaces: array<int, string>,
     *     originId: string,
     * }>
     */
    public function __invoke(
        string $type = '',
        string $search = '',
        int $perPage = 20,
        int $page = 1,
        array $fields = [],
        string $sortField = 'updated_at',
        string $sortOrder = 'desc',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 10000),
                'page' => max($page, 1),
                'sort_field' => $sortField,
                'sort_order' => $sortOrder,
            ];

            if ($type) {
                $params['type'] = $type;
            }
            if ($search) {
                $params['search'] = $search;
            }
            if (!empty($fields)) {
                $params['fields'] = implode(',', $fields);
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'ApiKey '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/saved_objects/_find", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($object) => [
                'id' => $object['id'],
                'type' => $object['type'],
                'updated_at' => $object['updated_at'],
                'version' => $object['version'],
                'attributes' => $object['attributes'],
                'references' => array_map(fn ($ref) => [
                    'id' => $ref['id'],
                    'name' => $ref['name'],
                    'type' => $ref['type'],
                ], $object['references'] ?? []),
                'migrationVersion' => $object['migrationVersion'] ?? [],
                'coreMigrationVersion' => $object['coreMigrationVersion'] ?? '',
                'namespaceType' => $object['namespaceType'] ?? 'single',
                'managed' => $object['managed'] ?? false,
                'namespaces' => $object['namespaces'] ?? [],
                'originId' => $object['originId'] ?? '',
            ], $data['saved_objects'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search Kibana data.
     *
     * @param string                $index        Index pattern or index name
     * @param array<string, mixed>  $query        Search query DSL
     * @param int                   $from         Starting offset
     * @param int                   $size         Number of results to return
     * @param array<string, string> $sort         Sort specification
     * @param array<int, string>    $sourceFields Source fields to return
     * @param array<string, mixed>  $aggregations Aggregations to perform
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
    public function search(
        string $index,
        array $query = [],
        int $from = 0,
        int $size = 10,
        array $sort = [],
        array $sourceFields = [],
        array $aggregations = [],
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
            if (!empty($aggregations)) {
                $searchBody['aggs'] = $aggregations;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'ApiKey '.$this->apiKey;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/api/console/proxy", [
                'headers' => $headers,
                'json' => [
                    'path' => "/{$index}/_search",
                    'method' => 'POST',
                    'body' => $searchBody,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error searching Kibana: '.($data['error']['reason'] ?? 'Unknown error');
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
            return 'Error searching Kibana: '.$e->getMessage();
        }
    }

    /**
     * Get Kibana dashboards.
     *
     * @param string $search  Search query
     * @param int    $perPage Number of dashboards per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     panelsJSON: string,
     *     optionsJSON: string,
     *     uiStateJSON: string,
     *     version: int,
     *     timeRestore: bool,
     *     kibanaSavedObjectMeta: array{
     *         searchSourceJSON: string,
     *     },
     *     updated_at: string,
     *     references: array<int, array{
     *         id: string,
     *         name: string,
     *         type: string,
     *     }>,
     * }>
     */
    public function getDashboards(
        string $search = '',
        int $perPage = 20,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 10000),
                'page' => max($page, 1),
            ];

            if ($search) {
                $params['search'] = $search;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'ApiKey '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/saved_objects/_find", [
                'headers' => $headers,
                'query' => array_merge($this->options, array_merge($params, ['type' => 'dashboard'])),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($dashboard) => [
                'id' => $dashboard['id'],
                'title' => $dashboard['attributes']['title'] ?? '',
                'description' => $dashboard['attributes']['description'] ?? '',
                'panelsJSON' => $dashboard['attributes']['panelsJSON'] ?? '',
                'optionsJSON' => $dashboard['attributes']['optionsJSON'] ?? '',
                'uiStateJSON' => $dashboard['attributes']['uiStateJSON'] ?? '',
                'version' => $dashboard['version'],
                'timeRestore' => $dashboard['attributes']['timeRestore'] ?? false,
                'kibanaSavedObjectMeta' => [
                    'searchSourceJSON' => $dashboard['attributes']['kibanaSavedObjectMeta']['searchSourceJSON'] ?? '',
                ],
                'updated_at' => $dashboard['updated_at'],
                'references' => array_map(fn ($ref) => [
                    'id' => $ref['id'],
                    'name' => $ref['name'],
                    'type' => $ref['type'],
                ], $dashboard['references'] ?? []),
            ], $data['saved_objects'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Kibana visualizations.
     *
     * @param string $search  Search query
     * @param int    $perPage Number of visualizations per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     visState: string,
     *     uiStateJSON: string,
     *     kibanaSavedObjectMeta: array{
     *         searchSourceJSON: string,
     *     },
     *     version: int,
     *     updated_at: string,
     *     references: array<int, array{
     *         id: string,
     *         name: string,
     *         type: string,
     *     }>,
     * }>
     */
    public function getVisualizations(
        string $search = '',
        int $perPage = 20,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 10000),
                'page' => max($page, 1),
            ];

            if ($search) {
                $params['search'] = $search;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'ApiKey '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/saved_objects/_find", [
                'headers' => $headers,
                'query' => array_merge($this->options, array_merge($params, ['type' => 'visualization'])),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($visualization) => [
                'id' => $visualization['id'],
                'title' => $visualization['attributes']['title'] ?? '',
                'description' => $visualization['attributes']['description'] ?? '',
                'visState' => $visualization['attributes']['visState'] ?? '',
                'uiStateJSON' => $visualization['attributes']['uiStateJSON'] ?? '',
                'kibanaSavedObjectMeta' => [
                    'searchSourceJSON' => $visualization['attributes']['kibanaSavedObjectMeta']['searchSourceJSON'] ?? '',
                ],
                'version' => $visualization['version'],
                'updated_at' => $visualization['updated_at'],
                'references' => array_map(fn ($ref) => [
                    'id' => $ref['id'],
                    'name' => $ref['name'],
                    'type' => $ref['type'],
                ], $visualization['references'] ?? []),
            ], $data['saved_objects'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Kibana index patterns.
     *
     * @param string $search  Search query
     * @param int    $perPage Number of index patterns per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     timeFieldName: string,
     *     fields: string,
     *     fieldFormatMap: string,
     *     typeMeta: string,
     *     version: int,
     *     updated_at: string,
     *     references: array<int, array{
     *         id: string,
     *         name: string,
     *         type: string,
     *     }>,
     * }>
     */
    public function getIndexPatterns(
        string $search = '',
        int $perPage = 20,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 10000),
                'page' => max($page, 1),
            ];

            if ($search) {
                $params['search'] = $search;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'ApiKey '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/saved_objects/_find", [
                'headers' => $headers,
                'query' => array_merge($this->options, array_merge($params, ['type' => 'index-pattern'])),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($indexPattern) => [
                'id' => $indexPattern['id'],
                'title' => $indexPattern['attributes']['title'] ?? '',
                'timeFieldName' => $indexPattern['attributes']['timeFieldName'] ?? '',
                'fields' => $indexPattern['attributes']['fields'] ?? '',
                'fieldFormatMap' => $indexPattern['attributes']['fieldFormatMap'] ?? '',
                'typeMeta' => $indexPattern['attributes']['typeMeta'] ?? '',
                'version' => $indexPattern['version'],
                'updated_at' => $indexPattern['updated_at'],
                'references' => array_map(fn ($ref) => [
                    'id' => $ref['id'],
                    'name' => $ref['name'],
                    'type' => $ref['type'],
                ], $indexPattern['references'] ?? []),
            ], $data['saved_objects'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Kibana spaces.
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     initials: string,
     *     color: string,
     *     disabledFeatures: array<int, string>,
     *     imageUrl: string,
     *     _reserved: bool,
     * }>
     */
    public function getSpaces(): array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'ApiKey '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/spaces/space", [
                'headers' => $headers,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($space) => [
                'id' => $space['id'],
                'name' => $space['name'],
                'description' => $space['description'] ?? '',
                'initials' => $space['initials'] ?? '',
                'color' => $space['color'] ?? '#000000',
                'disabledFeatures' => $space['disabledFeatures'] ?? [],
                'imageUrl' => $space['imageUrl'] ?? '',
                '_reserved' => $space['_reserved'] ?? false,
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
