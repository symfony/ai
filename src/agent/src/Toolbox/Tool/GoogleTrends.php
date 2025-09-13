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
#[AsTool('google_trends_get_interest_over_time', 'Tool that gets Google Trends interest over time')]
#[AsTool('google_trends_get_interest_by_region', 'Tool that gets Google Trends interest by region', method: 'getInterestByRegion')]
#[AsTool('google_trends_get_related_queries', 'Tool that gets Google Trends related queries', method: 'getRelatedQueries')]
#[AsTool('google_trends_get_related_topics', 'Tool that gets Google Trends related topics', method: 'getRelatedTopics')]
#[AsTool('google_trends_get_realtime_trending_searches', 'Tool that gets realtime trending searches', method: 'getRealtimeTrendingSearches')]
#[AsTool('google_trends_get_daily_trending_searches', 'Tool that gets daily trending searches', method: 'getDailyTrendingSearches')]
final readonly class GoogleTrends
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'https://trends.google.com/trends/api',
        private array $options = [],
    ) {
    }

    /**
     * Get Google Trends interest over time.
     *
     * @param array<string> $keywords  Keywords to search for
     * @param string        $startDate Start date (YYYY-MM-DD)
     * @param string        $endDate   End date (YYYY-MM-DD)
     * @param string        $geo       Geographic location (country code)
     * @param string        $category  Search category
     * @param string        $gprop     Google property (web, images, news, youtube, froogle)
     *
     * @return array{
     *     timelineData: array<int, array{
     *         time: string,
     *         formattedTime: string,
     *         value: array<int, int>,
     *         formattedValue: array<int, string>,
     *     }>,
     *     keywords: array<int, string>,
     *     geo: string,
     *     category: int,
     * }
     */
    public function __invoke(
        array $keywords,
        string $startDate = '',
        string $endDate = '',
        string $geo = '',
        string $category = '',
        string $gprop = 'web',
    ): array {
        try {
            $params = [
                'hl' => 'en',
                'tz' => '-480',
                'req' => json_encode([
                    'time' => $this->buildTimeParam($startDate, $endDate),
                    'resolution' => 'DAY',
                    'locale' => 'en',
                    'comparisonItem' => array_map(fn ($keyword) => [
                        'keyword' => $keyword,
                        'geo' => $geo,
                        'time' => $this->buildTimeParam($startDate, $endDate),
                    ], $keywords),
                    'requestOptions' => [
                        'property' => $gprop,
                        'backend' => 'IZG',
                        'category' => $this->getCategoryId($category),
                    ],
                ]),
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/explore", [
                'query' => array_merge($this->options, $params),
            ]);

            $content = $response->getContent();
            $content = substr($content, 4); // Remove ')]}\n' prefix
            $data = json_decode($content, true);

            if (!$data || !isset($data['default']['timelineData'])) {
                return [
                    'timelineData' => [],
                    'keywords' => $keywords,
                    'geo' => $geo,
                    'category' => 0,
                ];
            }

            return [
                'timelineData' => array_map(fn ($item) => [
                    'time' => $item['time'],
                    'formattedTime' => $item['formattedTime'],
                    'value' => $item['value'],
                    'formattedValue' => $item['formattedValue'],
                ], $data['default']['timelineData']),
                'keywords' => $keywords,
                'geo' => $geo,
                'category' => $this->getCategoryId($category),
            ];
        } catch (\Exception $e) {
            return [
                'timelineData' => [],
                'keywords' => $keywords,
                'geo' => $geo,
                'category' => 0,
            ];
        }
    }

    /**
     * Get Google Trends interest by region.
     *
     * @param array<string> $keywords   Keywords to search for
     * @param string        $startDate  Start date (YYYY-MM-DD)
     * @param string        $endDate    End date (YYYY-MM-DD)
     * @param string        $geo        Geographic location (country code)
     * @param string        $category   Search category
     * @param string        $gprop      Google property
     * @param int           $resolution Resolution (COUNTRY, REGION, CITY)
     *
     * @return array{
     *     geoMapData: array<int, array{
     *         geoCode: string,
     *         geoName: string,
     *         value: array<int, int>,
     *         formattedValue: array<int, string>,
     *         hasData: array<int, bool>,
     *         maxValueIndex: int,
     *     }>,
     *     keywords: array<int, string>,
     *     geo: string,
     *     category: int,
     * }
     */
    public function getInterestByRegion(
        array $keywords,
        string $startDate = '',
        string $endDate = '',
        string $geo = '',
        string $category = '',
        string $gprop = 'web',
        int $resolution = 0,
    ): array {
        try {
            $resolutionMap = [0 => 'COUNTRY', 1 => 'REGION', 2 => 'CITY'];
            $resolutionStr = $resolutionMap[$resolution] ?? 'COUNTRY';

            $params = [
                'hl' => 'en',
                'tz' => '-480',
                'req' => json_encode([
                    'time' => $this->buildTimeParam($startDate, $endDate),
                    'resolution' => $resolutionStr,
                    'locale' => 'en',
                    'comparisonItem' => array_map(fn ($keyword) => [
                        'keyword' => $keyword,
                        'geo' => $geo,
                        'time' => $this->buildTimeParam($startDate, $endDate),
                    ], $keywords),
                    'requestOptions' => [
                        'property' => $gprop,
                        'backend' => 'IZG',
                        'category' => $this->getCategoryId($category),
                    ],
                ]),
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/explore", [
                'query' => array_merge($this->options, $params),
            ]);

            $content = $response->getContent();
            $content = substr($content, 4);
            $data = json_decode($content, true);

            if (!$data || !isset($data['default']['geoMapData'])) {
                return [
                    'geoMapData' => [],
                    'keywords' => $keywords,
                    'geo' => $geo,
                    'category' => 0,
                ];
            }

            return [
                'geoMapData' => array_map(fn ($item) => [
                    'geoCode' => $item['geoCode'],
                    'geoName' => $item['geoName'],
                    'value' => $item['value'],
                    'formattedValue' => $item['formattedValue'],
                    'hasData' => $item['hasData'],
                    'maxValueIndex' => $item['maxValueIndex'],
                ], $data['default']['geoMapData']),
                'keywords' => $keywords,
                'geo' => $geo,
                'category' => $this->getCategoryId($category),
            ];
        } catch (\Exception $e) {
            return [
                'geoMapData' => [],
                'keywords' => $keywords,
                'geo' => $geo,
                'category' => 0,
            ];
        }
    }

    /**
     * Get Google Trends related queries.
     *
     * @param array<string> $keywords  Keywords to search for
     * @param string        $startDate Start date (YYYY-MM-DD)
     * @param string        $endDate   End date (YYYY-MM-DD)
     * @param string        $geo       Geographic location
     * @param string        $category  Search category
     * @param string        $gprop     Google property
     * @param string        $rankType  Rank type (rising, top)
     *
     * @return array{
     *     relatedQueries: array<int, array{
     *         query: string,
     *         value: int,
     *         formattedValue: string,
     *         link: string,
     *     }>,
     *     keywords: array<int, string>,
     *     geo: string,
     *     category: int,
     * }
     */
    public function getRelatedQueries(
        array $keywords,
        string $startDate = '',
        string $endDate = '',
        string $geo = '',
        string $category = '',
        string $gprop = 'web',
        string $rankType = 'rising',
    ): array {
        try {
            $params = [
                'hl' => 'en',
                'tz' => '-480',
                'req' => json_encode([
                    'time' => $this->buildTimeParam($startDate, $endDate),
                    'locale' => 'en',
                    'comparisonItem' => array_map(fn ($keyword) => [
                        'keyword' => $keyword,
                        'geo' => $geo,
                        'time' => $this->buildTimeParam($startDate, $endDate),
                    ], $keywords),
                    'requestOptions' => [
                        'property' => $gprop,
                        'backend' => 'IZG',
                        'category' => $this->getCategoryId($category),
                    ],
                ]),
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/explore", [
                'query' => array_merge($this->options, $params),
            ]);

            $content = $response->getContent();
            $content = substr($content, 4);
            $data = json_decode($content, true);

            $relatedQueries = [];
            if (isset($data['default']['relatedQueries'])) {
                $queries = $data['default']['relatedQueries'];
                $rankKey = 'rising' === $rankType ? 'rising' : 'top';

                if (isset($queries['rankedList'][0]['rankedKeyword'])) {
                    $relatedQueries = array_map(fn ($item) => [
                        'query' => $item['query'],
                        'value' => $item['value'],
                        'formattedValue' => $item['formattedValue'],
                        'link' => $item['link'],
                    ], $queries['rankedList'][0]['rankedKeyword']);
                }
            }

            return [
                'relatedQueries' => $relatedQueries,
                'keywords' => $keywords,
                'geo' => $geo,
                'category' => $this->getCategoryId($category),
            ];
        } catch (\Exception $e) {
            return [
                'relatedQueries' => [],
                'keywords' => $keywords,
                'geo' => $geo,
                'category' => 0,
            ];
        }
    }

    /**
     * Get Google Trends related topics.
     *
     * @param array<string> $keywords  Keywords to search for
     * @param string        $startDate Start date (YYYY-MM-DD)
     * @param string        $endDate   End date (YYYY-MM-DD)
     * @param string        $geo       Geographic location
     * @param string        $category  Search category
     * @param string        $gprop     Google property
     * @param string        $rankType  Rank type (rising, top)
     *
     * @return array{
     *     relatedTopics: array<int, array{
     *         topic: string,
     *         type: string,
     *         value: int,
     *         formattedValue: string,
     *         link: string,
     *     }>,
     *     keywords: array<int, string>,
     *     geo: string,
     *     category: int,
     * }
     */
    public function getRelatedTopics(
        array $keywords,
        string $startDate = '',
        string $endDate = '',
        string $geo = '',
        string $category = '',
        string $gprop = 'web',
        string $rankType = 'rising',
    ): array {
        try {
            $params = [
                'hl' => 'en',
                'tz' => '-480',
                'req' => json_encode([
                    'time' => $this->buildTimeParam($startDate, $endDate),
                    'locale' => 'en',
                    'comparisonItem' => array_map(fn ($keyword) => [
                        'keyword' => $keyword,
                        'geo' => $geo,
                        'time' => $this->buildTimeParam($startDate, $endDate),
                    ], $keywords),
                    'requestOptions' => [
                        'property' => $gprop,
                        'backend' => 'IZG',
                        'category' => $this->getCategoryId($category),
                    ],
                ]),
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/explore", [
                'query' => array_merge($this->options, $params),
            ]);

            $content = $response->getContent();
            $content = substr($content, 4);
            $data = json_decode($content, true);

            $relatedTopics = [];
            if (isset($data['default']['relatedTopics'])) {
                $topics = $data['default']['relatedTopics'];
                $rankKey = 'rising' === $rankType ? 'rising' : 'top';

                if (isset($topics['rankedList'][0]['rankedKeyword'])) {
                    $relatedTopics = array_map(fn ($item) => [
                        'topic' => $item['topic']['mid'],
                        'type' => $item['topic']['type'],
                        'value' => $item['value'],
                        'formattedValue' => $item['formattedValue'],
                        'link' => $item['link'],
                    ], $topics['rankedList'][0]['rankedKeyword']);
                }
            }

            return [
                'relatedTopics' => $relatedTopics,
                'keywords' => $keywords,
                'geo' => $geo,
                'category' => $this->getCategoryId($category),
            ];
        } catch (\Exception $e) {
            return [
                'relatedTopics' => [],
                'keywords' => $keywords,
                'geo' => $geo,
                'category' => 0,
            ];
        }
    }

    /**
     * Get realtime trending searches.
     *
     * @param string $geo      Geographic location
     * @param string $category Search category
     * @param int    $count    Number of results
     *
     * @return array{
     *     trendingSearches: array<int, array{
     *         title: string,
     *         formattedTraffic: string,
     *         relatedQueries: array<int, string>,
     *         image: array{
     *             newsUrl: string,
     *             source: string,
     *             imageUrl: string,
     *         },
     *         articles: array<int, array{
     *             title: string,
     *             timeAgo: string,
     *             source: string,
     *             url: string,
     *             snippet: string,
     *         }>,
     *     }>,
     *     geo: string,
     *     category: int,
     * }
     */
    public function getRealtimeTrendingSearches(
        string $geo = 'US',
        string $category = '',
        int $count = 20,
    ): array {
        try {
            $params = [
                'hl' => 'en',
                'tz' => '-480',
                'cat' => $this->getCategoryId($category),
                'fi' => 0,
                'fs' => 0,
                'geo' => $geo,
                'ri' => 300,
                'rs' => $count,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/realtimetrends", [
                'query' => array_merge($this->options, $params),
            ]);

            $content = $response->getContent();
            $content = substr($content, 4);
            $data = json_decode($content, true);

            $trendingSearches = [];
            if (isset($data['default']['trendingSearchesDays'][0]['trendingSearches'])) {
                $trendingSearches = array_map(fn ($item) => [
                    'title' => $item['title']['query'],
                    'formattedTraffic' => $item['formattedTraffic'],
                    'relatedQueries' => array_map(fn ($query) => $query['query'], $item['relatedQueries']),
                    'image' => [
                        'newsUrl' => $item['image']['newsUrl'],
                        'source' => $item['image']['source'],
                        'imageUrl' => $item['image']['imageUrl'],
                    ],
                    'articles' => array_map(fn ($article) => [
                        'title' => $article['title'],
                        'timeAgo' => $article['timeAgo'],
                        'source' => $article['source'],
                        'url' => $article['url'],
                        'snippet' => $article['snippet'],
                    ], $item['articles']),
                ], $data['default']['trendingSearchesDays'][0]['trendingSearches']);
            }

            return [
                'trendingSearches' => $trendingSearches,
                'geo' => $geo,
                'category' => $this->getCategoryId($category),
            ];
        } catch (\Exception $e) {
            return [
                'trendingSearches' => [],
                'geo' => $geo,
                'category' => 0,
            ];
        }
    }

    /**
     * Get daily trending searches.
     *
     * @param string $geo  Geographic location
     * @param string $date Date (YYYY-MM-DD)
     *
     * @return array{
     *     trendingSearches: array<int, array{
     *         query: string,
     *         exploreLink: string,
     *     }>,
     *     geo: string,
     *     date: string,
     * }
     */
    public function getDailyTrendingSearches(
        string $geo = 'US',
        string $date = '',
    ): array {
        try {
            if (!$date) {
                $date = date('Y-m-d');
            }

            $params = [
                'hl' => 'en',
                'tz' => '-480',
                'geo' => $geo,
                'ns' => 15,
                'date' => $date,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/dailytrends", [
                'query' => array_merge($this->options, $params),
            ]);

            $content = $response->getContent();
            $content = substr($content, 4);
            $data = json_decode($content, true);

            $trendingSearches = [];
            if (isset($data['default']['trendingSearchesDays'][0]['trendingSearches'])) {
                $trendingSearches = array_map(fn ($item) => [
                    'query' => $item['title']['query'],
                    'exploreLink' => $item['title']['exploreLink'],
                ], $data['default']['trendingSearchesDays'][0]['trendingSearches']);
            }

            return [
                'trendingSearches' => $trendingSearches,
                'geo' => $geo,
                'date' => $date,
            ];
        } catch (\Exception $e) {
            return [
                'trendingSearches' => [],
                'geo' => $geo,
                'date' => $date,
            ];
        }
    }

    /**
     * Build time parameter for Google Trends API.
     */
    private function buildTimeParam(string $startDate, string $endDate): string
    {
        if (!$startDate || !$endDate) {
            return date('Y-m-d').' '.date('Y-m-d');
        }

        return "{$startDate} {$endDate}";
    }

    /**
     * Get category ID from category name.
     */
    private function getCategoryId(string $category): int
    {
        $categories = [
            'all' => 0,
            'business' => 7,
            'entertainment' => 3,
            'health' => 45,
            'sports' => 20,
            'technology' => 5,
            'science' => 174,
            'news' => 16,
        ];

        return $categories[strtolower($category)] ?? 0;
    }
}
