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
#[AsTool('searx_search', 'Tool that searches using SearX meta-search engine')]
#[AsTool('searx_search_images', 'Tool that searches images using SearX', method: 'searchImages')]
#[AsTool('searx_search_news', 'Tool that searches news using SearX', method: 'searchNews')]
#[AsTool('searx_search_videos', 'Tool that searches videos using SearX', method: 'searchVideos')]
#[AsTool('searx_search_files', 'Tool that searches files using SearX', method: 'searchFiles')]
#[AsTool('searx_search_maps', 'Tool that searches maps using SearX', method: 'searchMaps')]
#[AsTool('searx_search_music', 'Tool that searches music using SearX', method: 'searchMusic')]
#[AsTool('searx_search_it', 'Tool that searches IT-related content using SearX', method: 'searchIT')]
final readonly class SearxSearch
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'https://searx.be',
        private array $options = [],
    ) {
    }

    /**
     * Search using SearX meta-search engine.
     *
     * @param string        $query      Search query
     * @param array<string> $categories Search categories (general, images, videos, news, files, maps, music, it)
     * @param string        $language   Search language
     * @param string        $timeRange  Time range filter (day, week, month, year)
     * @param int           $page       Page number
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         engine: string,
     *         parsedUrl: array<string, mixed>,
     *         template: string,
     *         engines: array<int, string>,
     *         positions: array<int, int>,
     *         score: float,
     *         category: string,
     *     }>,
     *     answers: array<int, string>,
     *     corrections: array<int, string>,
     *     infoboxes: array<int, array{
     *         infobox: string,
     *         content: string,
     *         engine: string,
     *         urls: array<int, array{
     *             title: string,
     *             url: string,
     *         }>,
     *     }>,
     *     suggestions: array<int, string>,
     *     unresponsiveEngines: array<int, string>,
     *     query: string,
     *     numberOfResults: int,
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        array $categories = ['general'],
        string $language = 'all',
        string $timeRange = '',
        int $page = 0,
    ): array {
        try {
            $params = [
                'q' => $query,
                'categories' => implode(',', $categories),
                'language' => $language,
                'pageno' => $page,
            ];

            if ($timeRange) {
                $params['time_range'] = $timeRange;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'results' => array_map(fn ($result) => [
                    'title' => $result['title'] ?? '',
                    'url' => $result['url'] ?? '',
                    'content' => $result['content'] ?? '',
                    'engine' => $result['engine'] ?? '',
                    'parsedUrl' => $result['parsed_url'] ?? [],
                    'template' => $result['template'] ?? '',
                    'engines' => $result['engines'] ?? [],
                    'positions' => $result['positions'] ?? [],
                    'score' => $result['score'] ?? 0.0,
                    'category' => $result['category'] ?? 'general',
                ], $data['results'] ?? []),
                'answers' => $data['answers'] ?? [],
                'corrections' => $data['corrections'] ?? [],
                'infoboxes' => array_map(fn ($infobox) => [
                    'infobox' => $infobox['infobox'] ?? '',
                    'content' => $infobox['content'] ?? '',
                    'engine' => $infobox['engine'] ?? '',
                    'urls' => array_map(fn ($url) => [
                        'title' => $url['title'] ?? '',
                        'url' => $url['url'] ?? '',
                    ], $infobox['urls'] ?? []),
                ], $data['infoboxes'] ?? []),
                'suggestions' => $data['suggestions'] ?? [],
                'unresponsiveEngines' => $data['unresponsive_engines'] ?? [],
                'query' => $query,
                'numberOfResults' => $data['number_of_results'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'answers' => [],
                'corrections' => [],
                'infoboxes' => [],
                'suggestions' => [],
                'unresponsiveEngines' => [],
                'query' => $query,
                'numberOfResults' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search images using SearX.
     *
     * @param string $query     Image search query
     * @param string $language  Search language
     * @param string $timeRange Time range filter
     * @param int    $page      Page number
     *
     * @return array{
     *     success: bool,
     *     images: array<int, array{
     *         title: string,
     *         url: string,
     *         imgSrc: string,
     *         thumbnailSrc: string,
     *         template: string,
     *         engine: string,
     *         engines: array<int, string>,
     *         positions: array<int, int>,
     *         content: string,
     *         author: string,
     *         source: string,
     *         thumbnail: string,
     *         imgFormat: string,
     *         resolution: string,
     *         parsedUrl: array<string, mixed>,
     *     }>,
     *     query: string,
     *     numberOfResults: int,
     *     error: string,
     * }
     */
    public function searchImages(
        string $query,
        string $language = 'all',
        string $timeRange = '',
        int $page = 0,
    ): array {
        try {
            $params = [
                'q' => $query,
                'categories' => 'images',
                'language' => $language,
                'pageno' => $page,
            ];

            if ($timeRange) {
                $params['time_range'] = $timeRange;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'images' => array_map(fn ($image) => [
                    'title' => $image['title'] ?? '',
                    'url' => $image['url'] ?? '',
                    'imgSrc' => $image['img_src'] ?? '',
                    'thumbnailSrc' => $image['thumbnail_src'] ?? '',
                    'template' => $image['template'] ?? '',
                    'engine' => $image['engine'] ?? '',
                    'engines' => $image['engines'] ?? [],
                    'positions' => $image['positions'] ?? [],
                    'content' => $image['content'] ?? '',
                    'author' => $image['author'] ?? '',
                    'source' => $image['source'] ?? '',
                    'thumbnail' => $image['thumbnail'] ?? '',
                    'imgFormat' => $image['img_format'] ?? '',
                    'resolution' => $image['resolution'] ?? '',
                    'parsedUrl' => $image['parsed_url'] ?? [],
                ], $data['results'] ?? []),
                'query' => $query,
                'numberOfResults' => $data['number_of_results'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'images' => [],
                'query' => $query,
                'numberOfResults' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search news using SearX.
     *
     * @param string $query     News search query
     * @param string $language  Search language
     * @param string $timeRange Time range filter
     * @param int    $page      Page number
     *
     * @return array{
     *     success: bool,
     *     news: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         publishedDate: string,
     *         engine: string,
     *         template: string,
     *         engines: array<int, string>,
     *         positions: array<int, int>,
     *         parsedUrl: array<string, mixed>,
     *     }>,
     *     query: string,
     *     numberOfResults: int,
     *     error: string,
     * }
     */
    public function searchNews(
        string $query,
        string $language = 'all',
        string $timeRange = '',
        int $page = 0,
    ): array {
        try {
            $params = [
                'q' => $query,
                'categories' => 'news',
                'language' => $language,
                'pageno' => $page,
            ];

            if ($timeRange) {
                $params['time_range'] = $timeRange;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'news' => array_map(fn ($article) => [
                    'title' => $article['title'] ?? '',
                    'url' => $article['url'] ?? '',
                    'content' => $article['content'] ?? '',
                    'publishedDate' => $article['publishedDate'] ?? '',
                    'engine' => $article['engine'] ?? '',
                    'template' => $article['template'] ?? '',
                    'engines' => $article['engines'] ?? [],
                    'positions' => $article['positions'] ?? [],
                    'parsedUrl' => $article['parsed_url'] ?? [],
                ], $data['results'] ?? []),
                'query' => $query,
                'numberOfResults' => $data['number_of_results'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'news' => [],
                'query' => $query,
                'numberOfResults' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search videos using SearX.
     *
     * @param string $query     Video search query
     * @param string $language  Search language
     * @param string $timeRange Time range filter
     * @param int    $page      Page number
     *
     * @return array{
     *     success: bool,
     *     videos: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         thumbnail: string,
     *         publishedDate: string,
     *         length: string,
     *         engine: string,
     *         template: string,
     *         engines: array<int, string>,
     *         positions: array<int, int>,
     *         parsedUrl: array<string, mixed>,
     *     }>,
     *     query: string,
     *     numberOfResults: int,
     *     error: string,
     * }
     */
    public function searchVideos(
        string $query,
        string $language = 'all',
        string $timeRange = '',
        int $page = 0,
    ): array {
        try {
            $params = [
                'q' => $query,
                'categories' => 'videos',
                'language' => $language,
                'pageno' => $page,
            ];

            if ($timeRange) {
                $params['time_range'] = $timeRange;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'videos' => array_map(fn ($video) => [
                    'title' => $video['title'] ?? '',
                    'url' => $video['url'] ?? '',
                    'content' => $video['content'] ?? '',
                    'thumbnail' => $video['thumbnail'] ?? '',
                    'publishedDate' => $video['publishedDate'] ?? '',
                    'length' => $video['length'] ?? '',
                    'engine' => $video['engine'] ?? '',
                    'template' => $video['template'] ?? '',
                    'engines' => $video['engines'] ?? [],
                    'positions' => $video['positions'] ?? [],
                    'parsedUrl' => $video['parsed_url'] ?? [],
                ], $data['results'] ?? []),
                'query' => $query,
                'numberOfResults' => $data['number_of_results'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'videos' => [],
                'query' => $query,
                'numberOfResults' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search files using SearX.
     *
     * @param string $query     File search query
     * @param string $language  Search language
     * @param string $timeRange Time range filter
     * @param int    $page      Page number
     *
     * @return array{
     *     success: bool,
     *     files: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         template: string,
     *         engine: string,
     *         engines: array<int, string>,
     *         positions: array<int, int>,
     *         parsedUrl: array<string, mixed>,
     *         size: string,
     *         type: string,
     *     }>,
     *     query: string,
     *     numberOfResults: int,
     *     error: string,
     * }
     */
    public function searchFiles(
        string $query,
        string $language = 'all',
        string $timeRange = '',
        int $page = 0,
    ): array {
        try {
            $params = [
                'q' => $query,
                'categories' => 'files',
                'language' => $language,
                'pageno' => $page,
            ];

            if ($timeRange) {
                $params['time_range'] = $timeRange;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'files' => array_map(fn ($file) => [
                    'title' => $file['title'] ?? '',
                    'url' => $file['url'] ?? '',
                    'content' => $file['content'] ?? '',
                    'template' => $file['template'] ?? '',
                    'engine' => $file['engine'] ?? '',
                    'engines' => $file['engines'] ?? [],
                    'positions' => $file['positions'] ?? [],
                    'parsedUrl' => $file['parsed_url'] ?? [],
                    'size' => $file['size'] ?? '',
                    'type' => $file['type'] ?? '',
                ], $data['results'] ?? []),
                'query' => $query,
                'numberOfResults' => $data['number_of_results'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'files' => [],
                'query' => $query,
                'numberOfResults' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search maps using SearX.
     *
     * @param string $query    Map search query
     * @param string $language Search language
     * @param int    $page     Page number
     *
     * @return array{
     *     success: bool,
     *     maps: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         address: string,
     *         latitude: float,
     *         longitude: float,
     *         engine: string,
     *         template: string,
     *         engines: array<int, string>,
     *         positions: array<int, int>,
     *         parsedUrl: array<string, mixed>,
     *     }>,
     *     query: string,
     *     numberOfResults: int,
     *     error: string,
     * }
     */
    public function searchMaps(
        string $query,
        string $language = 'all',
        int $page = 0,
    ): array {
        try {
            $params = [
                'q' => $query,
                'categories' => 'maps',
                'language' => $language,
                'pageno' => $page,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'maps' => array_map(fn ($map) => [
                    'title' => $map['title'] ?? '',
                    'url' => $map['url'] ?? '',
                    'content' => $map['content'] ?? '',
                    'address' => $map['address'] ?? '',
                    'latitude' => $map['latitude'] ?? 0.0,
                    'longitude' => $map['longitude'] ?? 0.0,
                    'engine' => $map['engine'] ?? '',
                    'template' => $map['template'] ?? '',
                    'engines' => $map['engines'] ?? [],
                    'positions' => $map['positions'] ?? [],
                    'parsedUrl' => $map['parsed_url'] ?? [],
                ], $data['results'] ?? []),
                'query' => $query,
                'numberOfResults' => $data['number_of_results'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'maps' => [],
                'query' => $query,
                'numberOfResults' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search music using SearX.
     *
     * @param string $query     Music search query
     * @param string $language  Search language
     * @param string $timeRange Time range filter
     * @param int    $page      Page number
     *
     * @return array{
     *     success: bool,
     *     music: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         thumbnail: string,
     *         publishedDate: string,
     *         length: string,
     *         artist: string,
     *         album: string,
     *         engine: string,
     *         template: string,
     *         engines: array<int, string>,
     *         positions: array<int, int>,
     *         parsedUrl: array<string, mixed>,
     *     }>,
     *     query: string,
     *     numberOfResults: int,
     *     error: string,
     * }
     */
    public function searchMusic(
        string $query,
        string $language = 'all',
        string $timeRange = '',
        int $page = 0,
    ): array {
        try {
            $params = [
                'q' => $query,
                'categories' => 'music',
                'language' => $language,
                'pageno' => $page,
            ];

            if ($timeRange) {
                $params['time_range'] = $timeRange;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'music' => array_map(fn ($track) => [
                    'title' => $track['title'] ?? '',
                    'url' => $track['url'] ?? '',
                    'content' => $track['content'] ?? '',
                    'thumbnail' => $track['thumbnail'] ?? '',
                    'publishedDate' => $track['publishedDate'] ?? '',
                    'length' => $track['length'] ?? '',
                    'artist' => $track['artist'] ?? '',
                    'album' => $track['album'] ?? '',
                    'engine' => $track['engine'] ?? '',
                    'template' => $track['template'] ?? '',
                    'engines' => $track['engines'] ?? [],
                    'positions' => $track['positions'] ?? [],
                    'parsedUrl' => $track['parsed_url'] ?? [],
                ], $data['results'] ?? []),
                'query' => $query,
                'numberOfResults' => $data['number_of_results'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'music' => [],
                'query' => $query,
                'numberOfResults' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search IT-related content using SearX.
     *
     * @param string $query     IT search query
     * @param string $language  Search language
     * @param string $timeRange Time range filter
     * @param int    $page      Page number
     *
     * @return array{
     *     success: bool,
     *     itResults: array<int, array{
     *         title: string,
     *         url: string,
     *         content: string,
     *         publishedDate: string,
     *         engine: string,
     *         template: string,
     *         engines: array<int, string>,
     *         positions: array<int, int>,
     *         parsedUrl: array<string, mixed>,
     *         tags: array<int, string>,
     *     }>,
     *     query: string,
     *     numberOfResults: int,
     *     error: string,
     * }
     */
    public function searchIT(
        string $query,
        string $language = 'all',
        string $timeRange = '',
        int $page = 0,
    ): array {
        try {
            $params = [
                'q' => $query,
                'categories' => 'it',
                'language' => $language,
                'pageno' => $page,
            ];

            if ($timeRange) {
                $params['time_range'] = $timeRange;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'itResults' => array_map(fn ($result) => [
                    'title' => $result['title'] ?? '',
                    'url' => $result['url'] ?? '',
                    'content' => $result['content'] ?? '',
                    'publishedDate' => $result['publishedDate'] ?? '',
                    'engine' => $result['engine'] ?? '',
                    'template' => $result['template'] ?? '',
                    'engines' => $result['engines'] ?? [],
                    'positions' => $result['positions'] ?? [],
                    'parsedUrl' => $result['parsed_url'] ?? [],
                    'tags' => $result['tags'] ?? [],
                ], $data['results'] ?? []),
                'query' => $query,
                'numberOfResults' => $data['number_of_results'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'itResults' => [],
                'query' => $query,
                'numberOfResults' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
