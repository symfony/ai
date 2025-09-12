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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('twitter_search_tweets', 'Tool that searches for tweets on Twitter/X')]
#[AsTool('twitter_post_tweet', 'Tool that posts tweets to Twitter/X', method: 'postTweet')]
#[AsTool('twitter_get_user_timeline', 'Tool that gets user timeline from Twitter/X', method: 'getUserTimeline')]
#[AsTool('twitter_get_trending_topics', 'Tool that gets trending topics on Twitter/X', method: 'getTrendingTopics')]
#[AsTool('twitter_get_user_info', 'Tool that gets user information from Twitter/X', method: 'getUserInfo')]
#[AsTool('twitter_retweet', 'Tool that retweets a tweet on Twitter/X', method: 'retweet')]
#[AsTool('twitter_like_tweet', 'Tool that likes a tweet on Twitter/X', method: 'likeTweet')]
final readonly class Twitter
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $bearerToken,
        #[\SensitiveParameter] private string $apiKey = '',
        #[\SensitiveParameter] private string $apiSecret = '',
        #[\SensitiveParameter] private string $accessToken = '',
        #[\SensitiveParameter] private string $accessTokenSecret = '',
        private array $options = [],
    ) {
    }

    /**
     * Search for tweets on Twitter/X.
     *
     * @param string $query      Search query
     * @param int    $maxResults Maximum number of results (10-100)
     * @param string $lang       Language code (e.g., 'en', 'es', 'fr')
     * @param string $resultType Type of results (mixed, recent, popular)
     * @param string $until      Return tweets before this date (YYYY-MM-DD)
     * @param string $since      Return tweets after this date (YYYY-MM-DD)
     *
     * @return array<int, array{
     *     id: string,
     *     text: string,
     *     author_id: string,
     *     created_at: string,
     *     public_metrics: array{
     *         retweet_count: int,
     *         like_count: int,
     *         reply_count: int,
     *         quote_count: int,
     *     },
     *     lang: string,
     *     possibly_sensitive: bool,
     *     entities: array{
     *         hashtags: array<int, array{tag: string, start: int, end: int}>,
     *         mentions: array<int, array{username: string, start: int, end: int}>,
     *         urls: array<int, array{url: string, expanded_url: string, display_url: string}>,
     *     },
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 10,
        string $lang = 'en',
        string $resultType = 'recent',
        string $until = '',
        string $since = '',
    ): array {
        try {
            $params = [
                'query' => $query,
                'max_results' => min(max($maxResults, 10), 100),
                'tweet.fields' => 'id,text,author_id,created_at,public_metrics,lang,possibly_sensitive,entities',
                'user.fields' => 'id,name,username,verified,public_metrics',
                'expansions' => 'author_id',
            ];

            if ($lang) {
                $params['lang'] = $lang;
            }
            if ($resultType) {
                $params['result_type'] = $resultType;
            }
            if ($until) {
                $params['until'] = $until;
            }
            if ($since) {
                $params['since'] = $since;
            }

            $response = $this->httpClient->request('GET', 'https://api.twitter.com/2/tweets/search/recent', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->bearerToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $tweets = [];
            foreach ($data['data'] as $tweet) {
                $tweets[] = [
                    'id' => $tweet['id'],
                    'text' => $tweet['text'],
                    'author_id' => $tweet['author_id'],
                    'created_at' => $tweet['created_at'],
                    'public_metrics' => [
                        'retweet_count' => $tweet['public_metrics']['retweet_count'] ?? 0,
                        'like_count' => $tweet['public_metrics']['like_count'] ?? 0,
                        'reply_count' => $tweet['public_metrics']['reply_count'] ?? 0,
                        'quote_count' => $tweet['public_metrics']['quote_count'] ?? 0,
                    ],
                    'lang' => $tweet['lang'] ?? 'en',
                    'possibly_sensitive' => $tweet['possibly_sensitive'] ?? false,
                    'entities' => [
                        'hashtags' => array_map(fn ($hashtag) => [
                            'tag' => $hashtag['tag'],
                            'start' => $hashtag['start'],
                            'end' => $hashtag['end'],
                        ], $tweet['entities']['hashtags'] ?? []),
                        'mentions' => array_map(fn ($mention) => [
                            'username' => $mention['username'],
                            'start' => $mention['start'],
                            'end' => $mention['end'],
                        ], $tweet['entities']['mentions'] ?? []),
                        'urls' => array_map(fn ($url) => [
                            'url' => $url['url'],
                            'expanded_url' => $url['expanded_url'] ?? '',
                            'display_url' => $url['display_url'] ?? '',
                        ], $tweet['entities']['urls'] ?? []),
                    ],
                ];
            }

            return $tweets;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Post a tweet to Twitter/X.
     *
     * @param string             $text           Tweet text content
     * @param array<int, string> $mediaIds       Optional media IDs to attach
     * @param string             $replyToTweetId Optional tweet ID to reply to
     *
     * @return array{
     *     id: string,
     *     text: string,
     *     created_at: string,
     * }|string
     */
    public function postTweet(
        #[With(maximum: 280)]
        string $text,
        array $mediaIds = [],
        string $replyToTweetId = '',
    ): array|string {
        try {
            if (empty($this->accessToken) || empty($this->accessTokenSecret)) {
                return 'Error: OAuth credentials required for posting tweets';
            }

            $tweetData = [
                'text' => $text,
            ];

            if (!empty($mediaIds)) {
                $tweetData['media'] = [
                    'media_ids' => $mediaIds,
                ];
            }

            if ($replyToTweetId) {
                $tweetData['reply'] = [
                    'in_reply_to_tweet_id' => $replyToTweetId,
                ];
            }

            $response = $this->httpClient->request('POST', 'https://api.twitter.com/2/tweets', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->bearerToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $tweetData,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error posting tweet: '.implode(', ', array_column($data['errors'], 'detail'));
            }

            return [
                'id' => $data['data']['id'],
                'text' => $data['data']['text'],
                'created_at' => $data['data']['created_at'] ?? date('c'),
            ];
        } catch (\Exception $e) {
            return 'Error posting tweet: '.$e->getMessage();
        }
    }

    /**
     * Get user timeline from Twitter/X.
     *
     * @param string $userId     User ID to get timeline for
     * @param int    $maxResults Maximum number of tweets (5-100)
     * @param string $since      Return tweets after this date (YYYY-MM-DD)
     * @param string $until      Return tweets before this date (YYYY-MM-DD)
     *
     * @return array<int, array{
     *     id: string,
     *     text: string,
     *     created_at: string,
     *     public_metrics: array{
     *         retweet_count: int,
     *         like_count: int,
     *         reply_count: int,
     *         quote_count: int,
     *     },
     *     lang: string,
     * }>
     */
    public function getUserTimeline(
        string $userId,
        int $maxResults = 10,
        string $since = '',
        string $until = '',
    ): array {
        try {
            $params = [
                'max_results' => min(max($maxResults, 5), 100),
                'tweet.fields' => 'id,text,created_at,public_metrics,lang',
            ];

            if ($since) {
                $params['start_time'] = $since.'T00:00:00Z';
            }
            if ($until) {
                $params['end_time'] = $until.'T23:59:59Z';
            }

            $response = $this->httpClient->request('GET', "https://api.twitter.com/2/users/{$userId}/tweets", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->bearerToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $tweets = [];
            foreach ($data['data'] as $tweet) {
                $tweets[] = [
                    'id' => $tweet['id'],
                    'text' => $tweet['text'],
                    'created_at' => $tweet['created_at'],
                    'public_metrics' => [
                        'retweet_count' => $tweet['public_metrics']['retweet_count'] ?? 0,
                        'like_count' => $tweet['public_metrics']['like_count'] ?? 0,
                        'reply_count' => $tweet['public_metrics']['reply_count'] ?? 0,
                        'quote_count' => $tweet['public_metrics']['quote_count'] ?? 0,
                    ],
                    'lang' => $tweet['lang'] ?? 'en',
                ];
            }

            return $tweets;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get trending topics on Twitter/X.
     *
     * @param int $woeid Where On Earth ID (1 = worldwide, 23424977 = US, etc.)
     *
     * @return array<int, array{
     *     name: string,
     *     url: string,
     *     promoted_content: string|null,
     *     query: string,
     *     tweet_volume: int,
     * }>|string
     */
    public function getTrendingTopics(int $woeid = 1): array|string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.twitter.com/1.1/trends/place.json', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->bearerToken,
                ],
                'query' => [
                    'id' => $woeid,
                ],
            ]);

            $data = $response->toArray();

            if (empty($data) || !isset($data[0]['trends'])) {
                return [];
            }

            $trends = [];
            foreach ($data[0]['trends'] as $trend) {
                $trends[] = [
                    'name' => $trend['name'],
                    'url' => $trend['url'],
                    'promoted_content' => $trend['promoted_content'] ?? null,
                    'query' => $trend['query'],
                    'tweet_volume' => $trend['tweet_volume'] ?? 0,
                ];
            }

            return $trends;
        } catch (\Exception $e) {
            return 'Error getting trending topics: '.$e->getMessage();
        }
    }

    /**
     * Get user information from Twitter/X.
     *
     * @param string $username Username to get information for
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     username: string,
     *     description: string,
     *     location: string,
     *     url: string,
     *     verified: bool,
     *     public_metrics: array{
     *         followers_count: int,
     *         following_count: int,
     *         tweet_count: int,
     *         listed_count: int,
     *     },
     *     created_at: string,
     *     profile_image_url: string,
     * }|string
     */
    public function getUserInfo(string $username): array|string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.twitter.com/2/users/by/username/'.ltrim($username, '@'), [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->bearerToken,
                ],
                'query' => [
                    'user.fields' => 'id,name,username,description,location,url,verified,public_metrics,created_at,profile_image_url',
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return 'Error: User not found';
            }

            $user = $data['data'];

            return [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'description' => $user['description'] ?? '',
                'location' => $user['location'] ?? '',
                'url' => $user['url'] ?? '',
                'verified' => $user['verified'] ?? false,
                'public_metrics' => [
                    'followers_count' => $user['public_metrics']['followers_count'] ?? 0,
                    'following_count' => $user['public_metrics']['following_count'] ?? 0,
                    'tweet_count' => $user['public_metrics']['tweet_count'] ?? 0,
                    'listed_count' => $user['public_metrics']['listed_count'] ?? 0,
                ],
                'created_at' => $user['created_at'],
                'profile_image_url' => $user['profile_image_url'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error getting user info: '.$e->getMessage();
        }
    }

    /**
     * Retweet a tweet on Twitter/X.
     *
     * @param string $tweetId Tweet ID to retweet
     *
     * @return array{
     *     id: string,
     *     text: string,
     *     created_at: string,
     * }|string
     */
    public function retweet(string $tweetId): array|string
    {
        try {
            if (empty($this->accessToken) || empty($this->accessTokenSecret)) {
                return 'Error: OAuth credentials required for retweeting';
            }

            $response = $this->httpClient->request('POST', 'https://api.twitter.com/2/users/me/retweets', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->bearerToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'tweet_id' => $tweetId,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error retweeting: '.implode(', ', array_column($data['errors'], 'detail'));
            }

            return [
                'id' => $data['data']['id'],
                'text' => $data['data']['text'] ?? '',
                'created_at' => $data['data']['created_at'] ?? date('c'),
            ];
        } catch (\Exception $e) {
            return 'Error retweeting: '.$e->getMessage();
        }
    }

    /**
     * Like a tweet on Twitter/X.
     *
     * @param string $tweetId Tweet ID to like
     *
     * @return array{
     *     liked: bool,
     * }|string
     */
    public function likeTweet(string $tweetId): array|string
    {
        try {
            if (empty($this->accessToken) || empty($this->accessTokenSecret)) {
                return 'Error: OAuth credentials required for liking tweets';
            }

            $response = $this->httpClient->request('POST', 'https://api.twitter.com/2/users/me/likes', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->bearerToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'tweet_id' => $tweetId,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error liking tweet: '.implode(', ', array_column($data['errors'], 'detail'));
            }

            return [
                'liked' => true,
            ];
        } catch (\Exception $e) {
            return 'Error liking tweet: '.$e->getMessage();
        }
    }

    /**
     * Upload media to Twitter/X.
     *
     * @param string $filePath  Path to the media file
     * @param string $mediaType Media type (image/jpeg, image/png, video/mp4, etc.)
     *
     * @return array{
     *     media_id: string,
     *     media_id_string: string,
     *     size: int,
     *     expires_after_secs: int,
     * }|string
     */
    public function uploadMedia(string $filePath, string $mediaType = 'image/jpeg'): array|string
    {
        try {
            if (!file_exists($filePath)) {
                return 'Error: Media file does not exist';
            }

            if (empty($this->accessToken) || empty($this->accessTokenSecret)) {
                return 'Error: OAuth credentials required for uploading media';
            }

            $fileContent = file_get_contents($filePath);
            $base64Content = base64_encode($fileContent);

            $response = $this->httpClient->request('POST', 'https://upload.twitter.com/1.1/media/upload.json', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->bearerToken,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'media_data' => $base64Content,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error uploading media: '.implode(', ', array_column($data['errors'], 'message'));
            }

            return [
                'media_id' => $data['media_id'],
                'media_id_string' => $data['media_id_string'],
                'size' => $data['size'],
                'expires_after_secs' => $data['expires_after_secs'] ?? 0,
            ];
        } catch (\Exception $e) {
            return 'Error uploading media: '.$e->getMessage();
        }
    }
}
