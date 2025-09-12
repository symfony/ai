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
#[AsTool('jina_search', 'Tool that searches using Jina AI search engine')]
#[AsTool('jina_search_images', 'Tool that searches images using Jina', method: 'searchImages')]
#[AsTool('jina_search_videos', 'Tool that searches videos using Jina', method: 'searchVideos')]
#[AsTool('jina_search_news', 'Tool that searches news using Jina', method: 'searchNews')]
#[AsTool('jina_search_academic', 'Tool that searches academic papers using Jina', method: 'searchAcademic')]
final readonly class JinaSearch
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.jina.ai',
        private array $options = [],
    ) {
    }

    /**
     * Search using Jina AI search engine.
     *
     * @param string               $query    Search query
     * @param string               $category Search category (general, images, videos, news, academic)
     * @param int                  $limit    Number of results to return
     * @param string               $language Response language
     * @param string               $region   Search region
     * @param array<string, mixed> $filters  Additional filters
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array{
     *         title: string,
     *         url: string,
     *         snippet: string,
     *         imageUrl: string,
     *         publishedDate: string,
     *         domain: string,
     *         rank: int,
     *         score: float,
     *     }>,
     *     totalResults: int,
     *     searchTime: float,
     *     query: string,
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        string $category = 'general',
        int $limit = 10,
        string $language = 'en',
        string $region = 'us',
        array $filters = [],
    ): array {
        try {
            $startTime = microtime(true);

            $requestData = [
                'query' => $query,
                'category' => $category,
                'limit' => max(1, min($limit, 50)),
                'language' => $language,
                'region' => $region,
                'filters' => $filters,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $searchTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'results' => array_map(fn ($result, $index) => [
                    'title' => $result['title'] ?? '',
                    'url' => $result['url'] ?? '',
                    'snippet' => $result['snippet'] ?? '',
                    'imageUrl' => $result['image_url'] ?? '',
                    'publishedDate' => $result['published_date'] ?? '',
                    'domain' => $result['domain'] ?? '',
                    'rank' => $index + 1,
                    'score' => $result['score'] ?? 0.0,
                ], $data['results'] ?? [], array_keys($data['results'] ?? [])),
                'totalResults' => $data['total_results'] ?? \count($data['results'] ?? []),
                'searchTime' => $searchTime,
                'query' => $query,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'totalResults' => 0,
                'searchTime' => 0.0,
                'query' => $query,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search images using Jina.
     *
     * @param string $query   Image search query
     * @param string $size    Image size filter (small, medium, large, xlarge)
     * @param string $color   Color filter (color, grayscale, transparent)
     * @param string $type    Image type filter (photo, clipart, lineart, animated)
     * @param string $license License filter (public, share, modify, modify_commercially)
     * @param int    $limit   Number of results
     *
     * @return array{
     *     success: bool,
     *     images: array<int, array{
     *         title: string,
     *         url: string,
     *         thumbnailUrl: string,
     *         sourceUrl: string,
     *         width: int,
     *         height: int,
     *         size: string,
     *         format: string,
     *         license: string,
     *         context: string,
     *     }>,
     *     totalResults: int,
     *     searchTime: float,
     *     error: string,
     * }
     */
    public function searchImages(
        string $query,
        string $size = 'medium',
        string $color = 'color',
        string $type = 'photo',
        string $license = 'public',
        int $limit = 20,
    ): array {
        try {
            $startTime = microtime(true);

            $requestData = [
                'query' => $query,
                'category' => 'images',
                'filters' => [
                    'size' => $size,
                    'color' => $color,
                    'type' => $type,
                    'license' => $license,
                ],
                'limit' => max(1, min($limit, 100)),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $searchTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'images' => array_map(fn ($image) => [
                    'title' => $image['title'] ?? '',
                    'url' => $image['url'] ?? '',
                    'thumbnailUrl' => $image['thumbnail_url'] ?? '',
                    'sourceUrl' => $image['source_url'] ?? '',
                    'width' => $image['width'] ?? 0,
                    'height' => $image['height'] ?? 0,
                    'size' => $image['size'] ?? '',
                    'format' => $image['format'] ?? '',
                    'license' => $image['license'] ?? '',
                    'context' => $image['context'] ?? '',
                ], $data['results'] ?? []),
                'totalResults' => $data['total_results'] ?? \count($data['results'] ?? []),
                'searchTime' => $searchTime,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'images' => [],
                'totalResults' => 0,
                'searchTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search videos using Jina.
     *
     * @param string $query    Video search query
     * @param string $duration Duration filter (short, medium, long)
     * @param string $quality  Quality filter (hd, sd)
     * @param string $license  License filter (public, share, modify)
     * @param int    $limit    Number of results
     *
     * @return array{
     *     success: bool,
     *     videos: array<int, array{
     *         title: string,
     *         url: string,
     *         thumbnailUrl: string,
     *         duration: int,
     *         views: int,
     *         uploadDate: string,
     *         channel: string,
     *         description: string,
     *         quality: string,
     *         license: string,
     *     }>,
     *     totalResults: int,
     *     searchTime: float,
     *     error: string,
     * }
     */
    public function searchVideos(
        string $query,
        string $duration = 'medium',
        string $quality = 'hd',
        string $license = 'public',
        int $limit = 20,
    ): array {
        try {
            $startTime = microtime(true);

            $requestData = [
                'query' => $query,
                'category' => 'videos',
                'filters' => [
                    'duration' => $duration,
                    'quality' => $quality,
                    'license' => $license,
                ],
                'limit' => max(1, min($limit, 100)),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $searchTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'videos' => array_map(fn ($video) => [
                    'title' => $video['title'] ?? '',
                    'url' => $video['url'] ?? '',
                    'thumbnailUrl' => $video['thumbnail_url'] ?? '',
                    'duration' => $video['duration'] ?? 0,
                    'views' => $video['views'] ?? 0,
                    'uploadDate' => $video['upload_date'] ?? '',
                    'channel' => $video['channel'] ?? '',
                    'description' => $video['description'] ?? '',
                    'quality' => $video['quality'] ?? '',
                    'license' => $video['license'] ?? '',
                ], $data['results'] ?? []),
                'totalResults' => $data['total_results'] ?? \count($data['results'] ?? []),
                'searchTime' => $searchTime,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'videos' => [],
                'totalResults' => 0,
                'searchTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search news using Jina.
     *
     * @param string $query     News search query
     * @param string $language  News language
     * @param string $region    News region
     * @param string $timeframe Time frame (1d, 7d, 30d, 1y, all)
     * @param int    $limit     Number of results
     *
     * @return array{
     *     success: bool,
     *     news: array<int, array{
     *         title: string,
     *         url: string,
     *         snippet: string,
     *         publishedDate: string,
     *         source: string,
     *         author: string,
     *         category: string,
     *         sentiment: string,
     *         imageUrl: string,
     *     }>,
     *     totalResults: int,
     *     searchTime: float,
     *     error: string,
     * }
     */
    public function searchNews(
        string $query,
        string $language = 'en',
        string $region = 'us',
        string $timeframe = '7d',
        int $limit = 20,
    ): array {
        try {
            $startTime = microtime(true);

            $requestData = [
                'query' => $query,
                'category' => 'news',
                'filters' => [
                    'language' => $language,
                    'region' => $region,
                    'timeframe' => $timeframe,
                ],
                'limit' => max(1, min($limit, 100)),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $searchTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'news' => array_map(fn ($article) => [
                    'title' => $article['title'] ?? '',
                    'url' => $article['url'] ?? '',
                    'snippet' => $article['snippet'] ?? '',
                    'publishedDate' => $article['published_date'] ?? '',
                    'source' => $article['source'] ?? '',
                    'author' => $article['author'] ?? '',
                    'category' => $article['category'] ?? '',
                    'sentiment' => $article['sentiment'] ?? '',
                    'imageUrl' => $article['image_url'] ?? '',
                ], $data['results'] ?? []),
                'totalResults' => $data['total_results'] ?? \count($data['results'] ?? []),
                'searchTime' => $searchTime,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'news' => [],
                'totalResults' => 0,
                'searchTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search academic papers using Jina.
     *
     * @param string $query   Academic search query
     * @param string $field   Research field
     * @param string $year    Publication year
     * @param string $journal Journal name
     * @param string $author  Author name
     * @param int    $limit   Number of results
     *
     * @return array{
     *     success: bool,
     *     papers: array<int, array{
     *         title: string,
     *         url: string,
     *         abstract: string,
     *         authors: array<int, string>,
     *         journal: string,
     *         publicationDate: string,
     *         citations: int,
     *         doi: string,
     *         keywords: array<int, string>,
     *         pdfUrl: string,
     *     }>,
     *     totalResults: int,
     *     searchTime: float,
     *     error: string,
     * }
     */
    public function searchAcademic(
        string $query,
        string $field = '',
        string $year = '',
        string $journal = '',
        string $author = '',
        int $limit = 20,
    ): array {
        try {
            $startTime = microtime(true);

            $requestData = [
                'query' => $query,
                'category' => 'academic',
                'filters' => [
                    'field' => $field,
                    'year' => $year,
                    'journal' => $journal,
                    'author' => $author,
                ],
                'limit' => max(1, min($limit, 100)),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/v1/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $searchTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'papers' => array_map(fn ($paper) => [
                    'title' => $paper['title'] ?? '',
                    'url' => $paper['url'] ?? '',
                    'abstract' => $paper['abstract'] ?? '',
                    'authors' => $paper['authors'] ?? [],
                    'journal' => $paper['journal'] ?? '',
                    'publicationDate' => $paper['publication_date'] ?? '',
                    'citations' => $paper['citations'] ?? 0,
                    'doi' => $paper['doi'] ?? '',
                    'keywords' => $paper['keywords'] ?? [],
                    'pdfUrl' => $paper['pdf_url'] ?? '',
                ], $data['results'] ?? []),
                'totalResults' => $data['total_results'] ?? \count($data['results'] ?? []),
                'searchTime' => $searchTime,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'papers' => [],
                'totalResults' => 0,
                'searchTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
