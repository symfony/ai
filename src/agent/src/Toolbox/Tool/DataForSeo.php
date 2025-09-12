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
#[AsTool('dataforseo_search', 'Tool that performs SEO data analysis using DataForSeo')]
#[AsTool('dataforseo_keyword_analysis', 'Tool that analyzes keywords', method: 'keywordAnalysis')]
#[AsTool('dataforseo_serp_analysis', 'Tool that analyzes SERP data', method: 'serpAnalysis')]
#[AsTool('dataforseo_competitor_analysis', 'Tool that analyzes competitors', method: 'competitorAnalysis')]
#[AsTool('dataforseo_backlink_analysis', 'Tool that analyzes backlinks', method: 'backlinkAnalysis')]
#[AsTool('dataforseo_content_analysis', 'Tool that analyzes content', method: 'contentAnalysis')]
#[AsTool('dataforseo_rank_tracking', 'Tool that tracks rankings', method: 'rankTracking')]
#[AsTool('dataforseo_technical_seo', 'Tool that performs technical SEO analysis', method: 'technicalSeo')]
final readonly class DataForSeo
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.dataforseo.com/v3',
        private array $options = [],
    ) {
    }

    /**
     * Perform SEO data analysis using DataForSeo.
     *
     * @param string $query        Search query
     * @param string $location     Search location
     * @param string $language     Search language
     * @param string $device       Device type (desktop, mobile)
     * @param string $searchEngine Search engine (google, bing, yahoo)
     *
     * @return array{
     *     success: bool,
     *     search_data: array{
     *         query: string,
     *         location: string,
     *         language: string,
     *         device: string,
     *         search_engine: string,
     *         results: array<int, array{
     *             position: int,
     *             title: string,
     *             url: string,
     *             description: string,
     *             domain: string,
     *             featured_snippet: bool,
     *             related_searches: array<int, string>,
     *         }>,
     *         ads: array<int, array{
     *             position: int,
     *             title: string,
     *             url: string,
     *             description: string,
     *             domain: string,
     *         }>,
     *         related_searches: array<int, string>,
     *         total_results: int,
     *         search_time: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        string $location = 'United States',
        string $language = 'en',
        string $device = 'desktop',
        string $searchEngine = 'google',
    ): array {
        try {
            $requestData = [
                'keyword' => $query,
                'location_name' => $location,
                'language_name' => $language,
                'device' => $device,
                'os' => 'windows',
            ];

            $endpoint = 'bing' === $searchEngine ? 'serp/google/organic/live/regular' : 'serp/google/organic/live/regular';

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/{$endpoint}", [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [$requestData],
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['tasks'][0]['result'][0] ?? [];

            return [
                'success' => true,
                'search_data' => [
                    'query' => $query,
                    'location' => $location,
                    'language' => $language,
                    'device' => $device,
                    'search_engine' => $searchEngine,
                    'results' => array_map(fn ($item, $index) => [
                        'position' => $index + 1,
                        'title' => $item['title'] ?? '',
                        'url' => $item['url'] ?? '',
                        'description' => $item['description'] ?? '',
                        'domain' => parse_url($item['url'] ?? '', \PHP_URL_HOST) ?: '',
                        'featured_snippet' => $item['featured_snippet'] ?? false,
                        'related_searches' => $item['related_searches'] ?? [],
                    ], $result['items'] ?? []),
                    'ads' => array_map(fn ($ad, $index) => [
                        'position' => $index + 1,
                        'title' => $ad['title'] ?? '',
                        'url' => $ad['url'] ?? '',
                        'description' => $ad['description'] ?? '',
                        'domain' => parse_url($ad['url'] ?? '', \PHP_URL_HOST) ?: '',
                    ], $result['ads'] ?? []),
                    'related_searches' => $result['related_searches'] ?? [],
                    'total_results' => $result['total_results'] ?? 0,
                    'search_time' => $result['search_time'] ?? 0.0,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'search_data' => [
                    'query' => $query,
                    'location' => $location,
                    'language' => $language,
                    'device' => $device,
                    'search_engine' => $searchEngine,
                    'results' => [],
                    'ads' => [],
                    'related_searches' => [],
                    'total_results' => 0,
                    'search_time' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze keywords.
     *
     * @param string               $keyword  Keyword to analyze
     * @param string               $location Search location
     * @param string               $language Search language
     * @param array<string, mixed> $metrics  Metrics to retrieve
     *
     * @return array{
     *     success: bool,
     *     keyword_analysis: array{
     *         keyword: string,
     *         location: string,
     *         language: string,
     *         search_volume: int,
     *         competition: string,
     *         competition_level: float,
     *         cpc: float,
     *         trends: array<int, array{
     *             month: string,
     *             search_volume: int,
     *         }>,
     *         related_keywords: array<int, array{
     *             keyword: string,
     *             search_volume: int,
     *             competition: string,
     *             cpc: float,
     *         }>,
     *         keyword_difficulty: float,
     *         intent: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function keywordAnalysis(
        string $keyword,
        string $location = 'United States',
        string $language = 'en',
        array $metrics = ['search_volume', 'competition', 'cpc', 'trends'],
    ): array {
        try {
            $requestData = [
                'keyword' => $keyword,
                'location_name' => $location,
                'language_name' => $language,
                'metrics' => $metrics,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/dataforseo_labs/google/keyword_ideas/live", [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [$requestData],
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['tasks'][0]['result'][0] ?? [];

            return [
                'success' => true,
                'keyword_analysis' => [
                    'keyword' => $keyword,
                    'location' => $location,
                    'language' => $language,
                    'search_volume' => $result['search_volume'] ?? 0,
                    'competition' => $result['competition'] ?? 'unknown',
                    'competition_level' => $result['competition_level'] ?? 0.0,
                    'cpc' => $result['cpc'] ?? 0.0,
                    'trends' => array_map(fn ($trend) => [
                        'month' => $trend['month'] ?? '',
                        'search_volume' => $trend['search_volume'] ?? 0,
                    ], $result['trends'] ?? []),
                    'related_keywords' => array_map(fn ($related) => [
                        'keyword' => $related['keyword'] ?? '',
                        'search_volume' => $related['search_volume'] ?? 0,
                        'competition' => $related['competition'] ?? 'unknown',
                        'cpc' => $related['cpc'] ?? 0.0,
                    ], $result['related_keywords'] ?? []),
                    'keyword_difficulty' => $result['keyword_difficulty'] ?? 0.0,
                    'intent' => $result['intent'] ?? 'unknown',
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'keyword_analysis' => [
                    'keyword' => $keyword,
                    'location' => $location,
                    'language' => $language,
                    'search_volume' => 0,
                    'competition' => 'unknown',
                    'competition_level' => 0.0,
                    'cpc' => 0.0,
                    'trends' => [],
                    'related_keywords' => [],
                    'keyword_difficulty' => 0.0,
                    'intent' => 'unknown',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze SERP data.
     *
     * @param string $keyword  Keyword to analyze
     * @param string $location Search location
     * @param string $device   Device type
     * @param int    $depth    Analysis depth
     *
     * @return array{
     *     success: bool,
     *     serp_analysis: array{
     *         keyword: string,
     *         location: string,
     *         device: string,
     *         organic_results: array<int, array{
     *             position: int,
     *             title: string,
     *             url: string,
     *             description: string,
     *             domain: string,
     *             domain_rating: float,
     *             page_rating: float,
     *             backlinks: int,
     *         }>,
     *         featured_snippets: array<int, array{
     *             title: string,
     *             content: string,
     *             url: string,
     *         }>,
     *         people_also_ask: array<int, string>,
     *         related_searches: array<int, string>,
     *         local_pack: array<int, array{
     *             name: string,
     *             address: string,
     *             rating: float,
     *             reviews: int,
     *         }>,
     *         analysis_summary: array{
     *             total_results: int,
     *             avg_domain_rating: float,
     *             content_types: array<string, int>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function serpAnalysis(
        string $keyword,
        string $location = 'United States',
        string $device = 'desktop',
        int $depth = 10,
    ): array {
        try {
            $requestData = [
                'keyword' => $keyword,
                'location_name' => $location,
                'device' => $device,
                'depth' => $depth,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/dataforseo_labs/google/ranked_keywords/live", [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [$requestData],
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['tasks'][0]['result'][0] ?? [];

            return [
                'success' => true,
                'serp_analysis' => [
                    'keyword' => $keyword,
                    'location' => $location,
                    'device' => $device,
                    'organic_results' => array_map(fn ($item, $index) => [
                        'position' => $index + 1,
                        'title' => $item['title'] ?? '',
                        'url' => $item['url'] ?? '',
                        'description' => $item['description'] ?? '',
                        'domain' => parse_url($item['url'] ?? '', \PHP_URL_HOST) ?: '',
                        'domain_rating' => $item['domain_rating'] ?? 0.0,
                        'page_rating' => $item['page_rating'] ?? 0.0,
                        'backlinks' => $item['backlinks'] ?? 0,
                    ], $result['organic_results'] ?? []),
                    'featured_snippets' => array_map(fn ($snippet) => [
                        'title' => $snippet['title'] ?? '',
                        'content' => $snippet['content'] ?? '',
                        'url' => $snippet['url'] ?? '',
                    ], $result['featured_snippets'] ?? []),
                    'people_also_ask' => $result['people_also_ask'] ?? [],
                    'related_searches' => $result['related_searches'] ?? [],
                    'local_pack' => array_map(fn ($local) => [
                        'name' => $local['name'] ?? '',
                        'address' => $local['address'] ?? '',
                        'rating' => $local['rating'] ?? 0.0,
                        'reviews' => $local['reviews'] ?? 0,
                    ], $result['local_pack'] ?? []),
                    'analysis_summary' => [
                        'total_results' => \count($result['organic_results'] ?? []),
                        'avg_domain_rating' => $result['avg_domain_rating'] ?? 0.0,
                        'content_types' => $result['content_types'] ?? [],
                    ],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'serp_analysis' => [
                    'keyword' => $keyword,
                    'location' => $location,
                    'device' => $device,
                    'organic_results' => [],
                    'featured_snippets' => [],
                    'people_also_ask' => [],
                    'related_searches' => [],
                    'local_pack' => [],
                    'analysis_summary' => [
                        'total_results' => 0,
                        'avg_domain_rating' => 0.0,
                        'content_types' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze competitors.
     *
     * @param string               $domain        Domain to analyze
     * @param array<string, mixed> $analysisTypes Types of analysis to perform
     * @param int                  $limit         Number of competitors to analyze
     *
     * @return array{
     *     success: bool,
     *     competitor_analysis: array{
     *         domain: string,
     *         competitors: array<int, array{
     *             domain: string,
     *             common_keywords: int,
     *             traffic_share: float,
     *             domain_rating: float,
     *             backlinks: int,
     *             organic_traffic: int,
     *             top_keywords: array<int, string>,
     *         }>,
     *         market_share: array{
     *             total_keywords: int,
     *             shared_keywords: int,
     *             unique_keywords: int,
     *         },
     *         analysis_summary: array{
     *             top_competitors: array<int, string>,
     *             market_opportunities: array<int, string>,
     *             competitive_gaps: array<int, string>,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function competitorAnalysis(
        string $domain,
        array $analysisTypes = ['keywords', 'backlinks', 'traffic'],
        int $limit = 10,
    ): array {
        try {
            $requestData = [
                'target' => $domain,
                'analysis_types' => $analysisTypes,
                'limit' => max(1, min($limit, 50)),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/dataforseo_labs/google/competitors_domain/live", [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [$requestData],
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['tasks'][0]['result'][0] ?? [];

            return [
                'success' => true,
                'competitor_analysis' => [
                    'domain' => $domain,
                    'competitors' => array_map(fn ($competitor) => [
                        'domain' => $competitor['domain'] ?? '',
                        'common_keywords' => $competitor['common_keywords'] ?? 0,
                        'traffic_share' => $competitor['traffic_share'] ?? 0.0,
                        'domain_rating' => $competitor['domain_rating'] ?? 0.0,
                        'backlinks' => $competitor['backlinks'] ?? 0,
                        'organic_traffic' => $competitor['organic_traffic'] ?? 0,
                        'top_keywords' => $competitor['top_keywords'] ?? [],
                    ], $result['competitors'] ?? []),
                    'market_share' => [
                        'total_keywords' => $result['market_share']['total_keywords'] ?? 0,
                        'shared_keywords' => $result['market_share']['shared_keywords'] ?? 0,
                        'unique_keywords' => $result['market_share']['unique_keywords'] ?? 0,
                    ],
                    'analysis_summary' => [
                        'top_competitors' => $result['analysis_summary']['top_competitors'] ?? [],
                        'market_opportunities' => $result['analysis_summary']['market_opportunities'] ?? [],
                        'competitive_gaps' => $result['analysis_summary']['competitive_gaps'] ?? [],
                    ],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'competitor_analysis' => [
                    'domain' => $domain,
                    'competitors' => [],
                    'market_share' => [
                        'total_keywords' => 0,
                        'shared_keywords' => 0,
                        'unique_keywords' => 0,
                    ],
                    'analysis_summary' => [
                        'top_competitors' => [],
                        'market_opportunities' => [],
                        'competitive_gaps' => [],
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze backlinks.
     *
     * @param string               $domain  Domain to analyze
     * @param array<string, mixed> $filters Backlink filters
     * @param int                  $limit   Number of backlinks to analyze
     *
     * @return array{
     *     success: bool,
     *     backlink_analysis: array{
     *         domain: string,
     *         total_backlinks: int,
     *         referring_domains: int,
     *         domain_rating: float,
     *         backlinks: array<int, array{
     *             url: string,
     *             domain: string,
     *             anchor_text: string,
     *             link_type: string,
     *             domain_rating: float,
     *             page_rating: float,
     *             first_seen: string,
     *             last_seen: string,
     *         }>,
     *         link_distribution: array{
     *             dofollow: int,
     *             nofollow: int,
     *             image: int,
     *             text: int,
     *         },
     *         top_referring_domains: array<int, array{
     *             domain: string,
     *             backlinks_count: int,
     *             domain_rating: float,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function backlinkAnalysis(
        string $domain,
        array $filters = [],
        int $limit = 100,
    ): array {
        try {
            $requestData = [
                'target' => $domain,
                'filters' => $filters,
                'limit' => max(1, min($limit, 1000)),
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/backlinks/summary/live", [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [$requestData],
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['tasks'][0]['result'][0] ?? [];

            return [
                'success' => true,
                'backlink_analysis' => [
                    'domain' => $domain,
                    'total_backlinks' => $result['total_backlinks'] ?? 0,
                    'referring_domains' => $result['referring_domains'] ?? 0,
                    'domain_rating' => $result['domain_rating'] ?? 0.0,
                    'backlinks' => array_map(fn ($backlink) => [
                        'url' => $backlink['url'] ?? '',
                        'domain' => $backlink['domain'] ?? '',
                        'anchor_text' => $backlink['anchor_text'] ?? '',
                        'link_type' => $backlink['link_type'] ?? '',
                        'domain_rating' => $backlink['domain_rating'] ?? 0.0,
                        'page_rating' => $backlink['page_rating'] ?? 0.0,
                        'first_seen' => $backlink['first_seen'] ?? '',
                        'last_seen' => $backlink['last_seen'] ?? '',
                    ], $result['backlinks'] ?? []),
                    'link_distribution' => [
                        'dofollow' => $result['link_distribution']['dofollow'] ?? 0,
                        'nofollow' => $result['link_distribution']['nofollow'] ?? 0,
                        'image' => $result['link_distribution']['image'] ?? 0,
                        'text' => $result['link_distribution']['text'] ?? 0,
                    ],
                    'top_referring_domains' => array_map(fn ($domain) => [
                        'domain' => $domain['domain'] ?? '',
                        'backlinks_count' => $domain['backlinks_count'] ?? 0,
                        'domain_rating' => $domain['domain_rating'] ?? 0.0,
                    ], $result['top_referring_domains'] ?? []),
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'backlink_analysis' => [
                    'domain' => $domain,
                    'total_backlinks' => 0,
                    'referring_domains' => 0,
                    'domain_rating' => 0.0,
                    'backlinks' => [],
                    'link_distribution' => [
                        'dofollow' => 0,
                        'nofollow' => 0,
                        'image' => 0,
                        'text' => 0,
                    ],
                    'top_referring_domains' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze content.
     *
     * @param string               $url           URL to analyze
     * @param array<string, mixed> $analysisTypes Types of content analysis
     *
     * @return array{
     *     success: bool,
     *     content_analysis: array{
     *         url: string,
     *         title: string,
     *         meta_description: string,
     *         word_count: int,
     *         readability_score: float,
     *         headings: array{
     *             h1: array<int, string>,
     *             h2: array<int, string>,
     *             h3: array<int, string>,
     *         },
     *         keywords: array<int, array{
     *             keyword: string,
     *             density: float,
     *             position: int,
     *         }>,
     *         images: array<int, array{
     *             src: string,
     *             alt: string,
     *             title: string,
     *         }>,
     *         internal_links: int,
     *         external_links: int,
     *         seo_score: float,
     *         recommendations: array<int, string>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function contentAnalysis(
        string $url,
        array $analysisTypes = ['seo', 'readability', 'keywords', 'images'],
    ): array {
        try {
            $requestData = [
                'url' => $url,
                'analysis_types' => $analysisTypes,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/on_page/content_analysis", [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [$requestData],
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['tasks'][0]['result'][0] ?? [];

            return [
                'success' => true,
                'content_analysis' => [
                    'url' => $url,
                    'title' => $result['title'] ?? '',
                    'meta_description' => $result['meta_description'] ?? '',
                    'word_count' => $result['word_count'] ?? 0,
                    'readability_score' => $result['readability_score'] ?? 0.0,
                    'headings' => [
                        'h1' => $result['headings']['h1'] ?? [],
                        'h2' => $result['headings']['h2'] ?? [],
                        'h3' => $result['headings']['h3'] ?? [],
                    ],
                    'keywords' => array_map(fn ($keyword) => [
                        'keyword' => $keyword['keyword'] ?? '',
                        'density' => $keyword['density'] ?? 0.0,
                        'position' => $keyword['position'] ?? 0,
                    ], $result['keywords'] ?? []),
                    'images' => array_map(fn ($image) => [
                        'src' => $image['src'] ?? '',
                        'alt' => $image['alt'] ?? '',
                        'title' => $image['title'] ?? '',
                    ], $result['images'] ?? []),
                    'internal_links' => $result['internal_links'] ?? 0,
                    'external_links' => $result['external_links'] ?? 0,
                    'seo_score' => $result['seo_score'] ?? 0.0,
                    'recommendations' => $result['recommendations'] ?? [],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'content_analysis' => [
                    'url' => $url,
                    'title' => '',
                    'meta_description' => '',
                    'word_count' => 0,
                    'readability_score' => 0.0,
                    'headings' => [
                        'h1' => [],
                        'h2' => [],
                        'h3' => [],
                    ],
                    'keywords' => [],
                    'images' => [],
                    'internal_links' => 0,
                    'external_links' => 0,
                    'seo_score' => 0.0,
                    'recommendations' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Track rankings.
     *
     * @param string $keyword  Keyword to track
     * @param string $domain   Domain to track
     * @param string $location Search location
     * @param string $device   Device type
     *
     * @return array{
     *     success: bool,
     *     rank_tracking: array{
     *         keyword: string,
     *         domain: string,
     *         location: string,
     *         device: string,
     *         current_position: int,
     *         previous_position: int,
     *         position_change: int,
     *         url: string,
     *         tracking_history: array<int, array{
     *             date: string,
     *             position: int,
     *             url: string,
     *         }>,
     *         competitors: array<int, array{
     *             domain: string,
     *             position: int,
     *             url: string,
     *         }>,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function rankTracking(
        string $keyword,
        string $domain,
        string $location = 'United States',
        string $device = 'desktop',
    ): array {
        try {
            $requestData = [
                'keyword' => $keyword,
                'target' => $domain,
                'location_name' => $location,
                'device' => $device,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/dataforseo_labs/google/ranked_keywords/live", [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [$requestData],
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['tasks'][0]['result'][0] ?? [];

            return [
                'success' => true,
                'rank_tracking' => [
                    'keyword' => $keyword,
                    'domain' => $domain,
                    'location' => $location,
                    'device' => $device,
                    'current_position' => $result['current_position'] ?? 0,
                    'previous_position' => $result['previous_position'] ?? 0,
                    'position_change' => ($result['current_position'] ?? 0) - ($result['previous_position'] ?? 0),
                    'url' => $result['url'] ?? '',
                    'tracking_history' => array_map(fn ($history) => [
                        'date' => $history['date'] ?? '',
                        'position' => $history['position'] ?? 0,
                        'url' => $history['url'] ?? '',
                    ], $result['tracking_history'] ?? []),
                    'competitors' => array_map(fn ($competitor) => [
                        'domain' => $competitor['domain'] ?? '',
                        'position' => $competitor['position'] ?? 0,
                        'url' => $competitor['url'] ?? '',
                    ], $result['competitors'] ?? []),
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'rank_tracking' => [
                    'keyword' => $keyword,
                    'domain' => $domain,
                    'location' => $location,
                    'device' => $device,
                    'current_position' => 0,
                    'previous_position' => 0,
                    'position_change' => 0,
                    'url' => '',
                    'tracking_history' => [],
                    'competitors' => [],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform technical SEO analysis.
     *
     * @param string               $url    URL to analyze
     * @param array<string, mixed> $checks Technical checks to perform
     *
     * @return array{
     *     success: bool,
     *     technical_seo: array{
     *         url: string,
     *         page_speed: array{
     *             mobile_score: float,
     *             desktop_score: float,
     *             load_time: float,
     *             recommendations: array<int, string>,
     *         },
     *         mobile_friendliness: bool,
     *         structured_data: array{
     *             present: bool,
     *             types: array<int, string>,
     *             errors: array<int, string>,
     *         },
     *         meta_tags: array{
     *             title_length: int,
     *             description_length: int,
     *             missing_tags: array<int, string>,
     *         },
     *         technical_issues: array<int, array{
     *             type: string,
     *             severity: string,
     *             description: string,
     *             recommendation: string,
     *         }>,
     *         overall_score: float,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function technicalSeo(
        string $url,
        array $checks = ['page_speed', 'mobile_friendliness', 'structured_data', 'meta_tags'],
    ): array {
        try {
            $requestData = [
                'url' => $url,
                'checks' => $checks,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/on_page/technical_analysis", [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->apiKey.':'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [$requestData],
            ] + $this->options);

            $data = $response->toArray();
            $result = $data['tasks'][0]['result'][0] ?? [];

            return [
                'success' => true,
                'technical_seo' => [
                    'url' => $url,
                    'page_speed' => [
                        'mobile_score' => $result['page_speed']['mobile_score'] ?? 0.0,
                        'desktop_score' => $result['page_speed']['desktop_score'] ?? 0.0,
                        'load_time' => $result['page_speed']['load_time'] ?? 0.0,
                        'recommendations' => $result['page_speed']['recommendations'] ?? [],
                    ],
                    'mobile_friendliness' => $result['mobile_friendliness'] ?? false,
                    'structured_data' => [
                        'present' => $result['structured_data']['present'] ?? false,
                        'types' => $result['structured_data']['types'] ?? [],
                        'errors' => $result['structured_data']['errors'] ?? [],
                    ],
                    'meta_tags' => [
                        'title_length' => $result['meta_tags']['title_length'] ?? 0,
                        'description_length' => $result['meta_tags']['description_length'] ?? 0,
                        'missing_tags' => $result['meta_tags']['missing_tags'] ?? [],
                    ],
                    'technical_issues' => array_map(fn ($issue) => [
                        'type' => $issue['type'] ?? '',
                        'severity' => $issue['severity'] ?? 'low',
                        'description' => $issue['description'] ?? '',
                        'recommendation' => $issue['recommendation'] ?? '',
                    ], $result['technical_issues'] ?? []),
                    'overall_score' => $result['overall_score'] ?? 0.0,
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'technical_seo' => [
                    'url' => $url,
                    'page_speed' => [
                        'mobile_score' => 0.0,
                        'desktop_score' => 0.0,
                        'load_time' => 0.0,
                        'recommendations' => [],
                    ],
                    'mobile_friendliness' => false,
                    'structured_data' => [
                        'present' => false,
                        'types' => [],
                        'errors' => [],
                    ],
                    'meta_tags' => [
                        'title_length' => 0,
                        'description_length' => 0,
                        'missing_tags' => [],
                    ],
                    'technical_issues' => [],
                    'overall_score' => 0.0,
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
