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
#[AsTool('searchapi_search', 'Tool that searches using SearchAPI')]
#[AsTool('searchapi_search_news', 'Tool that searches news using SearchAPI', method: 'searchNews')]
#[AsTool('searchapi_search_images', 'Tool that searches images using SearchAPI', method: 'searchImages')]
#[AsTool('searchapi_search_shopping', 'Tool that searches shopping products using SearchAPI', method: 'searchShopping')]
#[AsTool('searchapi_search_videos', 'Tool that searches videos using SearchAPI', method: 'searchVideos')]
#[AsTool('searchapi_search_places', 'Tool that searches places using SearchAPI', method: 'searchPlaces')]
#[AsTool('searchapi_get_serp', 'Tool that gets SERP data using SearchAPI', method: 'getSerp')]
#[AsTool('searchapi_get_locations', 'Tool that gets available locations using SearchAPI', method: 'getLocations')]
final readonly class SearchApiClient
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://www.searchapi.io/api/v1',
        private array $options = [],
    ) {
    }

    /**
     * Search using SearchAPI.
     *
     * @param string               $query       Search query
     * @param string               $engine      Search engine (google, bing, yahoo, duckduckgo)
     * @param string               $country     Country code (us, uk, de, fr, etc.)
     * @param string               $language    Language code (en, es, fr, de, etc.)
     * @param int                  $num         Number of results
     * @param int                  $start       Start index
     * @param string               $device      Device type (desktop, mobile, tablet)
     * @param array<string, mixed> $extraParams Extra parameters
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array{
     *         title: string,
     *         link: string,
     *         snippet: string,
     *         position: int,
     *         source: string,
     *     }>,
     *     searchInformation: array{
     *         totalResults: string,
     *         searchTime: float,
     *         query: string,
     *     },
     *     pagination: array{
     *         current: int,
     *         next: string,
     *         otherPages: array<int, string>,
     *     },
     *     relatedSearches: array<int, string>,
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        string $engine = 'google',
        string $country = 'us',
        string $language = 'en',
        int $num = 10,
        int $start = 0,
        string $device = 'desktop',
        array $extraParams = [],
    ): array {
        try {
            $params = [
                'api_key' => $this->apiKey,
                'q' => $query,
                'engine' => $engine,
                'country' => $country,
                'language' => $language,
                'num' => max(1, min($num, 100)),
                'start' => max(0, $start),
                'device' => $device,
            ];

            $params = array_merge($params, $extraParams);

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'results' => array_map(fn ($result) => [
                    'title' => $result['title'] ?? '',
                    'link' => $result['link'] ?? '',
                    'snippet' => $result['snippet'] ?? '',
                    'position' => $result['position'] ?? 0,
                    'source' => $result['source'] ?? '',
                ], $data['organic_results'] ?? []),
                'searchInformation' => [
                    'totalResults' => $data['search_information']['total_results'] ?? '0',
                    'searchTime' => $data['search_information']['time_taken_displayed'] ?? 0.0,
                    'query' => $data['search_information']['query_displayed'] ?? $query,
                ],
                'pagination' => [
                    'current' => $data['pagination']['current'] ?? 1,
                    'next' => $data['pagination']['next'] ?? '',
                    'otherPages' => $data['pagination']['other_pages'] ?? [],
                ],
                'relatedSearches' => array_map(fn ($search) => $search['query'], $data['related_searches'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'searchInformation' => ['totalResults' => '0', 'searchTime' => 0.0, 'query' => $query],
                'pagination' => ['current' => 1, 'next' => '', 'otherPages' => []],
                'relatedSearches' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search news using SearchAPI.
     *
     * @param string $query     News search query
     * @param string $country   Country code
     * @param string $language  Language code
     * @param int    $num       Number of results
     * @param int    $start     Start index
     * @param string $timeframe Time frame (d, w, m, y)
     * @param string $device    Device type
     *
     * @return array{
     *     success: bool,
     *     news: array<int, array{
     *         title: string,
     *         link: string,
     *         snippet: string,
     *         date: string,
     *         source: string,
     *         position: int,
     *         thumbnail: string,
     *     }>,
     *     searchInformation: array{
     *         totalResults: string,
     *         searchTime: float,
     *         query: string,
     *     },
     *     error: string,
     * }
     */
    public function searchNews(
        string $query,
        string $country = 'us',
        string $language = 'en',
        int $num = 10,
        int $start = 0,
        string $timeframe = 'w',
        string $device = 'desktop',
    ): array {
        try {
            $params = [
                'api_key' => $this->apiKey,
                'q' => $query,
                'engine' => 'google',
                'tbm' => 'nws',
                'country' => $country,
                'language' => $language,
                'num' => max(1, min($num, 100)),
                'start' => max(0, $start),
                'device' => $device,
                'tbs' => "qdr:{$timeframe}",
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'news' => array_map(fn ($article) => [
                    'title' => $article['title'] ?? '',
                    'link' => $article['link'] ?? '',
                    'snippet' => $article['snippet'] ?? '',
                    'date' => $article['date'] ?? '',
                    'source' => $article['source'] ?? '',
                    'position' => $article['position'] ?? 0,
                    'thumbnail' => $article['thumbnail'] ?? '',
                ], $data['news_results'] ?? []),
                'searchInformation' => [
                    'totalResults' => $data['search_information']['total_results'] ?? '0',
                    'searchTime' => $data['search_information']['time_taken_displayed'] ?? 0.0,
                    'query' => $data['search_information']['query_displayed'] ?? $query,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'news' => [],
                'searchInformation' => ['totalResults' => '0', 'searchTime' => 0.0, 'query' => $query],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search images using SearchAPI.
     *
     * @param string $query    Image search query
     * @param string $country  Country code
     * @param string $language Language code
     * @param int    $num      Number of results
     * @param int    $start    Start index
     * @param string $color    Color filter (red, orange, yellow, green, teal, blue, purple, pink, white, gray, black, brown)
     * @param string $size     Size filter (large, medium, icon)
     * @param string $type     Type filter (photo, clipart, lineart, animated)
     * @param string $device   Device type
     *
     * @return array{
     *     success: bool,
     *     images: array<int, array{
     *         title: string,
     *         link: string,
     *         image: string,
     *         source: string,
     *         thumbnail: string,
     *         position: int,
     *         originalWidth: int,
     *         originalHeight: int,
     *     }>,
     *     searchInformation: array{
     *         totalResults: string,
     *         searchTime: float,
     *         query: string,
     *     },
     *     error: string,
     * }
     */
    public function searchImages(
        string $query,
        string $country = 'us',
        string $language = 'en',
        int $num = 20,
        int $start = 0,
        string $color = '',
        string $size = '',
        string $type = '',
        string $device = 'desktop',
    ): array {
        try {
            $params = [
                'api_key' => $this->apiKey,
                'q' => $query,
                'engine' => 'google',
                'tbm' => 'isch',
                'country' => $country,
                'language' => $language,
                'num' => max(1, min($num, 100)),
                'start' => max(0, $start),
                'device' => $device,
            ];

            if ($color) {
                $params['tbs'] = "ic:specific,isc:{$color}";
            }

            if ($size) {
                $params['isz'] = $size;
            }

            if ($type) {
                $params['itp'] = $type;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'images' => array_map(fn ($image) => [
                    'title' => $image['title'] ?? '',
                    'link' => $image['link'] ?? '',
                    'image' => $image['image'] ?? '',
                    'source' => $image['source'] ?? '',
                    'thumbnail' => $image['thumbnail'] ?? '',
                    'position' => $image['position'] ?? 0,
                    'originalWidth' => $image['original_width'] ?? 0,
                    'originalHeight' => $image['original_height'] ?? 0,
                ], $data['images_results'] ?? []),
                'searchInformation' => [
                    'totalResults' => $data['search_information']['total_results'] ?? '0',
                    'searchTime' => $data['search_information']['time_taken_displayed'] ?? 0.0,
                    'query' => $data['search_information']['query_displayed'] ?? $query,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'images' => [],
                'searchInformation' => ['totalResults' => '0', 'searchTime' => 0.0, 'query' => $query],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search shopping products using SearchAPI.
     *
     * @param string $query    Shopping search query
     * @param string $country  Country code
     * @param string $language Language code
     * @param int    $num      Number of results
     * @param int    $start    Start index
     * @param string $sort     Sort order (price_asc, price_desc, rating, relevance)
     * @param string $device   Device type
     *
     * @return array{
     *     success: bool,
     *     shopping: array<int, array{
     *         title: string,
     *         link: string,
     *         price: string,
     *         rating: float,
     *         reviews: int,
     *         source: string,
     *         thumbnail: string,
     *         position: int,
     *         delivery: string,
     *     }>,
     *     searchInformation: array{
     *         totalResults: string,
     *         searchTime: float,
     *         query: string,
     *     },
     *     error: string,
     * }
     */
    public function searchShopping(
        string $query,
        string $country = 'us',
        string $language = 'en',
        int $num = 10,
        int $start = 0,
        string $sort = 'relevance',
        string $device = 'desktop',
    ): array {
        try {
            $params = [
                'api_key' => $this->apiKey,
                'q' => $query,
                'engine' => 'google',
                'tbm' => 'shop',
                'country' => $country,
                'language' => $language,
                'num' => max(1, min($num, 100)),
                'start' => max(0, $start),
                'device' => $device,
            ];

            if ($sort) {
                $params['tbs'] = 'sbd:1';
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'shopping' => array_map(fn ($product) => [
                    'title' => $product['title'] ?? '',
                    'link' => $product['link'] ?? '',
                    'price' => $product['price'] ?? '',
                    'rating' => $product['rating'] ?? 0.0,
                    'reviews' => $product['reviews'] ?? 0,
                    'source' => $product['source'] ?? '',
                    'thumbnail' => $product['thumbnail'] ?? '',
                    'position' => $product['position'] ?? 0,
                    'delivery' => $product['delivery'] ?? '',
                ], $data['shopping_results'] ?? []),
                'searchInformation' => [
                    'totalResults' => $data['search_information']['total_results'] ?? '0',
                    'searchTime' => $data['search_information']['time_taken_displayed'] ?? 0.0,
                    'query' => $data['search_information']['query_displayed'] ?? $query,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'shopping' => [],
                'searchInformation' => ['totalResults' => '0', 'searchTime' => 0.0, 'query' => $query],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search videos using SearchAPI.
     *
     * @param string $query    Video search query
     * @param string $country  Country code
     * @param string $language Language code
     * @param int    $num      Number of results
     * @param int    $start    Start index
     * @param string $duration Duration filter (short, medium, long)
     * @param string $device   Device type
     *
     * @return array{
     *     success: bool,
     *     videos: array<int, array{
     *         title: string,
     *         link: string,
     *         snippet: string,
     *         date: string,
     *         duration: string,
     *         views: string,
     *         channel: string,
     *         thumbnail: string,
     *         position: int,
     *     }>,
     *     searchInformation: array{
     *         totalResults: string,
     *         searchTime: float,
     *         query: string,
     *     },
     *     error: string,
     * }
     */
    public function searchVideos(
        string $query,
        string $country = 'us',
        string $language = 'en',
        int $num = 10,
        int $start = 0,
        string $duration = '',
        string $device = 'desktop',
    ): array {
        try {
            $params = [
                'api_key' => $this->apiKey,
                'q' => $query,
                'engine' => 'google',
                'tbm' => 'vid',
                'country' => $country,
                'language' => $language,
                'num' => max(1, min($num, 100)),
                'start' => max(0, $start),
                'device' => $device,
            ];

            if ($duration) {
                $params['tbs'] = "dur:{$duration}";
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'videos' => array_map(fn ($video) => [
                    'title' => $video['title'] ?? '',
                    'link' => $video['link'] ?? '',
                    'snippet' => $video['snippet'] ?? '',
                    'date' => $video['date'] ?? '',
                    'duration' => $video['duration'] ?? '',
                    'views' => $video['views'] ?? '',
                    'channel' => $video['channel'] ?? '',
                    'thumbnail' => $video['thumbnail'] ?? '',
                    'position' => $video['position'] ?? 0,
                ], $data['video_results'] ?? []),
                'searchInformation' => [
                    'totalResults' => $data['search_information']['total_results'] ?? '0',
                    'searchTime' => $data['search_information']['time_taken_displayed'] ?? 0.0,
                    'query' => $data['search_information']['query_displayed'] ?? $query,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'videos' => [],
                'searchInformation' => ['totalResults' => '0', 'searchTime' => 0.0, 'query' => $query],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search places using SearchAPI.
     *
     * @param string $query    Place search query
     * @param string $country  Country code
     * @param string $language Language code
     * @param int    $num      Number of results
     * @param int    $start    Start index
     * @param string $device   Device type
     *
     * @return array{
     *     success: bool,
     *     places: array<int, array{
     *         title: string,
     *         link: string,
     *         snippet: string,
     *         address: string,
     *         phone: string,
     *         rating: float,
     *         reviews: int,
     *         price: string,
     *         hours: string,
     *         position: int,
     *     }>,
     *     searchInformation: array{
     *         totalResults: string,
     *         searchTime: float,
     *         query: string,
     *     },
     *     error: string,
     * }
     */
    public function searchPlaces(
        string $query,
        string $country = 'us',
        string $language = 'en',
        int $num = 10,
        int $start = 0,
        string $device = 'desktop',
    ): array {
        try {
            $params = [
                'api_key' => $this->apiKey,
                'q' => $query,
                'engine' => 'google',
                'tbm' => 'lcl',
                'country' => $country,
                'language' => $language,
                'num' => max(1, min($num, 100)),
                'start' => max(0, $start),
                'device' => $device,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'places' => array_map(fn ($place) => [
                    'title' => $place['title'] ?? '',
                    'link' => $place['link'] ?? '',
                    'snippet' => $place['snippet'] ?? '',
                    'address' => $place['address'] ?? '',
                    'phone' => $place['phone'] ?? '',
                    'rating' => $place['rating'] ?? 0.0,
                    'reviews' => $place['reviews'] ?? 0,
                    'price' => $place['price'] ?? '',
                    'hours' => $place['hours'] ?? '',
                    'position' => $place['position'] ?? 0,
                ], $data['local_results'] ?? []),
                'searchInformation' => [
                    'totalResults' => $data['search_information']['total_results'] ?? '0',
                    'searchTime' => $data['search_information']['time_taken_displayed'] ?? 0.0,
                    'query' => $data['search_information']['query_displayed'] ?? $query,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'places' => [],
                'searchInformation' => ['totalResults' => '0', 'searchTime' => 0.0, 'query' => $query],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get SERP data using SearchAPI.
     *
     * @param string $query    Search query
     * @param string $engine   Search engine
     * @param string $country  Country code
     * @param string $language Language code
     * @param int    $num      Number of results
     * @param int    $start    Start index
     * @param string $device   Device type
     *
     * @return array{
     *     success: bool,
     *     serp: array<string, mixed>,
     *     organicResults: array<int, array<string, mixed>>,
     *     paidResults: array<int, array<string, mixed>>,
     *     featuredSnippet: array<string, mixed>,
     *     knowledgeGraph: array<string, mixed>,
     *     searchInformation: array<string, mixed>,
     *     error: string,
     * }
     */
    public function getSerp(
        string $query,
        string $engine = 'google',
        string $country = 'us',
        string $language = 'en',
        int $num = 10,
        int $start = 0,
        string $device = 'desktop',
    ): array {
        try {
            $params = [
                'api_key' => $this->apiKey,
                'q' => $query,
                'engine' => $engine,
                'country' => $country,
                'language' => $language,
                'num' => max(1, min($num, 100)),
                'start' => max(0, $start),
                'device' => $device,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'serp' => $data,
                'organicResults' => $data['organic_results'] ?? [],
                'paidResults' => $data['paid_results'] ?? [],
                'featuredSnippet' => $data['featured_snippet'] ?? [],
                'knowledgeGraph' => $data['knowledge_graph'] ?? [],
                'searchInformation' => $data['search_information'] ?? [],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'serp' => [],
                'organicResults' => [],
                'paidResults' => [],
                'featuredSnippet' => [],
                'knowledgeGraph' => [],
                'searchInformation' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available locations using SearchAPI.
     *
     * @return array{
     *     success: bool,
     *     locations: array<int, array{
     *         country: string,
     *         countryCode: string,
     *         language: string,
     *         languageCode: string,
     *         currency: string,
     *         currencyCode: string,
     *     }>,
     *     error: string,
     * }
     */
    public function getLocations(): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/locations", [
                'query' => array_merge($this->options, ['api_key' => $this->apiKey]),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'locations' => array_map(fn ($location) => [
                    'country' => $location['country'] ?? '',
                    'countryCode' => $location['country_code'] ?? '',
                    'language' => $location['language'] ?? '',
                    'languageCode' => $location['language_code'] ?? '',
                    'currency' => $location['currency'] ?? '',
                    'currencyCode' => $location['currency_code'] ?? '',
                ], $data['locations'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'locations' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}
