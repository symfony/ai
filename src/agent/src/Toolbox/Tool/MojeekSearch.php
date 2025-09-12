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
#[AsTool('mojeek_search', 'Tool that searches using Mojeek search engine')]
#[AsTool('mojeek_search_news', 'Tool that searches news using Mojeek', method: 'searchNews')]
#[AsTool('mojeek_search_images', 'Tool that searches images using Mojeek', method: 'searchImages')]
#[AsTool('mojeek_autocomplete', 'Tool that provides autocomplete suggestions using Mojeek', method: 'autocomplete')]
final readonly class MojeekSearch
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey = '',
        private string $baseUrl = 'https://api.mojeek.com',
        private array $options = [],
    ) {
    }

    /**
     * Search using Mojeek search engine.
     *
     * @param string $query      Search query
     * @param int    $offset     Result offset
     * @param int    $count      Number of results
     * @param string $format     Response format (json, xml)
     * @param string $lang       Language code
     * @param string $country    Country code
     * @param string $safesearch Safe search level (off, moderate, strict)
     * @param string $type       Search type (web, news, images)
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array{
     *         title: string,
     *         desc: string,
     *         url: string,
     *         date: string,
     *         engine: string,
     *         rank: int,
     *         score: float,
     *     }>,
     *     query: array{
     *         text: string,
     *         offset: int,
     *         count: int,
     *         total: int,
     *     },
     *     response: array{
     *         time: float,
     *         status: string,
     *         version: string,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        int $offset = 0,
        int $count = 10,
        string $format = 'json',
        string $lang = 'en',
        string $country = 'us',
        string $safesearch = 'moderate',
        string $type = 'web',
    ): array {
        try {
            $params = [
                'q' => $query,
                'offset' => max(0, $offset),
                'count' => max(1, min($count, 100)),
                'format' => $format,
                'lang' => $lang,
                'country' => $country,
                'safesearch' => $safesearch,
                'type' => $type,
            ];

            if ($this->apiKey) {
                $params['api_key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'results' => array_map(fn ($result) => [
                    'title' => $result['title'] ?? '',
                    'desc' => $result['desc'] ?? '',
                    'url' => $result['url'] ?? '',
                    'date' => $result['date'] ?? '',
                    'engine' => $result['engine'] ?? 'mojeek',
                    'rank' => $result['rank'] ?? 0,
                    'score' => $result['score'] ?? 0.0,
                ], $data['results'] ?? []),
                'query' => [
                    'text' => $data['query']['text'] ?? $query,
                    'offset' => $data['query']['offset'] ?? $offset,
                    'count' => $data['query']['count'] ?? $count,
                    'total' => $data['query']['total'] ?? 0,
                ],
                'response' => [
                    'time' => $data['response']['time'] ?? 0.0,
                    'status' => $data['response']['status'] ?? 'ok',
                    'version' => $data['response']['version'] ?? '1.0',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'query' => ['text' => $query, 'offset' => $offset, 'count' => $count, 'total' => 0],
                'response' => ['time' => 0.0, 'status' => 'error', 'version' => '1.0'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search news using Mojeek.
     *
     * @param string $query     News search query
     * @param int    $offset    Result offset
     * @param int    $count     Number of results
     * @param string $lang      Language code
     * @param string $country   Country code
     * @param string $timeframe Time frame (day, week, month, year)
     *
     * @return array{
     *     success: bool,
     *     news: array<int, array{
     *         title: string,
     *         desc: string,
     *         url: string,
     *         date: string,
     *         source: string,
     *         author: string,
     *         image: string,
     *         rank: int,
     *     }>,
     *     query: array{
     *         text: string,
     *         offset: int,
     *         count: int,
     *         total: int,
     *     },
     *     response: array{
     *         time: float,
     *         status: string,
     *         version: string,
     *     },
     *     error: string,
     * }
     */
    public function searchNews(
        string $query,
        int $offset = 0,
        int $count = 10,
        string $lang = 'en',
        string $country = 'us',
        string $timeframe = '',
    ): array {
        try {
            $params = [
                'q' => $query,
                'offset' => max(0, $offset),
                'count' => max(1, min($count, 100)),
                'format' => 'json',
                'lang' => $lang,
                'country' => $country,
                'type' => 'news',
            ];

            if ($timeframe) {
                $params['time'] = $timeframe;
            }

            if ($this->apiKey) {
                $params['api_key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'news' => array_map(fn ($article) => [
                    'title' => $article['title'] ?? '',
                    'desc' => $article['desc'] ?? '',
                    'url' => $article['url'] ?? '',
                    'date' => $article['date'] ?? '',
                    'source' => $article['source'] ?? '',
                    'author' => $article['author'] ?? '',
                    'image' => $article['image'] ?? '',
                    'rank' => $article['rank'] ?? 0,
                ], $data['results'] ?? []),
                'query' => [
                    'text' => $data['query']['text'] ?? $query,
                    'offset' => $data['query']['offset'] ?? $offset,
                    'count' => $data['query']['count'] ?? $count,
                    'total' => $data['query']['total'] ?? 0,
                ],
                'response' => [
                    'time' => $data['response']['time'] ?? 0.0,
                    'status' => $data['response']['status'] ?? 'ok',
                    'version' => $data['response']['version'] ?? '1.0',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'news' => [],
                'query' => ['text' => $query, 'offset' => $offset, 'count' => $count, 'total' => 0],
                'response' => ['time' => 0.0, 'status' => 'error', 'version' => '1.0'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search images using Mojeek.
     *
     * @param string $query   Image search query
     * @param int    $offset  Result offset
     * @param int    $count   Number of results
     * @param string $lang    Language code
     * @param string $country Country code
     * @param string $size    Image size filter (small, medium, large, xlarge)
     * @param string $color   Color filter (color, grayscale, transparent)
     * @param string $type    Image type filter (photo, clipart, lineart, animated)
     *
     * @return array{
     *     success: bool,
     *     images: array<int, array{
     *         title: string,
     *         desc: string,
     *         url: string,
     *         imgUrl: string,
     *         thumbnailUrl: string,
     *         source: string,
     *         size: string,
     *         format: string,
     *         rank: int,
     *     }>,
     *     query: array{
     *         text: string,
     *         offset: int,
     *         count: int,
     *         total: int,
     *     },
     *     response: array{
     *         time: float,
     *         status: string,
     *         version: string,
     *     },
     *     error: string,
     * }
     */
    public function searchImages(
        string $query,
        int $offset = 0,
        int $count = 20,
        string $lang = 'en',
        string $country = 'us',
        string $size = '',
        string $color = '',
        string $type = '',
    ): array {
        try {
            $params = [
                'q' => $query,
                'offset' => max(0, $offset),
                'count' => max(1, min($count, 100)),
                'format' => 'json',
                'lang' => $lang,
                'country' => $country,
                'type' => 'images',
            ];

            if ($size) {
                $params['size'] = $size;
            }

            if ($color) {
                $params['color'] = $color;
            }

            if ($type) {
                $params['imgtype'] = $type;
            }

            if ($this->apiKey) {
                $params['api_key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'images' => array_map(fn ($image) => [
                    'title' => $image['title'] ?? '',
                    'desc' => $image['desc'] ?? '',
                    'url' => $image['url'] ?? '',
                    'imgUrl' => $image['imgUrl'] ?? '',
                    'thumbnailUrl' => $image['thumbnailUrl'] ?? '',
                    'source' => $image['source'] ?? '',
                    'size' => $image['size'] ?? '',
                    'format' => $image['format'] ?? '',
                    'rank' => $image['rank'] ?? 0,
                ], $data['results'] ?? []),
                'query' => [
                    'text' => $data['query']['text'] ?? $query,
                    'offset' => $data['query']['offset'] ?? $offset,
                    'count' => $data['query']['count'] ?? $count,
                    'total' => $data['query']['total'] ?? 0,
                ],
                'response' => [
                    'time' => $data['response']['time'] ?? 0.0,
                    'status' => $data['response']['status'] ?? 'ok',
                    'version' => $data['response']['version'] ?? '1.0',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'images' => [],
                'query' => ['text' => $query, 'offset' => $offset, 'count' => $count, 'total' => 0],
                'response' => ['time' => 0.0, 'status' => 'error', 'version' => '1.0'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get autocomplete suggestions using Mojeek.
     *
     * @param string $query   Partial query for autocomplete
     * @param int    $count   Number of suggestions
     * @param string $lang    Language code
     * @param string $country Country code
     *
     * @return array{
     *     success: bool,
     *     suggestions: array<int, string>,
     *     query: array{
     *         text: string,
     *         count: int,
     *     },
     *     response: array{
     *         time: float,
     *         status: string,
     *         version: string,
     *     },
     *     error: string,
     * }
     */
    public function autocomplete(
        string $query,
        int $count = 10,
        string $lang = 'en',
        string $country = 'us',
    ): array {
        try {
            $params = [
                'q' => $query,
                'count' => max(1, min($count, 20)),
                'format' => 'json',
                'lang' => $lang,
                'country' => $country,
                'type' => 'suggest',
            ];

            if ($this->apiKey) {
                $params['api_key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'suggestions' => $data['suggestions'] ?? [],
                'query' => [
                    'text' => $data['query']['text'] ?? $query,
                    'count' => $data['query']['count'] ?? $count,
                ],
                'response' => [
                    'time' => $data['response']['time'] ?? 0.0,
                    'status' => $data['response']['status'] ?? 'ok',
                    'version' => $data['response']['version'] ?? '1.0',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'suggestions' => [],
                'query' => ['text' => $query, 'count' => $count],
                'response' => ['time' => 0.0, 'status' => 'error', 'version' => '1.0'],
                'error' => $e->getMessage(),
            ];
        }
    }
}
