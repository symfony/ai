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
 * Tool integration of tavily.com.
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('tavily_search', description: 'search for information on the internet', method: 'search')]
#[AsTool('tavily_extract', description: 'fetch content from websites', method: 'extract')]
#[AsTool('tavily_search_news', description: 'search for news using Tavily', method: 'searchNews')]
#[AsTool('tavily_search_documents', description: 'search documents using Tavily', method: 'searchDocuments')]
#[AsTool('tavily_get_answer', description: 'get direct answers using Tavily', method: 'getAnswer')]
final readonly class Tavily
{
    /**
     * @param array<string, string|string[]|int|bool> $options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private array $options = ['include_images' => false],
    ) {
    }

    /**
     * @param string $query The search query to use
     */
    public function search(string $query): string
    {
        $result = $this->httpClient->request('POST', 'https://api.tavily.com/search', [
            'json' => array_merge($this->options, [
                'query' => $query,
                'api_key' => $this->apiKey,
            ]),
        ]);

        return $result->getContent();
    }

    /**
     * @param string[] $urls URLs to fetch information from
     */
    public function extract(array $urls): string
    {
        $result = $this->httpClient->request('POST', 'https://api.tavily.com/extract', [
            'json' => [
                'urls' => $urls,
                'api_key' => $this->apiKey,
            ],
        ]);

        return $result->getContent();
    }

    /**
     * Search news using Tavily.
     *
     * @param string $query         Search query
     * @param int    $maxResults    Maximum number of results
     * @param string $timeframe     Timeframe filter (day, week, month, year)
     * @param string $includeAnswer Include direct answer
     *
     * @return array{
     *     query: string,
     *     answer: string|null,
     *     results: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         score: float,
     *         published_date: string,
     *         source: string,
     *     }>,
     * }
     */
    public function searchNews(
        string $query,
        int $maxResults = 5,
        string $timeframe = '',
        string $includeAnswer = 'false',
    ): array {
        try {
            $body = [
                'query' => $query,
                'max_results' => min(max($maxResults, 1), 20),
                'search_depth' => 'basic',
                'include_answer' => 'true' === $includeAnswer,
                'search_type' => 'news',
                'api_key' => $this->apiKey,
            ];

            if ($timeframe) {
                $body['timeframe'] = $timeframe;
            }

            $response = $this->httpClient->request('POST', 'https://api.tavily.com/search', [
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'query' => $data['query'] ?? $query,
                'answer' => $data['answer'] ?? null,
                'results' => array_map(fn ($result) => [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'content' => $result['content'],
                    'score' => $result['score'],
                    'published_date' => $result['published_date'],
                    'source' => $result['source'] ?? '',
                ], $data['results'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'query' => $query,
                'answer' => null,
                'results' => [],
            ];
        }
    }

    /**
     * Search documents using Tavily.
     *
     * @param string        $query         Search query
     * @param array<string> $urls          URLs to search within
     * @param int           $maxResults    Maximum number of results
     * @param string        $includeAnswer Include direct answer
     *
     * @return array{
     *     query: string,
     *     answer: string|null,
     *     results: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         score: float,
     *         published_date: string|null,
     *     }>,
     * }
     */
    public function searchDocuments(
        string $query,
        array $urls = [],
        int $maxResults = 5,
        string $includeAnswer = 'false',
    ): array {
        try {
            $body = [
                'query' => $query,
                'max_results' => min(max($maxResults, 1), 20),
                'search_depth' => 'advanced',
                'include_answer' => 'true' === $includeAnswer,
                'search_type' => 'document',
                'api_key' => $this->apiKey,
            ];

            if (!empty($urls)) {
                $body['include_domains'] = $urls;
            }

            $response = $this->httpClient->request('POST', 'https://api.tavily.com/search', [
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'query' => $data['query'] ?? $query,
                'answer' => $data['answer'] ?? null,
                'results' => array_map(fn ($result) => [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'content' => $result['content'],
                    'score' => $result['score'],
                    'published_date' => $result['published_date'] ?? null,
                ], $data['results'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'query' => $query,
                'answer' => null,
                'results' => [],
            ];
        }
    }

    /**
     * Get direct answer using Tavily.
     *
     * @param string $query       Search query
     * @param int    $maxResults  Maximum number of results to use for answer
     * @param string $searchDepth Search depth (basic, advanced)
     *
     * @return array{
     *     query: string,
     *     answer: string,
     *     sources: array<int, array{
     *         title: string,
     *         url: string,
     *         score: float,
     *     }>,
     * }
     */
    public function getAnswer(
        string $query,
        int $maxResults = 5,
        string $searchDepth = 'basic',
    ): array {
        try {
            $body = [
                'query' => $query,
                'max_results' => min(max($maxResults, 1), 20),
                'search_depth' => $searchDepth,
                'include_answer' => true,
                'include_raw_content' => false,
                'api_key' => $this->apiKey,
            ];

            $response = $this->httpClient->request('POST', 'https://api.tavily.com/search', [
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'query' => $data['query'] ?? $query,
                'answer' => $data['answer'] ?? 'No answer found',
                'sources' => array_map(fn ($result) => [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'score' => $result['score'],
                ], $data['results'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'query' => $query,
                'answer' => 'Error retrieving answer: '.$e->getMessage(),
                'sources' => [],
            ];
        }
    }
}
