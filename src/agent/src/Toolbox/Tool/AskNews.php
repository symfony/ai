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
#[AsTool('asknews_search', 'Tool that searches for news using AskNews API')]
#[AsTool('asknews_get_trending', 'Tool that gets trending news topics', method: 'getTrending')]
#[AsTool('asknews_get_headlines', 'Tool that gets news headlines', method: 'getHeadlines')]
#[AsTool('asknews_get_article', 'Tool that gets full article content', method: 'getArticle')]
#[AsTool('asknews_get_sources', 'Tool that gets news sources', method: 'getSources')]
#[AsTool('asknews_get_categories', 'Tool that gets news categories', method: 'getCategories')]
#[AsTool('asknews_get_sentiment', 'Tool that gets news sentiment analysis', method: 'getSentiment')]
#[AsTool('asknews_get_summary', 'Tool that gets news summary', method: 'getSummary')]
final readonly class AskNews
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.asknews.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Search for news using AskNews API.
     *
     * @param string $query     Search query
     * @param string $language  Language code (en, es, fr, de, etc.)
     * @param string $country   Country code (us, gb, ca, etc.)
     * @param string $category  News category
     * @param string $sortBy    Sort by (publishedAt, relevance, popularity)
     * @param string $timeframe Timeframe (hour, day, week, month, year)
     * @param int    $limit     Number of results
     * @param int    $offset    Offset for pagination
     *
     * @return array{
     *     success: bool,
     *     articles: array<int, array{
     *         id: string,
     *         title: string,
     *         description: string,
     *         content: string,
     *         url: string,
     *         imageUrl: string,
     *         publishedAt: string,
     *         source: array{
     *             id: string,
     *             name: string,
     *             url: string,
     *             category: string,
     *         },
     *         author: string,
     *         language: string,
     *         country: string,
     *         category: string,
     *         tags: array<int, string>,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *         },
     *         readTime: int,
     *         wordCount: int,
     *         shares: int,
     *         engagement: float,
     *     }>,
     *     totalResults: int,
     *     query: string,
     *     searchMetadata: array{
     *         searchId: string,
     *         totalResults: int,
     *         searchTime: float,
     *         query: string,
     *         filters: array<string, mixed>,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        string $language = 'en',
        string $country = 'us',
        string $category = '',
        string $sortBy = 'publishedAt',
        string $timeframe = 'week',
        int $limit = 20,
        int $offset = 0,
    ): array {
        try {
            $requestData = [
                'q' => $query,
                'lang' => $language,
                'country' => $country,
                'sortBy' => $sortBy,
                'timeframe' => $timeframe,
                'limit' => max(1, min($limit, 100)),
                'offset' => max(0, $offset),
            ];

            if ($category) {
                $requestData['category'] = $category;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/search", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $requestData),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'articles' => array_map(fn ($article) => [
                    'id' => $article['id'] ?? '',
                    'title' => $article['title'] ?? '',
                    'description' => $article['description'] ?? '',
                    'content' => $article['content'] ?? '',
                    'url' => $article['url'] ?? '',
                    'imageUrl' => $article['imageUrl'] ?? '',
                    'publishedAt' => $article['publishedAt'] ?? '',
                    'source' => [
                        'id' => $article['source']['id'] ?? '',
                        'name' => $article['source']['name'] ?? '',
                        'url' => $article['source']['url'] ?? '',
                        'category' => $article['source']['category'] ?? '',
                    ],
                    'author' => $article['author'] ?? '',
                    'language' => $article['language'] ?? $language,
                    'country' => $article['country'] ?? $country,
                    'category' => $article['category'] ?? $category,
                    'tags' => $article['tags'] ?? [],
                    'sentiment' => [
                        'score' => $article['sentiment']['score'] ?? 0.0,
                        'label' => $article['sentiment']['label'] ?? 'neutral',
                    ],
                    'readTime' => $article['readTime'] ?? 0,
                    'wordCount' => $article['wordCount'] ?? 0,
                    'shares' => $article['shares'] ?? 0,
                    'engagement' => $article['engagement'] ?? 0.0,
                ], $data['articles'] ?? []),
                'totalResults' => $data['totalResults'] ?? 0,
                'query' => $query,
                'searchMetadata' => [
                    'searchId' => $data['searchId'] ?? '',
                    'totalResults' => $data['totalResults'] ?? 0,
                    'searchTime' => $data['searchTime'] ?? 0.0,
                    'query' => $query,
                    'filters' => [
                        'language' => $language,
                        'country' => $country,
                        'category' => $category,
                        'sortBy' => $sortBy,
                        'timeframe' => $timeframe,
                    ],
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'articles' => [],
                'totalResults' => 0,
                'query' => $query,
                'searchMetadata' => [
                    'searchId' => '',
                    'totalResults' => 0,
                    'searchTime' => 0.0,
                    'query' => $query,
                    'filters' => [
                        'language' => $language,
                        'country' => $country,
                        'category' => $category,
                        'sortBy' => $sortBy,
                        'timeframe' => $timeframe,
                    ],
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get trending news topics.
     *
     * @param string $language  Language code
     * @param string $country   Country code
     * @param string $category  News category
     * @param int    $limit     Number of trending topics
     * @param string $timeframe Timeframe for trending calculation
     *
     * @return array{
     *     success: bool,
     *     trending: array<int, array{
     *         topic: string,
     *         query: string,
     *         articleCount: int,
     *         trendScore: float,
     *         growthRate: float,
     *         category: string,
     *         topArticles: array<int, array{
     *             id: string,
     *             title: string,
     *             url: string,
     *             publishedAt: string,
     *             source: string,
     *         }>,
     *         relatedTopics: array<int, string>,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *         },
     *     }>,
     *     totalTrending: int,
     *     timeframe: string,
     *     error: string,
     * }
     */
    public function getTrending(
        string $language = 'en',
        string $country = 'us',
        string $category = '',
        int $limit = 20,
        string $timeframe = 'day',
    ): array {
        try {
            $requestData = [
                'lang' => $language,
                'country' => $country,
                'limit' => max(1, min($limit, 100)),
                'timeframe' => $timeframe,
            ];

            if ($category) {
                $requestData['category'] = $category;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/trending", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $requestData),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'trending' => array_map(fn ($trend) => [
                    'topic' => $trend['topic'] ?? '',
                    'query' => $trend['query'] ?? '',
                    'articleCount' => $trend['articleCount'] ?? 0,
                    'trendScore' => $trend['trendScore'] ?? 0.0,
                    'growthRate' => $trend['growthRate'] ?? 0.0,
                    'category' => $trend['category'] ?? $category,
                    'topArticles' => array_map(fn ($article) => [
                        'id' => $article['id'] ?? '',
                        'title' => $article['title'] ?? '',
                        'url' => $article['url'] ?? '',
                        'publishedAt' => $article['publishedAt'] ?? '',
                        'source' => $article['source'] ?? '',
                    ], $trend['topArticles'] ?? []),
                    'relatedTopics' => $trend['relatedTopics'] ?? [],
                    'sentiment' => [
                        'score' => $trend['sentiment']['score'] ?? 0.0,
                        'label' => $trend['sentiment']['label'] ?? 'neutral',
                    ],
                ], $data['trending'] ?? []),
                'totalTrending' => $data['totalTrending'] ?? 0,
                'timeframe' => $timeframe,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'trending' => [],
                'totalTrending' => 0,
                'timeframe' => $timeframe,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get news headlines.
     *
     * @param string $category News category
     * @param string $language Language code
     * @param string $country  Country code
     * @param int    $limit    Number of headlines
     *
     * @return array{
     *     success: bool,
     *     headlines: array<int, array{
     *         id: string,
     *         title: string,
     *         description: string,
     *         url: string,
     *         imageUrl: string,
     *         publishedAt: string,
     *         source: string,
     *         category: string,
     *         priority: string,
     *         breaking: bool,
     *     }>,
     *     category: string,
     *     totalHeadlines: int,
     *     error: string,
     * }
     */
    public function getHeadlines(
        string $category = 'general',
        string $language = 'en',
        string $country = 'us',
        int $limit = 20,
    ): array {
        try {
            $requestData = [
                'category' => $category,
                'lang' => $language,
                'country' => $country,
                'limit' => max(1, min($limit, 100)),
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/headlines", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $requestData),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'headlines' => array_map(fn ($headline) => [
                    'id' => $headline['id'] ?? '',
                    'title' => $headline['title'] ?? '',
                    'description' => $headline['description'] ?? '',
                    'url' => $headline['url'] ?? '',
                    'imageUrl' => $headline['imageUrl'] ?? '',
                    'publishedAt' => $headline['publishedAt'] ?? '',
                    'source' => $headline['source'] ?? '',
                    'category' => $headline['category'] ?? $category,
                    'priority' => $headline['priority'] ?? 'normal',
                    'breaking' => $headline['breaking'] ?? false,
                ], $data['headlines'] ?? []),
                'category' => $category,
                'totalHeadlines' => $data['totalHeadlines'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'headlines' => [],
                'category' => $category,
                'totalHeadlines' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get full article content.
     *
     * @param string $articleId     Article ID
     * @param bool   $includeImages Include images in content
     * @param bool   $includeVideos Include videos in content
     *
     * @return array{
     *     success: bool,
     *     article: array{
     *         id: string,
     *         title: string,
     *         description: string,
     *         content: string,
     *         url: string,
     *         imageUrl: string,
     *         publishedAt: string,
     *         updatedAt: string,
     *         source: array{
     *             id: string,
     *             name: string,
     *             url: string,
     *             category: string,
     *         },
     *         author: string,
     *         language: string,
     *         country: string,
     *         category: string,
     *         tags: array<int, string>,
     *         sentiment: array{
     *             score: float,
     *             label: string,
     *         },
     *         readTime: int,
     *         wordCount: int,
     *         shares: int,
     *         engagement: float,
     *         images: array<int, array{
     *             url: string,
     *             caption: string,
     *             alt: string,
     *         }>,
     *         videos: array<int, array{
     *             url: string,
     *             title: string,
     *             duration: int,
     *         }>,
     *         relatedArticles: array<int, array{
     *             id: string,
     *             title: string,
     *             url: string,
     *             publishedAt: string,
     *         }>,
     *     },
     *     error: string,
     * }
     */
    public function getArticle(
        string $articleId,
        bool $includeImages = true,
        bool $includeVideos = true,
    ): array {
        try {
            $requestData = [
                'includeImages' => $includeImages,
                'includeVideos' => $includeVideos,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/articles/{$articleId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $requestData),
            ]);

            $data = $response->toArray();
            $article = $data['article'] ?? [];

            return [
                'success' => true,
                'article' => [
                    'id' => $article['id'] ?? $articleId,
                    'title' => $article['title'] ?? '',
                    'description' => $article['description'] ?? '',
                    'content' => $article['content'] ?? '',
                    'url' => $article['url'] ?? '',
                    'imageUrl' => $article['imageUrl'] ?? '',
                    'publishedAt' => $article['publishedAt'] ?? '',
                    'updatedAt' => $article['updatedAt'] ?? '',
                    'source' => [
                        'id' => $article['source']['id'] ?? '',
                        'name' => $article['source']['name'] ?? '',
                        'url' => $article['source']['url'] ?? '',
                        'category' => $article['source']['category'] ?? '',
                    ],
                    'author' => $article['author'] ?? '',
                    'language' => $article['language'] ?? '',
                    'country' => $article['country'] ?? '',
                    'category' => $article['category'] ?? '',
                    'tags' => $article['tags'] ?? [],
                    'sentiment' => [
                        'score' => $article['sentiment']['score'] ?? 0.0,
                        'label' => $article['sentiment']['label'] ?? 'neutral',
                    ],
                    'readTime' => $article['readTime'] ?? 0,
                    'wordCount' => $article['wordCount'] ?? 0,
                    'shares' => $article['shares'] ?? 0,
                    'engagement' => $article['engagement'] ?? 0.0,
                    'images' => array_map(fn ($image) => [
                        'url' => $image['url'] ?? '',
                        'caption' => $image['caption'] ?? '',
                        'alt' => $image['alt'] ?? '',
                    ], $article['images'] ?? []),
                    'videos' => array_map(fn ($video) => [
                        'url' => $video['url'] ?? '',
                        'title' => $video['title'] ?? '',
                        'duration' => $video['duration'] ?? 0,
                    ], $article['videos'] ?? []),
                    'relatedArticles' => array_map(fn ($related) => [
                        'id' => $related['id'] ?? '',
                        'title' => $related['title'] ?? '',
                        'url' => $related['url'] ?? '',
                        'publishedAt' => $related['publishedAt'] ?? '',
                    ], $article['relatedArticles'] ?? []),
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'article' => [
                    'id' => $articleId,
                    'title' => '',
                    'description' => '',
                    'content' => '',
                    'url' => '',
                    'imageUrl' => '',
                    'publishedAt' => '',
                    'updatedAt' => '',
                    'source' => ['id' => '', 'name' => '', 'url' => '', 'category' => ''],
                    'author' => '',
                    'language' => '',
                    'country' => '',
                    'category' => '',
                    'tags' => [],
                    'sentiment' => ['score' => 0.0, 'label' => 'neutral'],
                    'readTime' => 0,
                    'wordCount' => 0,
                    'shares' => 0,
                    'engagement' => 0.0,
                    'images' => [],
                    'videos' => [],
                    'relatedArticles' => [],
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get news sources.
     *
     * @param string $language Language code
     * @param string $country  Country code
     * @param string $category News category
     * @param int    $limit    Number of sources
     *
     * @return array{
     *     success: bool,
     *     sources: array<int, array{
     *         id: string,
     *         name: string,
     *         url: string,
     *         category: string,
     *         language: string,
     *         country: string,
     *         description: string,
     *         logoUrl: string,
     *         credibility: array{
     *             score: float,
     *             label: string,
     *         },
     *         bias: array{
     *             score: float,
     *             label: string,
     *         },
     *         articleCount: int,
     *         lastUpdated: string,
     *     }>,
     *     totalSources: int,
     *     error: string,
     * }
     */
    public function getSources(
        string $language = 'en',
        string $country = 'us',
        string $category = '',
        int $limit = 50,
    ): array {
        try {
            $requestData = [
                'lang' => $language,
                'country' => $country,
                'limit' => max(1, min($limit, 100)),
            ];

            if ($category) {
                $requestData['category'] = $category;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/sources", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $requestData),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'sources' => array_map(fn ($source) => [
                    'id' => $source['id'] ?? '',
                    'name' => $source['name'] ?? '',
                    'url' => $source['url'] ?? '',
                    'category' => $source['category'] ?? $category,
                    'language' => $source['language'] ?? $language,
                    'country' => $source['country'] ?? $country,
                    'description' => $source['description'] ?? '',
                    'logoUrl' => $source['logoUrl'] ?? '',
                    'credibility' => [
                        'score' => $source['credibility']['score'] ?? 0.0,
                        'label' => $source['credibility']['label'] ?? 'unknown',
                    ],
                    'bias' => [
                        'score' => $source['bias']['score'] ?? 0.0,
                        'label' => $source['bias']['label'] ?? 'neutral',
                    ],
                    'articleCount' => $source['articleCount'] ?? 0,
                    'lastUpdated' => $source['lastUpdated'] ?? '',
                ], $data['sources'] ?? []),
                'totalSources' => $data['totalSources'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'sources' => [],
                'totalSources' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get news categories.
     *
     * @param string $language Language code
     * @param string $country  Country code
     *
     * @return array{
     *     success: bool,
     *     categories: array<int, array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         parentCategory: string,
     *         subcategories: array<int, array{
     *             id: string,
     *             name: string,
     *             description: string,
     *         }>,
     *         articleCount: int,
     *         trending: bool,
     *     }>,
     *     totalCategories: int,
     *     error: string,
     * }
     */
    public function getCategories(
        string $language = 'en',
        string $country = 'us',
    ): array {
        try {
            $requestData = [
                'lang' => $language,
                'country' => $country,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/categories", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $requestData),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'categories' => array_map(fn ($category) => [
                    'id' => $category['id'] ?? '',
                    'name' => $category['name'] ?? '',
                    'description' => $category['description'] ?? '',
                    'parentCategory' => $category['parentCategory'] ?? '',
                    'subcategories' => array_map(fn ($sub) => [
                        'id' => $sub['id'] ?? '',
                        'name' => $sub['name'] ?? '',
                        'description' => $sub['description'] ?? '',
                    ], $category['subcategories'] ?? []),
                    'articleCount' => $category['articleCount'] ?? 0,
                    'trending' => $category['trending'] ?? false,
                ], $data['categories'] ?? []),
                'totalCategories' => $data['totalCategories'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'categories' => [],
                'totalCategories' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get news sentiment analysis.
     *
     * @param string $articleId       Article ID
     * @param bool   $includeEmotions Include emotion analysis
     * @param bool   $includeEntities Include entity analysis
     *
     * @return array{
     *     success: bool,
     *     sentiment: array{
     *         overall: array{
     *             score: float,
     *             label: string,
     *             confidence: float,
     *         },
     *         emotions: array<string, float>,
     *         entities: array<int, array{
     *             text: string,
     *             type: string,
     *             sentiment: array{
     *                 score: float,
     *                 label: string,
     *             },
     *         }>,
     *         topics: array<int, array{
     *             name: string,
     *             sentiment: array{
     *                 score: float,
     *                 label: string,
     *             },
     *         }>,
     *         summary: string,
     *     },
     *     articleId: string,
     *     error: string,
     * }
     */
    public function getSentiment(
        string $articleId,
        bool $includeEmotions = true,
        bool $includeEntities = true,
    ): array {
        try {
            $requestData = [
                'includeEmotions' => $includeEmotions,
                'includeEntities' => $includeEntities,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/articles/{$articleId}/sentiment", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $requestData),
            ]);

            $data = $response->toArray();
            $sentiment = $data['sentiment'] ?? [];

            return [
                'success' => true,
                'sentiment' => [
                    'overall' => [
                        'score' => $sentiment['overall']['score'] ?? 0.0,
                        'label' => $sentiment['overall']['label'] ?? 'neutral',
                        'confidence' => $sentiment['overall']['confidence'] ?? 0.0,
                    ],
                    'emotions' => $sentiment['emotions'] ?? [],
                    'entities' => array_map(fn ($entity) => [
                        'text' => $entity['text'] ?? '',
                        'type' => $entity['type'] ?? '',
                        'sentiment' => [
                            'score' => $entity['sentiment']['score'] ?? 0.0,
                            'label' => $entity['sentiment']['label'] ?? 'neutral',
                        ],
                    ], $sentiment['entities'] ?? []),
                    'topics' => array_map(fn ($topic) => [
                        'name' => $topic['name'] ?? '',
                        'sentiment' => [
                            'score' => $topic['sentiment']['score'] ?? 0.0,
                            'label' => $topic['sentiment']['label'] ?? 'neutral',
                        ],
                    ], $sentiment['topics'] ?? []),
                    'summary' => $sentiment['summary'] ?? '',
                ],
                'articleId' => $articleId,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'sentiment' => [
                    'overall' => ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.0],
                    'emotions' => [],
                    'entities' => [],
                    'topics' => [],
                    'summary' => '',
                ],
                'articleId' => $articleId,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get news summary.
     *
     * @param string $articleId Article ID
     * @param int    $maxLength Maximum summary length
     * @param string $style     Summary style (brief, detailed, bulleted)
     *
     * @return array{
     *     success: bool,
     *     summary: array{
     *         text: string,
     *         length: int,
     *         style: string,
     *         keyPoints: array<int, string>,
     *         keywords: array<int, string>,
     *         topics: array<int, string>,
     *         confidence: float,
     *     },
     *     articleId: string,
     *     error: string,
     * }
     */
    public function getSummary(
        string $articleId,
        int $maxLength = 200,
        string $style = 'brief',
    ): array {
        try {
            $requestData = [
                'maxLength' => max(50, min($maxLength, 1000)),
                'style' => $style,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/articles/{$articleId}/summary", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $requestData),
            ]);

            $data = $response->toArray();
            $summary = $data['summary'] ?? [];

            return [
                'success' => true,
                'summary' => [
                    'text' => $summary['text'] ?? '',
                    'length' => $summary['length'] ?? 0,
                    'style' => $summary['style'] ?? $style,
                    'keyPoints' => $summary['keyPoints'] ?? [],
                    'keywords' => $summary['keywords'] ?? [],
                    'topics' => $summary['topics'] ?? [],
                    'confidence' => $summary['confidence'] ?? 0.0,
                ],
                'articleId' => $articleId,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'summary' => [
                    'text' => '',
                    'length' => 0,
                    'style' => $style,
                    'keyPoints' => [],
                    'keywords' => [],
                    'topics' => [],
                    'confidence' => 0.0,
                ],
                'articleId' => $articleId,
                'error' => $e->getMessage(),
            ];
        }
    }
}
