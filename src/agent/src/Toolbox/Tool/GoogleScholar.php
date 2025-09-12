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
#[AsTool('google_scholar_search', 'Tool that searches Google Scholar for academic papers')]
#[AsTool('google_scholar_get_citations', 'Tool that gets citation metrics from Google Scholar', method: 'getCitations')]
#[AsTool('google_scholar_get_author_profile', 'Tool that gets author profile from Google Scholar', method: 'getAuthorProfile')]
#[AsTool('google_scholar_get_related_articles', 'Tool that gets related articles from Google Scholar', method: 'getRelatedArticles')]
final readonly class GoogleScholar
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'https://scholar.google.com',
        private array $options = [],
    ) {
    }

    /**
     * Search Google Scholar for academic papers.
     *
     * @param string $query    Search query
     * @param int    $num      Number of results
     * @param int    $start    Start index
     * @param string $sort     Sort order (relevance, date)
     * @param string $cluster  Cluster results
     * @param string $asSdt    article, thesis, etc
     * @param string $asVis    Include citations
     * @param string $hl       Language
     * @param string $asRights Access rights filter
     *
     * @return array{
     *     results: array<int, array{
     *         title: string,
     *         authors: string,
     *         publication: string,
     *         year: string,
     *         citedBy: int,
     *         pdfLink: string,
     *         url: string,
     *         snippet: string,
     *         related: array<int, string>,
     *         versions: array<int, array{
     *             title: string,
     *             url: string,
     *             source: string,
     *             year: string,
     *         }>,
     *     }>,
     *     totalResults: int,
     *     searchTime: float,
     * }
     */
    public function __invoke(
        string $query,
        int $num = 10,
        int $start = 0,
        string $sort = 'relevance',
        string $cluster = 'all',
        string $asSdt = '0,5',
        string $asVis = '1',
        string $hl = 'en',
        string $asRights = '',
    ): array {
        try {
            $params = [
                'q' => $query,
                'num' => min(max($num, 1), 20),
                'start' => max($start, 0),
                'hl' => $hl,
                'as_sdt' => $asSdt,
                'as_vis' => $asVis,
                'cluster' => $cluster,
            ];

            if ($sort) {
                $params['scisbd'] = 'date' === $sort ? '1' : '0';
            }

            if ($asRights) {
                $params['as_rights'] = $asRights;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/scholar", [
                'query' => array_merge($this->options, $params),
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ]);

            $content = $response->getContent();
            $results = $this->parseSearchResults($content);

            return [
                'results' => $results,
                'totalResults' => $this->extractTotalResults($content),
                'searchTime' => 0.0, // Google Scholar doesn't provide this
            ];
        } catch (\Exception $e) {
            return [
                'results' => [],
                'totalResults' => 0,
                'searchTime' => 0.0,
            ];
        }
    }

    /**
     * Get citation metrics from Google Scholar.
     *
     * @param string $paperTitle Paper title
     * @param string $authors    Authors
     * @param string $year       Publication year
     *
     * @return array{
     *     title: string,
     *     authors: string,
     *     year: string,
     *     citations: int,
     *     hIndex: int,
     *     i10Index: int,
     *     relatedPapers: array<int, array{
     *         title: string,
     *         authors: string,
     *         year: string,
     *         citations: int,
     *     }>,
     * }
     */
    public function getCitations(
        string $paperTitle,
        string $authors = '',
        string $year = '',
    ): array {
        try {
            $searchQuery = $paperTitle;
            if ($authors) {
                $searchQuery .= ' '.$authors;
            }
            if ($year) {
                $searchQuery .= ' '.$year;
            }

            $params = [
                'q' => $searchQuery,
                'num' => 1,
                'hl' => 'en',
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/scholar", [
                'query' => array_merge($this->options, $params),
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ]);

            $content = $response->getContent();
            $citations = $this->extractCitationMetrics($content);

            return [
                'title' => $paperTitle,
                'authors' => $authors,
                'year' => $year,
                'citations' => $citations['citations'] ?? 0,
                'hIndex' => $citations['hIndex'] ?? 0,
                'i10Index' => $citations['i10Index'] ?? 0,
                'relatedPapers' => $citations['relatedPapers'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'title' => $paperTitle,
                'authors' => $authors,
                'year' => $year,
                'citations' => 0,
                'hIndex' => 0,
                'i10Index' => 0,
                'relatedPapers' => [],
            ];
        }
    }

    /**
     * Get author profile from Google Scholar.
     *
     * @param string $authorName  Author name
     * @param string $affiliation Institution affiliation
     * @param string $email       Author email
     *
     * @return array{
     *     name: string,
     *     affiliation: string,
     *     email: string,
     *     homepage: string,
     *     interests: array<int, string>,
     *     citations: int,
     *     hIndex: int,
     *     i10Index: int,
     *     papers: array<int, array{
     *         title: string,
     *         year: string,
     *         citations: int,
     *         journal: string,
     *     }>,
     * }
     */
    public function getAuthorProfile(
        string $authorName,
        string $affiliation = '',
        string $email = '',
    ): array {
        try {
            $searchQuery = $authorName;
            if ($affiliation) {
                $searchQuery .= ' '.$affiliation;
            }

            $params = [
                'q' => $searchQuery,
                'num' => 1,
                'hl' => 'en',
                'view_op' => 'search_authors',
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/scholar", [
                'query' => array_merge($this->options, $params),
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ]);

            $content = $response->getContent();
            $profile = $this->extractAuthorProfile($content);

            return [
                'name' => $authorName,
                'affiliation' => $affiliation,
                'email' => $email,
                'homepage' => $profile['homepage'] ?? '',
                'interests' => $profile['interests'] ?? [],
                'citations' => $profile['citations'] ?? 0,
                'hIndex' => $profile['hIndex'] ?? 0,
                'i10Index' => $profile['i10Index'] ?? 0,
                'papers' => $profile['papers'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'name' => $authorName,
                'affiliation' => $affiliation,
                'email' => $email,
                'homepage' => '',
                'interests' => [],
                'citations' => 0,
                'hIndex' => 0,
                'i10Index' => 0,
                'papers' => [],
            ];
        }
    }

    /**
     * Get related articles from Google Scholar.
     *
     * @param string $paperTitle Paper title
     * @param string $authors    Authors
     * @param int    $num        Number of related articles
     *
     * @return array{
     *     originalPaper: array{
     *         title: string,
     *         authors: string,
     *         year: string,
     *     },
     *     relatedArticles: array<int, array{
     *         title: string,
     *         authors: string,
     *         year: string,
     *         citations: int,
     *         relevanceScore: float,
     *         url: string,
     *     }>,
     * }
     */
    public function getRelatedArticles(
        string $paperTitle,
        string $authors = '',
        int $num = 10,
    ): array {
        try {
            $searchQuery = $paperTitle;
            if ($authors) {
                $searchQuery .= ' '.$authors;
            }

            $params = [
                'q' => $searchQuery,
                'num' => min(max($num, 1), 20),
                'hl' => 'en',
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/scholar", [
                'query' => array_merge($this->options, $params),
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ]);

            $content = $response->getContent();
            $results = $this->parseSearchResults($content);

            return [
                'originalPaper' => [
                    'title' => $paperTitle,
                    'authors' => $authors,
                    'year' => '',
                ],
                'relatedArticles' => array_map(fn ($result) => [
                    'title' => $result['title'],
                    'authors' => $result['authors'],
                    'year' => $result['year'],
                    'citations' => $result['citedBy'],
                    'relevanceScore' => 0.8, // Simplified relevance score
                    'url' => $result['url'],
                ], \array_slice($results, 1, $num)), // Skip first result (original paper)
            ];
        } catch (\Exception $e) {
            return [
                'originalPaper' => [
                    'title' => $paperTitle,
                    'authors' => $authors,
                    'year' => '',
                ],
                'relatedArticles' => [],
            ];
        }
    }

    /**
     * Parse search results from HTML content.
     */
    private function parseSearchResults(string $content): array
    {
        $results = [];

        // This is a simplified parser - in reality, you'd need more sophisticated HTML parsing
        preg_match_all('/<h3[^>]*class="[^"]*gs_rt[^"]*"[^>]*>(.*?)<\/h3>/s', $content, $titleMatches);
        preg_match_all('/<div[^>]*class="[^"]*gs_a[^"]*"[^>]*>(.*?)<\/div>/s', $content, $authorMatches);
        preg_match_all('/<div[^>]*class="[^"]*gs_rs[^"]*"[^>]*>(.*?)<\/div>/s', $content, $snippetMatches);

        for ($i = 0; $i < min(\count($titleMatches[1]), 10); ++$i) {
            $title = strip_tags($titleMatches[1][$i]);
            $authors = isset($authorMatches[1][$i]) ? strip_tags($authorMatches[1][$i]) : '';
            $snippet = isset($snippetMatches[1][$i]) ? strip_tags($snippetMatches[1][$i]) : '';

            $results[] = [
                'title' => $title,
                'authors' => $authors,
                'publication' => '',
                'year' => '',
                'citedBy' => 0,
                'pdfLink' => '',
                'url' => '',
                'snippet' => $snippet,
                'related' => [],
                'versions' => [],
            ];
        }

        return $results;
    }

    /**
     * Extract total results count from HTML content.
     */
    private function extractTotalResults(string $content): int
    {
        preg_match('/About ([\d,]+) results/', $content, $matches);

        return isset($matches[1]) ? (int) str_replace(',', '', $matches[1]) : 0;
    }

    /**
     * Extract citation metrics from HTML content.
     */
    private function extractCitationMetrics(string $content): array
    {
        $citations = 0;
        $hIndex = 0;
        $i10Index = 0;
        $relatedPapers = [];

        preg_match('/Cited by ([\d,]+)/', $content, $citationMatches);
        if (isset($citationMatches[1])) {
            $citations = (int) str_replace(',', '', $citationMatches[1]);
        }

        return [
            'citations' => $citations,
            'hIndex' => $hIndex,
            'i10Index' => $i10Index,
            'relatedPapers' => $relatedPapers,
        ];
    }

    /**
     * Extract author profile from HTML content.
     */
    private function extractAuthorProfile(string $content): array
    {
        $homepage = '';
        $interests = [];
        $citations = 0;
        $hIndex = 0;
        $i10Index = 0;
        $papers = [];

        preg_match('/<a[^>]*href="([^"]*)"[^>]*>Homepage<\/a>/', $content, $homepageMatches);
        if (isset($homepageMatches[1])) {
            $homepage = $homepageMatches[1];
        }

        preg_match('/Citations: ([\d,]+)/', $content, $citationMatches);
        if (isset($citationMatches[1])) {
            $citations = (int) str_replace(',', '', $citationMatches[1]);
        }

        return [
            'homepage' => $homepage,
            'interests' => $interests,
            'citations' => $citations,
            'hIndex' => $hIndex,
            'i10Index' => $i10Index,
            'papers' => $papers,
        ];
    }
}
