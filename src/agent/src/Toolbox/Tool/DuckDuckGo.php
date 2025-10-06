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
#[AsTool('duckduckgo_search', 'Tool that searches the web using DuckDuckGo Search')]
#[AsTool('duckduckgo_results_json', 'Tool that searches DuckDuckGo and returns structured JSON results', method: 'searchJson')]
final readonly class DuckDuckGo
{
    /**
     * @param array<string, mixed> $options Additional search options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private array $options = [],
    ) {
    }

    /**
     * @param string $query      the search query term
     * @param int    $maxResults The maximum number of search results to return
     *
     * @return string Formatted search results
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 10,
    ): string {
        $results = $this->performSearch($query, $maxResults);

        if (empty($results)) {
            return 'No results found for the given query.';
        }

        $formattedResults = [];
        foreach ($results as $result) {
            $formattedResults[] = \sprintf(
                'title: %s, snippet: %s, link: %s, date: %s, source: %s',
                $result['title'] ?? 'N/A',
                $result['snippet'] ?? 'N/A',
                $result['link'] ?? 'N/A',
                $result['date'] ?? 'N/A',
                $result['source'] ?? 'N/A'
            );
        }

        return '['.implode('], [', $formattedResults).']';
    }

    /**
     * @param string $query      the search query term
     * @param int    $maxResults The maximum number of search results to return
     *
     * @return array<int, array{
     *     title: string,
     *     snippet: string,
     *     link: string,
     *     date: string,
     *     source: string,
     * }>
     */
    public function searchJson(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 4,
    ): array {
        return $this->performSearch($query, $maxResults);
    }

    /**
     * @param string $query      the search query term
     * @param int    $maxResults The maximum number of search results to return
     *
     * @return array<int, array{
     *     title: string,
     *     snippet: string,
     *     link: string,
     *     date: string,
     *     source: string,
     * }>
     */
    private function performSearch(string $query, int $maxResults): array
    {
        try {
            // DuckDuckGo Instant Answer API
            $response = $this->httpClient->request('GET', 'https://api.duckduckgo.com/', [
                'query' => array_merge($this->options, [
                    'q' => $query,
                    'format' => 'json',
                    'no_html' => '1',
                    'skip_disambig' => '1',
                ]),
            ]);

            $data = $response->toArray();
            $results = [];

            // Add abstract if available
            if (!empty($data['Abstract'])) {
                $results[] = [
                    'title' => $data['Heading'] ?? $query,
                    'snippet' => $data['Abstract'],
                    'link' => $data['AbstractURL'] ?? '',
                    'date' => '',
                    'source' => $data['AbstractSource'] ?? 'DuckDuckGo',
                ];
            }

            // Add related topics
            if (!empty($data['RelatedTopics'])) {
                foreach (\array_slice($data['RelatedTopics'], 0, $maxResults - \count($results)) as $topic) {
                    if (isset($topic['Text']) && isset($topic['FirstURL'])) {
                        $results[] = [
                            'title' => $topic['Text'],
                            'snippet' => $topic['Text'],
                            'link' => $topic['FirstURL'],
                            'date' => '',
                            'source' => 'DuckDuckGo Related',
                        ];
                    }
                }
            }

            // If no results from Instant Answer API, try web search simulation
            if (empty($results)) {
                $results[] = [
                    'title' => "Search results for: {$query}",
                    'snippet' => 'DuckDuckGo search results would be displayed here. Note: This is a simplified implementation.',
                    'link' => 'https://duckduckgo.com/?q='.urlencode($query),
                    'date' => date('Y-m-d'),
                    'source' => 'DuckDuckGo Web Search',
                ];
            }

            return \array_slice($results, 0, $maxResults);
        } catch (\Exception $e) {
            return [
                [
                    'title' => 'Search Error',
                    'snippet' => 'Unable to perform search: '.$e->getMessage(),
                    'link' => '',
                    'date' => '',
                    'source' => 'Error',
                ],
            ];
        }
    }
}
