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
#[AsTool('wikidata_search', 'Tool that searches Wikidata entities')]
#[AsTool('wikidata_get_entity', 'Tool that gets Wikidata entity details', method: 'getEntity')]
#[AsTool('wikidata_get_claims', 'Tool that gets Wikidata entity claims', method: 'getClaims')]
#[AsTool('wikidata_sparql_query', 'Tool that executes SPARQL queries on Wikidata', method: 'sparqlQuery')]
#[AsTool('wikidata_get_labels', 'Tool that gets Wikidata entity labels', method: 'getLabels')]
#[AsTool('wikidata_get_descriptions', 'Tool that gets Wikidata entity descriptions', method: 'getDescriptions')]
final readonly class Wikidata
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'https://www.wikidata.org/w/api.php',
        private string $sparqlUrl = 'https://query.wikidata.org/sparql',
        private array $options = [],
    ) {
    }

    /**
     * Search Wikidata entities.
     *
     * @param string $search   Search query
     * @param string $language Language code
     * @param int    $limit    Number of results
     * @param string $continue Continue token for pagination
     *
     * @return array{
     *     searchinfo: array{
     *         search: string,
     *         totalhits: int,
     *     },
     *     search: array<int, array{
     *         id: string,
     *         title: string,
     *         pageid: int,
     *         size: int,
     *         wordcount: int,
     *         snippet: string,
     *         timestamp: string,
     *         description: string,
     *     }>,
     *     continue: array<string, string>|null,
     * }
     */
    public function __invoke(
        string $search,
        string $language = 'en',
        int $limit = 10,
        string $continue = '',
    ): array {
        try {
            $params = [
                'action' => 'wbsearchentities',
                'search' => $search,
                'language' => $language,
                'limit' => min(max($limit, 1), 50),
                'format' => 'json',
            ];

            if ($continue) {
                $params['continue'] = $continue;
            }

            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'searchinfo' => [
                    'search' => $data['searchinfo']['search'] ?? $search,
                    'totalhits' => $data['searchinfo']['totalhits'] ?? 0,
                ],
                'search' => array_map(fn ($result) => [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'pageid' => $result['pageid'],
                    'size' => $result['size'] ?? 0,
                    'wordcount' => $result['wordcount'] ?? 0,
                    'snippet' => $result['snippet'] ?? '',
                    'timestamp' => $result['timestamp'] ?? '',
                    'description' => $result['description'] ?? '',
                ], $data['search'] ?? []),
                'continue' => $data['continue'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'searchinfo' => [
                    'search' => $search,
                    'totalhits' => 0,
                ],
                'search' => [],
                'continue' => null,
            ];
        }
    }

    /**
     * Get Wikidata entity details.
     *
     * @param string $entityId Entity ID (e.g., Q42)
     * @param string $language Language code
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     labels: array<string, array{
     *         language: string,
     *         value: string,
     *     }>,
     *     descriptions: array<string, array{
     *         language: string,
     *         value: string,
     *     }>,
     *     aliases: array<string, array<int, array{
     *         language: string,
     *         value: string,
     *     }>>,
     *     claims: array<string, array<int, array{
     *         id: string,
     *         mainsnak: array<string, mixed>,
     *         type: string,
     *         rank: string,
     *         qualifiers: array<string, mixed>,
     *         references: array<int, array<string, mixed>>,
     *     }>>,
     *     sitelinks: array<string, array{
     *         site: string,
     *         title: string,
     *         badges: array<int, string>,
     *         url: string,
     *     }>,
     * }|string
     */
    public function getEntity(
        string $entityId,
        string $language = 'en',
    ): array|string {
        try {
            $params = [
                'action' => 'wbgetentities',
                'ids' => $entityId,
                'languages' => $language,
                'format' => 'json',
                'props' => 'labels|descriptions|aliases|claims|sitelinks',
            ];

            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting entity: '.($data['error']['info'] ?? 'Unknown error');
            }

            $entity = $data['entities'][$entityId] ?? null;
            if (!$entity) {
                return 'Entity not found';
            }

            return [
                'id' => $entity['id'],
                'type' => $entity['type'],
                'labels' => $entity['labels'] ?? [],
                'descriptions' => $entity['descriptions'] ?? [],
                'aliases' => $entity['aliases'] ?? [],
                'claims' => $entity['claims'] ?? [],
                'sitelinks' => array_map(fn ($sitelink) => [
                    'site' => $sitelink['site'],
                    'title' => $sitelink['title'],
                    'badges' => $sitelink['badges'] ?? [],
                    'url' => $sitelink['url'] ?? '',
                ], $entity['sitelinks'] ?? []),
            ];
        } catch (\Exception $e) {
            return 'Error getting entity: '.$e->getMessage();
        }
    }

    /**
     * Get Wikidata entity claims.
     *
     * @param string $entityId   Entity ID
     * @param string $propertyId Property ID to filter by
     * @param string $language   Language code
     *
     * @return array<int, array{
     *     id: string,
     *     mainsnak: array{
     *         snaktype: string,
     *         property: string,
     *         datavalue: array<string, mixed>|null,
     *         datatype: string,
     *     },
     *     type: string,
     *     rank: string,
     *     qualifiers: array<string, mixed>,
     *     references: array<int, array<string, mixed>>,
     * }>
     */
    public function getClaims(
        string $entityId,
        string $propertyId = '',
        string $language = 'en',
    ): array {
        try {
            $params = [
                'action' => 'wbgetclaims',
                'entity' => $entityId,
                'format' => 'json',
            ];

            if ($propertyId) {
                $params['property'] = $propertyId;
            }

            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            $claims = [];
            foreach ($data['claims'] ?? [] as $propClaims) {
                foreach ($propClaims as $claim) {
                    $claims[] = [
                        'id' => $claim['id'],
                        'mainsnak' => [
                            'snaktype' => $claim['mainsnak']['snaktype'],
                            'property' => $claim['mainsnak']['property'],
                            'datavalue' => $claim['mainsnak']['datavalue'] ?? null,
                            'datatype' => $claim['mainsnak']['datatype'],
                        ],
                        'type' => $claim['type'],
                        'rank' => $claim['rank'],
                        'qualifiers' => $claim['qualifiers'] ?? [],
                        'references' => $claim['references'] ?? [],
                    ];
                }
            }

            return $claims;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Execute SPARQL query on Wikidata.
     *
     * @param string $query  SPARQL query
     * @param string $format Output format (json, xml, csv, tsv)
     *
     * @return array{
     *     head: array{
     *         vars: array<int, string>,
     *     },
     *     results: array{
     *         bindings: array<int, array<string, array{
     *             type: string,
     *             value: string,
     *             datatype?: string,
     *             xml:lang?: string,
     *         }>>,
     *     },
     * }|string
     */
    public function sparqlQuery(
        string $query,
        string $format = 'json',
    ): array|string {
        try {
            $params = [
                'query' => $query,
                'format' => $format,
            ];

            $response = $this->httpClient->request('GET', $this->sparqlUrl, [
                'query' => array_merge($this->options, $params),
            ]);

            if ('json' !== $format) {
                return $response->getContent();
            }

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'SPARQL query error: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'head' => [
                    'vars' => $data['head']['vars'] ?? [],
                ],
                'results' => [
                    'bindings' => array_map(fn ($binding) => array_map(fn ($value) => [
                        'type' => $value['type'],
                        'value' => $value['value'],
                        'datatype' => $value['datatype'] ?? null,
                        'xml:lang' => $value['xml:lang'] ?? null,
                    ], $binding),
                        $data['results']['bindings'] ?? []
                    ),
                ],
            ];
        } catch (\Exception $e) {
            return 'SPARQL query error: '.$e->getMessage();
        }
    }

    /**
     * Get Wikidata entity labels.
     *
     * @param string $entityId Entity ID
     * @param string $language Language code
     *
     * @return array<string, array{
     *     language: string,
     *     value: string,
     * }>
     */
    public function getLabels(
        string $entityId,
        string $language = 'en',
    ): array {
        try {
            $params = [
                'action' => 'wbgetentities',
                'ids' => $entityId,
                'languages' => $language,
                'format' => 'json',
                'props' => 'labels',
            ];

            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error']) || !isset($data['entities'][$entityId])) {
                return [];
            }

            return $data['entities'][$entityId]['labels'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Wikidata entity descriptions.
     *
     * @param string $entityId Entity ID
     * @param string $language Language code
     *
     * @return array<string, array{
     *     language: string,
     *     value: string,
     * }>
     */
    public function getDescriptions(
        string $entityId,
        string $language = 'en',
    ): array {
        try {
            $params = [
                'action' => 'wbgetentities',
                'ids' => $entityId,
                'languages' => $language,
                'format' => 'json',
                'props' => 'descriptions',
            ];

            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error']) || !isset($data['entities'][$entityId])) {
                return [];
            }

            return $data['entities'][$entityId]['descriptions'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
