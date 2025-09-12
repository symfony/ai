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
#[AsTool('semantic_scholar_search', 'Tool that searches academic papers using Semantic Scholar')]
#[AsTool('semantic_scholar_get_paper', 'Tool that gets paper details from Semantic Scholar', method: 'getPaper')]
#[AsTool('semantic_scholar_get_author', 'Tool that gets author details from Semantic Scholar', method: 'getAuthor')]
#[AsTool('semantic_scholar_get_citations', 'Tool that gets paper citations from Semantic Scholar', method: 'getCitations')]
#[AsTool('semantic_scholar_get_references', 'Tool that gets paper references from Semantic Scholar', method: 'getReferences')]
#[AsTool('semantic_scholar_get_recommendations', 'Tool that gets paper recommendations from Semantic Scholar', method: 'getRecommendations')]
final readonly class SemanticScholar
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'https://api.semanticscholar.org/graph/v1',
        private string $apiKey = '',
        private array $options = [],
    ) {
    }

    /**
     * Search academic papers using Semantic Scholar.
     *
     * @param string        $query        Search query
     * @param int           $limit        Number of results
     * @param int           $offset       Offset for pagination
     * @param string        $fields       Fields to return
     * @param string        $sort         Sort order (relevance, year, citations)
     * @param string        $yearMin      Minimum publication year
     * @param string        $yearMax      Maximum publication year
     * @param array<string> $venueFilter  Venue filter
     * @param array<string> $authorFilter Author filter
     *
     * @return array{
     *     total: int,
     *     offset: int,
     *     next: int,
     *     data: array<int, array{
     *         paperId: string,
     *         externalIds: array<string, string>,
     *         url: string,
     *         title: string,
     *         abstract: string|null,
     *         venue: string,
     *         year: int,
     *         referenceCount: int,
     *         citationCount: int,
     *         influentialCitationCount: int,
     *         isOpenAccess: bool,
     *         openAccessPdf: array{
     *             url: string,
     *             status: string,
     *         }|null,
     *         fieldsOfStudy: array<int, string>,
     *         authors: array<int, array{
     *             authorId: string,
     *             name: string,
     *         }>,
     *         citations: array<int, array{
     *             paperId: string,
     *             title: string,
     *             year: int,
     *         }>,
     *         references: array<int, array{
     *             paperId: string,
     *             title: string,
     *             year: int,
     *         }>,
     *     }>,
     * }
     */
    public function __invoke(
        string $query,
        int $limit = 10,
        int $offset = 0,
        string $fields = 'paperId,title,abstract,venue,year,referenceCount,citationCount,authors',
        string $sort = 'relevance',
        string $yearMin = '',
        string $yearMax = '',
        array $venueFilter = [],
        array $authorFilter = [],
    ): array {
        try {
            $params = [
                'query' => $query,
                'limit' => min(max($limit, 1), 100),
                'offset' => max($offset, 0),
                'fields' => $fields,
                'sort' => $sort,
            ];

            if ($yearMin) {
                $params['year'] = $yearMin;
                if ($yearMax) {
                    $params['year'] = "{$yearMin}:{$yearMax}";
                }
            }

            if (!empty($venueFilter)) {
                $params['venue'] = implode(',', $venueFilter);
            }

            if (!empty($authorFilter)) {
                $params['authors'] = implode(',', $authorFilter);
            }

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['x-api-key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/paper/search", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'total' => $data['total'] ?? 0,
                'offset' => $data['offset'] ?? $offset,
                'next' => $data['next'] ?? 0,
                'data' => array_map(fn ($paper) => [
                    'paperId' => $paper['paperId'],
                    'externalIds' => $paper['externalIds'] ?? [],
                    'url' => $paper['url'] ?? '',
                    'title' => $paper['title'],
                    'abstract' => $paper['abstract'] ?? null,
                    'venue' => $paper['venue'] ?? '',
                    'year' => $paper['year'] ?? 0,
                    'referenceCount' => $paper['referenceCount'] ?? 0,
                    'citationCount' => $paper['citationCount'] ?? 0,
                    'influentialCitationCount' => $paper['influentialCitationCount'] ?? 0,
                    'isOpenAccess' => $paper['isOpenAccess'] ?? false,
                    'openAccessPdf' => $paper['openAccessPdf'] ? [
                        'url' => $paper['openAccessPdf']['url'],
                        'status' => $paper['openAccessPdf']['status'],
                    ] : null,
                    'fieldsOfStudy' => $paper['fieldsOfStudy'] ?? [],
                    'authors' => array_map(fn ($author) => [
                        'authorId' => $author['authorId'],
                        'name' => $author['name'],
                    ], $paper['authors'] ?? []),
                    'citations' => array_map(fn ($citation) => [
                        'paperId' => $citation['paperId'],
                        'title' => $citation['title'],
                        'year' => $citation['year'] ?? 0,
                    ], $paper['citations'] ?? []),
                    'references' => array_map(fn ($reference) => [
                        'paperId' => $reference['paperId'],
                        'title' => $reference['title'],
                        'year' => $reference['year'] ?? 0,
                    ], $paper['references'] ?? []),
                ], $data['data'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'offset' => $offset,
                'next' => 0,
                'data' => [],
            ];
        }
    }

    /**
     * Get paper details from Semantic Scholar.
     *
     * @param string $paperId Paper ID
     * @param string $fields  Fields to return
     *
     * @return array{
     *     paperId: string,
     *     externalIds: array<string, string>,
     *     url: string,
     *     title: string,
     *     abstract: string|null,
     *     venue: string,
     *     year: int,
     *     referenceCount: int,
     *     citationCount: int,
     *     influentialCitationCount: int,
     *     isOpenAccess: bool,
     *     openAccessPdf: array{
     *         url: string,
     *         status: string,
     *     }|null,
     *     fieldsOfStudy: array<int, string>,
     *     authors: array<int, array{
     *         authorId: string,
     *         name: string,
     *     }>,
     *     tldr: array{
     *         model: string,
     *         text: string,
     *     }|null,
     * }|string
     */
    public function getPaper(
        string $paperId,
        string $fields = 'paperId,title,abstract,venue,year,referenceCount,citationCount,authors,tldr',
    ): array|string {
        try {
            $params = [
                'fields' => $fields,
            ];

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['x-api-key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/paper/{$paperId}", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting paper: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'paperId' => $data['paperId'],
                'externalIds' => $data['externalIds'] ?? [],
                'url' => $data['url'] ?? '',
                'title' => $data['title'],
                'abstract' => $data['abstract'] ?? null,
                'venue' => $data['venue'] ?? '',
                'year' => $data['year'] ?? 0,
                'referenceCount' => $data['referenceCount'] ?? 0,
                'citationCount' => $data['citationCount'] ?? 0,
                'influentialCitationCount' => $data['influentialCitationCount'] ?? 0,
                'isOpenAccess' => $data['isOpenAccess'] ?? false,
                'openAccessPdf' => $data['openAccessPdf'] ? [
                    'url' => $data['openAccessPdf']['url'],
                    'status' => $data['openAccessPdf']['status'],
                ] : null,
                'fieldsOfStudy' => $data['fieldsOfStudy'] ?? [],
                'authors' => array_map(fn ($author) => [
                    'authorId' => $author['authorId'],
                    'name' => $author['name'],
                ], $data['authors'] ?? []),
                'tldr' => $data['tldr'] ? [
                    'model' => $data['tldr']['model'],
                    'text' => $data['tldr']['text'],
                ] : null,
            ];
        } catch (\Exception $e) {
            return 'Error getting paper: '.$e->getMessage();
        }
    }

    /**
     * Get author details from Semantic Scholar.
     *
     * @param string $authorId Author ID
     * @param string $fields   Fields to return
     *
     * @return array{
     *     authorId: string,
     *     name: string,
     *     aliases: array<int, string>,
     *     url: string,
     *     papers: array<int, array{
     *         paperId: string,
     *         title: string,
     *         year: int,
     *     }>,
     * }|string
     */
    public function getAuthor(
        string $authorId,
        string $fields = 'authorId,name,aliases,url,papers',
    ): array|string {
        try {
            $params = [
                'fields' => $fields,
            ];

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['x-api-key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/author/{$authorId}", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting author: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'authorId' => $data['authorId'],
                'name' => $data['name'],
                'aliases' => $data['aliases'] ?? [],
                'url' => $data['url'] ?? '',
                'papers' => array_map(fn ($paper) => [
                    'paperId' => $paper['paperId'],
                    'title' => $paper['title'],
                    'year' => $paper['year'] ?? 0,
                ], $data['papers'] ?? []),
            ];
        } catch (\Exception $e) {
            return 'Error getting author: '.$e->getMessage();
        }
    }

    /**
     * Get paper citations from Semantic Scholar.
     *
     * @param string $paperId Paper ID
     * @param int    $limit   Number of citations
     * @param int    $offset  Offset for pagination
     *
     * @return array{
     *     paperId: string,
     *     citations: array<int, array{
     *         paperId: string,
     *         title: string,
     *         year: int,
     *         venue: string,
     *         citationCount: int,
     *         influentialCitationCount: int,
     *     }>,
     *     next: int,
     * }
     */
    public function getCitations(
        string $paperId,
        int $limit = 10,
        int $offset = 0,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
                'offset' => max($offset, 0),
                'fields' => 'paperId,title,year,venue,citationCount,influentialCitationCount',
            ];

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['x-api-key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/paper/{$paperId}/citations", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'paperId' => $paperId,
                'citations' => array_map(fn ($citation) => [
                    'paperId' => $citation['paperId'],
                    'title' => $citation['title'],
                    'year' => $citation['year'] ?? 0,
                    'venue' => $citation['venue'] ?? '',
                    'citationCount' => $citation['citationCount'] ?? 0,
                    'influentialCitationCount' => $citation['influentialCitationCount'] ?? 0,
                ], $data['data'] ?? []),
                'next' => $data['next'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'paperId' => $paperId,
                'citations' => [],
                'next' => 0,
            ];
        }
    }

    /**
     * Get paper references from Semantic Scholar.
     *
     * @param string $paperId Paper ID
     * @param int    $limit   Number of references
     * @param int    $offset  Offset for pagination
     *
     * @return array{
     *     paperId: string,
     *     references: array<int, array{
     *         paperId: string,
     *         title: string,
     *         year: int,
     *         venue: string,
     *         citationCount: int,
     *         influentialCitationCount: int,
     *     }>,
     *     next: int,
     * }
     */
    public function getReferences(
        string $paperId,
        int $limit = 10,
        int $offset = 0,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
                'offset' => max($offset, 0),
                'fields' => 'paperId,title,year,venue,citationCount,influentialCitationCount',
            ];

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['x-api-key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/paper/{$paperId}/references", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'paperId' => $paperId,
                'references' => array_map(fn ($reference) => [
                    'paperId' => $reference['paperId'],
                    'title' => $reference['title'],
                    'year' => $reference['year'] ?? 0,
                    'venue' => $reference['venue'] ?? '',
                    'citationCount' => $reference['citationCount'] ?? 0,
                    'influentialCitationCount' => $reference['influentialCitationCount'] ?? 0,
                ], $data['data'] ?? []),
                'next' => $data['next'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'paperId' => $paperId,
                'references' => [],
                'next' => 0,
            ];
        }
    }

    /**
     * Get paper recommendations from Semantic Scholar.
     *
     * @param string $paperId Paper ID
     * @param int    $limit   Number of recommendations
     *
     * @return array{
     *     paperId: string,
     *     recommendations: array<int, array{
     *         paperId: string,
     *         title: string,
     *         year: int,
     *         venue: string,
     *         citationCount: int,
     *         score: float,
     *     }>,
     * }
     */
    public function getRecommendations(
        string $paperId,
        int $limit = 10,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
                'fields' => 'paperId,title,year,venue,citationCount',
            ];

            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($this->apiKey) {
                $headers['x-api-key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/recommendations/paper/{$paperId}", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'paperId' => $paperId,
                'recommendations' => array_map(fn ($recommendation) => [
                    'paperId' => $recommendation['paperId'],
                    'title' => $recommendation['title'],
                    'year' => $recommendation['year'] ?? 0,
                    'venue' => $recommendation['venue'] ?? '',
                    'citationCount' => $recommendation['citationCount'] ?? 0,
                    'score' => $recommendation['score'] ?? 0.0,
                ], $data['data'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'paperId' => $paperId,
                'recommendations' => [],
            ];
        }
    }
}
