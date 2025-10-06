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
#[AsTool('metaphor_search', 'Tool that searches using Metaphor neural search')]
#[AsTool('metaphor_find_similar', 'Tool that finds similar content using Metaphor', method: 'findSimilar')]
#[AsTool('metaphor_get_contents', 'Tool that gets content from URLs using Metaphor', method: 'getContents')]
#[AsTool('metaphor_summarize', 'Tool that summarizes content using Metaphor', method: 'summarize')]
final readonly class MetaphorSearch
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $baseUrl = 'https://api.metaphor.systems',
        private array $options = [],
    ) {
    }

    /**
     * Search using Metaphor neural search.
     *
     * @param string $query              Search query
     * @param int    $numResults         Number of results
     * @param string $useAutoprompt      Use autoprompt for better results
     * @param string $includeDomains     Domains to include (comma-separated)
     * @param string $excludeDomains     Domains to exclude (comma-separated)
     * @param string $startCrawlDate     Start crawl date (YYYY-MM-DD)
     * @param string $endCrawlDate       End crawl date (YYYY-MM-DD)
     * @param string $startPublishedDate Start published date (YYYY-MM-DD)
     * @param string $endPublishedDate   End published date (YYYY-MM-DD)
     *
     * @return array{
     *     results: array<int, array{
     *         id: string,
     *         title: string,
     *         url: string,
     *         publishedDate: string|null,
     *         author: string|null,
     *         score: float,
     *         extract: string,
     *     }>,
     *     autopromptString: string|null,
     * }
     */
    public function __invoke(
        string $query,
        int $numResults = 10,
        string $useAutoprompt = 'false',
        string $includeDomains = '',
        string $excludeDomains = '',
        string $startCrawlDate = '',
        string $endCrawlDate = '',
        string $startPublishedDate = '',
        string $endPublishedDate = '',
    ): array {
        try {
            $body = [
                'query' => $query,
                'numResults' => min(max($numResults, 1), 20),
                'useAutoprompt' => 'true' === $useAutoprompt,
            ];

            if ($includeDomains) {
                $body['includeDomains'] = explode(',', $includeDomains);
            }
            if ($excludeDomains) {
                $body['excludeDomains'] = explode(',', $excludeDomains);
            }
            if ($startCrawlDate) {
                $body['startCrawlDate'] = $startCrawlDate;
            }
            if ($endCrawlDate) {
                $body['endCrawlDate'] = $endCrawlDate;
            }
            if ($startPublishedDate) {
                $body['startPublishedDate'] = $startPublishedDate;
            }
            if ($endPublishedDate) {
                $body['endPublishedDate'] = $endPublishedDate;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/search", [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'results' => array_map(fn ($result) => [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'publishedDate' => $result['publishedDate'] ?? null,
                    'author' => $result['author'] ?? null,
                    'score' => $result['score'],
                    'extract' => $result['extract'] ?? '',
                ], $data['results'] ?? []),
                'autopromptString' => $data['autopromptString'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'results' => [],
                'autopromptString' => null,
            ];
        }
    }

    /**
     * Find similar content using Metaphor.
     *
     * @param string $url            URL to find similar content for
     * @param int    $numResults     Number of results
     * @param string $includeDomains Domains to include (comma-separated)
     * @param string $excludeDomains Domains to exclude (comma-separated)
     *
     * @return array{
     *     results: array<int, array{
     *         id: string,
     *         title: string,
     *         url: string,
     *         publishedDate: string|null,
     *         author: string|null,
     *         score: float,
     *         extract: string,
     *     }>,
     * }
     */
    public function findSimilar(
        string $url,
        int $numResults = 10,
        string $includeDomains = '',
        string $excludeDomains = '',
    ): array {
        try {
            $body = [
                'url' => $url,
                'numResults' => min(max($numResults, 1), 20),
            ];

            if ($includeDomains) {
                $body['includeDomains'] = explode(',', $includeDomains);
            }
            if ($excludeDomains) {
                $body['excludeDomains'] = explode(',', $excludeDomains);
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/findSimilar", [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'results' => array_map(fn ($result) => [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'publishedDate' => $result['publishedDate'] ?? null,
                    'author' => $result['author'] ?? null,
                    'score' => $result['score'],
                    'extract' => $result['extract'] ?? '',
                ], $data['results'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'results' => [],
            ];
        }
    }

    /**
     * Get content from URLs using Metaphor.
     *
     * @param array<string> $ids    Document IDs to get content for
     * @param string        $format Content format (extract, markdown, html)
     *
     * @return array{
     *     contents: array<int, array{
     *         id: string,
     *         url: string,
     *         title: string,
     *         extract: string,
     *         markdown: string|null,
     *         html: string|null,
     *         publishedDate: string|null,
     *         author: string|null,
     *     }>,
     * }
     */
    public function getContents(
        array $ids,
        string $format = 'extract',
    ): array {
        try {
            $body = [
                'ids' => $ids,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/getContents", [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'contents' => array_map(fn ($content) => [
                    'id' => $content['id'],
                    'url' => $content['url'],
                    'title' => $content['title'],
                    'extract' => $content['extract'] ?? '',
                    'markdown' => $content['markdown'] ?? null,
                    'html' => $content['html'] ?? null,
                    'publishedDate' => $content['publishedDate'] ?? null,
                    'author' => $content['author'] ?? null,
                ], $data['contents'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'contents' => [],
            ];
        }
    }

    /**
     * Summarize content using Metaphor.
     *
     * @param array<string> $ids           Document IDs to summarize
     * @param string        $summaryLength Summary length (short, medium, long)
     *
     * @return array{
     *     summary: string,
     *     sources: array<int, array{
     *         id: string,
     *         title: string,
     *         url: string,
     *         score: float,
     *     }>,
     * }
     */
    public function summarize(
        array $ids,
        string $summaryLength = 'medium',
    ): array {
        try {
            $body = [
                'ids' => $ids,
                'summaryLength' => $summaryLength,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/summarize", [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'summary' => $data['summary'] ?? '',
                'sources' => array_map(fn ($source) => [
                    'id' => $source['id'],
                    'title' => $source['title'],
                    'url' => $source['url'],
                    'score' => $source['score'] ?? 0.0,
                ], $data['sources'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'summary' => 'Error generating summary: '.$e->getMessage(),
                'sources' => [],
            ];
        }
    }
}
