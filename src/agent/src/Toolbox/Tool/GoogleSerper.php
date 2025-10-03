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
#[AsTool('google_serper_search', 'Tool that searches Google via Serper API')]
#[AsTool('google_serper_image_search', 'Tool that searches Google Images via Serper API', method: 'imageSearch')]
#[AsTool('google_serper_news_search', 'Tool that searches Google News via Serper API', method: 'newsSearch')]
#[AsTool('google_serper_place_search', 'Tool that searches Google Places via Serper API', method: 'placeSearch')]
#[AsTool('google_serper_shopping_search', 'Tool that searches Google Shopping via Serper API', method: 'shoppingSearch')]
final readonly class GoogleSerper
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $baseUrl = 'https://google.serper.dev',
        private array $options = [],
    ) {
    }

    /**
     * Search Google via Serper API.
     *
     * @param string $query Search query
     * @param int    $num   Number of results
     * @param int    $start Start index
     * @param string $gl    Country code
     * @param string $hl    Language code
     * @param string $safe  Safe search
     *
     * @return array{
     *     searchParameters: array{
     *         q: string,
     *         type: string,
     *         engine: string,
     *         gl: string,
     *         hl: string,
     *         num: int,
     *         start: int,
     *     },
     *     organic: array<int, array{
     *         title: string,
     *         link: string,
     *         snippet: string,
     *         position: int,
     *         date: string,
     *         sitelinks: array<int, array{
     *             title: string,
     *             link: string,
     *         }>,
     *     }>,
     *     knowledgeGraph: array{
     *         title: string,
     *         type: string,
     *         website: string,
     *         imageUrl: string,
     *         description: string,
     *         descriptionSource: string,
     *         descriptionLink: string,
     *         attributes: array<string, mixed>,
     *     }|null,
     *     answerBox: array{
     *         answer: string,
     *         title: string,
     *         link: string,
     *         snippet: string,
     *         date: string,
     *     }|null,
     *     peopleAlsoAsk: array<int, array{
     *         question: string,
     *         snippet: string,
     *         title: string,
     *         link: string,
     *     }>,
     *     relatedSearches: array<int, array{
     *         query: string,
     *     }>,
     * }
     */
    public function __invoke(
        string $query,
        int $num = 10,
        int $start = 0,
        string $gl = '',
        string $hl = '',
        string $safe = 'off',
    ): array {
        try {
            $body = [
                'q' => $query,
                'num' => min(max($num, 1), 100),
                'start' => max($start, 0),
                'safe' => $safe,
            ];

            if ($gl) {
                $body['gl'] = $gl;
            }
            if ($hl) {
                $body['hl'] = $hl;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/search", [
                'headers' => [
                    'X-API-KEY' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'searchParameters' => [
                    'q' => $data['searchParameters']['q'] ?? $query,
                    'type' => $data['searchParameters']['type'] ?? 'search',
                    'engine' => $data['searchParameters']['engine'] ?? 'google',
                    'gl' => $data['searchParameters']['gl'] ?? $gl,
                    'hl' => $data['searchParameters']['hl'] ?? $hl,
                    'num' => $data['searchParameters']['num'] ?? $num,
                    'start' => $data['searchParameters']['start'] ?? $start,
                ],
                'organic' => array_map(fn ($result) => [
                    'title' => $result['title'],
                    'link' => $result['link'],
                    'snippet' => $result['snippet'] ?? '',
                    'position' => $result['position'],
                    'date' => $result['date'] ?? '',
                    'sitelinks' => array_map(fn ($link) => [
                        'title' => $link['title'],
                        'link' => $link['link'],
                    ], $result['sitelinks'] ?? []),
                ], $data['organic'] ?? []),
                'knowledgeGraph' => $data['knowledgeGraph'] ? [
                    'title' => $data['knowledgeGraph']['title'],
                    'type' => $data['knowledgeGraph']['type'],
                    'website' => $data['knowledgeGraph']['website'] ?? '',
                    'imageUrl' => $data['knowledgeGraph']['imageUrl'] ?? '',
                    'description' => $data['knowledgeGraph']['description'] ?? '',
                    'descriptionSource' => $data['knowledgeGraph']['descriptionSource'] ?? '',
                    'descriptionLink' => $data['knowledgeGraph']['descriptionLink'] ?? '',
                    'attributes' => $data['knowledgeGraph']['attributes'] ?? [],
                ] : null,
                'answerBox' => $data['answerBox'] ? [
                    'answer' => $data['answerBox']['answer'],
                    'title' => $data['answerBox']['title'] ?? '',
                    'link' => $data['answerBox']['link'] ?? '',
                    'snippet' => $data['answerBox']['snippet'] ?? '',
                    'date' => $data['answerBox']['date'] ?? '',
                ] : null,
                'peopleAlsoAsk' => array_map(fn ($item) => [
                    'question' => $item['question'],
                    'snippet' => $item['snippet'],
                    'title' => $item['title'] ?? '',
                    'link' => $item['link'] ?? '',
                ], $data['peopleAlsoAsk'] ?? []),
                'relatedSearches' => array_map(fn ($search) => [
                    'query' => $search['query'],
                ], $data['relatedSearches'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'searchParameters' => [
                    'q' => $query,
                    'type' => 'search',
                    'engine' => 'google',
                    'gl' => $gl,
                    'hl' => $hl,
                    'num' => $num,
                    'start' => $start,
                ],
                'organic' => [],
                'knowledgeGraph' => null,
                'answerBox' => null,
                'peopleAlsoAsk' => [],
                'relatedSearches' => [],
            ];
        }
    }

    /**
     * Search Google Images via Serper API.
     *
     * @param string $query     Search query
     * @param int    $num       Number of results
     * @param int    $start     Start index
     * @param string $gl        Country code
     * @param string $hl        Language code
     * @param string $safe      Safe search
     * @param string $imageType Image type filter
     * @param string $color     Color filter
     *
     * @return array{
     *     searchParameters: array{
     *         q: string,
     *         type: string,
     *         engine: string,
     *         gl: string,
     *         hl: string,
     *         num: int,
     *         start: int,
     *     },
     *     images: array<int, array{
     *         title: string,
     *         imageUrl: string,
     *         imageWidth: int,
     *         imageHeight: int,
     *         thumbnailUrl: string,
     *         thumbnailWidth: int,
     *         thumbnailHeight: int,
     *         source: string,
     *         domain: string,
     *         link: string,
     *         googleUrl: string,
     *         position: int,
     *     }>,
     * }
     */
    public function imageSearch(
        string $query,
        int $num = 10,
        int $start = 0,
        string $gl = '',
        string $hl = '',
        string $safe = 'off',
        string $imageType = '',
        string $color = '',
    ): array {
        try {
            $body = [
                'q' => $query,
                'num' => min(max($num, 1), 100),
                'start' => max($start, 0),
                'safe' => $safe,
            ];

            if ($gl) {
                $body['gl'] = $gl;
            }
            if ($hl) {
                $body['hl'] = $hl;
            }
            if ($imageType) {
                $body['imageType'] = $imageType;
            }
            if ($color) {
                $body['color'] = $color;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/images", [
                'headers' => [
                    'X-API-KEY' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'searchParameters' => [
                    'q' => $data['searchParameters']['q'] ?? $query,
                    'type' => $data['searchParameters']['type'] ?? 'images',
                    'engine' => $data['searchParameters']['engine'] ?? 'google',
                    'gl' => $data['searchParameters']['gl'] ?? $gl,
                    'hl' => $data['searchParameters']['hl'] ?? $hl,
                    'num' => $data['searchParameters']['num'] ?? $num,
                    'start' => $data['searchParameters']['start'] ?? $start,
                ],
                'images' => array_map(fn ($image) => [
                    'title' => $image['title'],
                    'imageUrl' => $image['imageUrl'],
                    'imageWidth' => $image['imageWidth'],
                    'imageHeight' => $image['imageHeight'],
                    'thumbnailUrl' => $image['thumbnailUrl'],
                    'thumbnailWidth' => $image['thumbnailWidth'],
                    'thumbnailHeight' => $image['thumbnailHeight'],
                    'source' => $image['source'],
                    'domain' => $image['domain'],
                    'link' => $image['link'],
                    'googleUrl' => $image['googleUrl'],
                    'position' => $image['position'],
                ], $data['images'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'searchParameters' => [
                    'q' => $query,
                    'type' => 'images',
                    'engine' => 'google',
                    'gl' => $gl,
                    'hl' => $hl,
                    'num' => $num,
                    'start' => $start,
                ],
                'images' => [],
            ];
        }
    }

    /**
     * Search Google News via Serper API.
     *
     * @param string $query Search query
     * @param int    $num   Number of results
     * @param int    $start Start index
     * @param string $gl    Country code
     * @param string $hl    Language code
     * @param string $sort  Sort order
     * @param string $when  Time filter
     *
     * @return array{
     *     searchParameters: array{
     *         q: string,
     *         type: string,
     *         engine: string,
     *         gl: string,
     *         hl: string,
     *         num: int,
     *         start: int,
     *     },
     *     news: array<int, array{
     *         title: string,
     *         link: string,
     *         snippet: string,
     *         date: string,
     *         position: int,
     *         source: string,
     *         imageUrl: string,
     *     }>,
     * }
     */
    public function newsSearch(
        string $query,
        int $num = 10,
        int $start = 0,
        string $gl = '',
        string $hl = '',
        string $sort = '',
        string $when = '',
    ): array {
        try {
            $body = [
                'q' => $query,
                'num' => min(max($num, 1), 100),
                'start' => max($start, 0),
            ];

            if ($gl) {
                $body['gl'] = $gl;
            }
            if ($hl) {
                $body['hl'] = $hl;
            }
            if ($sort) {
                $body['sort'] = $sort;
            }
            if ($when) {
                $body['when'] = $when;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/news", [
                'headers' => [
                    'X-API-KEY' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'searchParameters' => [
                    'q' => $data['searchParameters']['q'] ?? $query,
                    'type' => $data['searchParameters']['type'] ?? 'news',
                    'engine' => $data['searchParameters']['engine'] ?? 'google',
                    'gl' => $data['searchParameters']['gl'] ?? $gl,
                    'hl' => $data['searchParameters']['hl'] ?? $hl,
                    'num' => $data['searchParameters']['num'] ?? $num,
                    'start' => $data['searchParameters']['start'] ?? $start,
                ],
                'news' => array_map(fn ($news) => [
                    'title' => $news['title'],
                    'link' => $news['link'],
                    'snippet' => $news['snippet'] ?? '',
                    'date' => $news['date'] ?? '',
                    'position' => $news['position'],
                    'source' => $news['source'] ?? '',
                    'imageUrl' => $news['imageUrl'] ?? '',
                ], $data['news'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'searchParameters' => [
                    'q' => $query,
                    'type' => 'news',
                    'engine' => 'google',
                    'gl' => $gl,
                    'hl' => $hl,
                    'num' => $num,
                    'start' => $start,
                ],
                'news' => [],
            ];
        }
    }

    /**
     * Search Google Places via Serper API.
     *
     * @param string $query    Search query
     * @param string $location Location filter
     * @param int    $num      Number of results
     * @param string $gl       Country code
     * @param string $hl       Language code
     *
     * @return array{
     *     searchParameters: array{
     *         q: string,
     *         type: string,
     *         engine: string,
     *         gl: string,
     *         hl: string,
     *         num: int,
     *     },
     *     places: array<int, array{
     *         title: string,
     *         address: string,
     *         latitude: float,
     *         longitude: float,
     *         rating: float,
     *         reviews: int,
     *         type: string,
     *         website: string,
     *         phone: string,
     *         hours: array<string, string>,
     *         position: int,
     *     }>,
     * }
     */
    public function placeSearch(
        string $query,
        string $location = '',
        int $num = 10,
        string $gl = '',
        string $hl = '',
    ): array {
        try {
            $body = [
                'q' => $query,
                'num' => min(max($num, 1), 100),
            ];

            if ($location) {
                $body['location'] = $location;
            }
            if ($gl) {
                $body['gl'] = $gl;
            }
            if ($hl) {
                $body['hl'] = $hl;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/places", [
                'headers' => [
                    'X-API-KEY' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'searchParameters' => [
                    'q' => $data['searchParameters']['q'] ?? $query,
                    'type' => $data['searchParameters']['type'] ?? 'places',
                    'engine' => $data['searchParameters']['engine'] ?? 'google',
                    'gl' => $data['searchParameters']['gl'] ?? $gl,
                    'hl' => $data['searchParameters']['hl'] ?? $hl,
                    'num' => $data['searchParameters']['num'] ?? $num,
                ],
                'places' => array_map(fn ($place) => [
                    'title' => $place['title'],
                    'address' => $place['address'],
                    'latitude' => $place['latitude'],
                    'longitude' => $place['longitude'],
                    'rating' => $place['rating'] ?? 0.0,
                    'reviews' => $place['reviews'] ?? 0,
                    'type' => $place['type'] ?? '',
                    'website' => $place['website'] ?? '',
                    'phone' => $place['phone'] ?? '',
                    'hours' => $place['hours'] ?? [],
                    'position' => $place['position'],
                ], $data['places'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'searchParameters' => [
                    'q' => $query,
                    'type' => 'places',
                    'engine' => 'google',
                    'gl' => $gl,
                    'hl' => $hl,
                    'num' => $num,
                ],
                'places' => [],
            ];
        }
    }

    /**
     * Search Google Shopping via Serper API.
     *
     * @param string $query    Search query
     * @param int    $num      Number of results
     * @param int    $start    Start index
     * @param string $gl       Country code
     * @param string $hl       Language code
     * @param string $sort     Sort order
     * @param string $priceMin Minimum price
     * @param string $priceMax Maximum price
     *
     * @return array{
     *     searchParameters: array{
     *         q: string,
     *         type: string,
     *         engine: string,
     *         gl: string,
     *         hl: string,
     *         num: int,
     *         start: int,
     *     },
     *     shopping: array<int, array{
     *         title: string,
     *         link: string,
     *         price: string,
     *         extractedPrice: float,
     *         rating: float,
     *         reviews: int,
     *         extensions: array<int, string>,
     *         thumbnail: string,
     *         position: int,
     *         delivery: string,
     *     }>,
     * }
     */
    public function shoppingSearch(
        string $query,
        int $num = 10,
        int $start = 0,
        string $gl = '',
        string $hl = '',
        string $sort = '',
        string $priceMin = '',
        string $priceMax = '',
    ): array {
        try {
            $body = [
                'q' => $query,
                'num' => min(max($num, 1), 100),
                'start' => max($start, 0),
            ];

            if ($gl) {
                $body['gl'] = $gl;
            }
            if ($hl) {
                $body['hl'] = $hl;
            }
            if ($sort) {
                $body['sort'] = $sort;
            }
            if ($priceMin) {
                $body['priceMin'] = $priceMin;
            }
            if ($priceMax) {
                $body['priceMax'] = $priceMax;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/shopping", [
                'headers' => [
                    'X-API-KEY' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'searchParameters' => [
                    'q' => $data['searchParameters']['q'] ?? $query,
                    'type' => $data['searchParameters']['type'] ?? 'shopping',
                    'engine' => $data['searchParameters']['engine'] ?? 'google',
                    'gl' => $data['searchParameters']['gl'] ?? $gl,
                    'hl' => $data['searchParameters']['hl'] ?? $hl,
                    'num' => $data['searchParameters']['num'] ?? $num,
                    'start' => $data['searchParameters']['start'] ?? $start,
                ],
                'shopping' => array_map(fn ($item) => [
                    'title' => $item['title'],
                    'link' => $item['link'],
                    'price' => $item['price'],
                    'extractedPrice' => $item['extractedPrice'] ?? 0.0,
                    'rating' => $item['rating'] ?? 0.0,
                    'reviews' => $item['reviews'] ?? 0,
                    'extensions' => $item['extensions'] ?? [],
                    'thumbnail' => $item['thumbnail'] ?? '',
                    'position' => $item['position'],
                    'delivery' => $item['delivery'] ?? '',
                ], $data['shopping'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'searchParameters' => [
                    'q' => $query,
                    'type' => 'shopping',
                    'engine' => 'google',
                    'gl' => $gl,
                    'hl' => $hl,
                    'num' => $num,
                    'start' => $start,
                ],
                'shopping' => [],
            ];
        }
    }
}
