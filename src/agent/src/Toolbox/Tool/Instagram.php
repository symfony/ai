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
#[AsTool('instagram_get_media', 'Tool that gets Instagram media and posts')]
#[AsTool('instagram_get_user_info', 'Tool that gets Instagram user information', method: 'getUserInfo')]
#[AsTool('instagram_search_hashtags', 'Tool that searches Instagram hashtags', method: 'searchHashtags')]
#[AsTool('instagram_get_media_insights', 'Tool that gets Instagram media insights', method: 'getMediaInsights')]
#[AsTool('instagram_get_user_insights', 'Tool that gets Instagram user insights', method: 'getUserInsights')]
#[AsTool('instagram_create_media_container', 'Tool that creates Instagram media containers', method: 'createMediaContainer')]
final readonly class Instagram
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v18.0',
        private array $options = [],
    ) {
    }

    /**
     * Get Instagram media and posts.
     *
     * @param string $userId Instagram user ID
     * @param int    $limit  Number of media items to retrieve (1-100)
     * @param string $fields Fields to retrieve (id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username)
     *
     * @return array<int, array{
     *     id: string,
     *     caption: string,
     *     media_type: string,
     *     media_url: string,
     *     permalink: string,
     *     thumbnail_url: string,
     *     timestamp: string,
     *     username: string,
     *     like_count: int,
     *     comments_count: int,
     * }>
     */
    public function __invoke(
        string $userId,
        int $limit = 25,
        string $fields = 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username,like_count,comments_count',
    ): array {
        try {
            $params = [
                'fields' => $fields,
                'limit' => min(max($limit, 1), 100),
            ];

            $response = $this->httpClient->request('GET', "https://graph.instagram.com/{$this->apiVersion}/{$userId}/media", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $media = [];
            foreach ($data['data'] as $item) {
                $media[] = [
                    'id' => $item['id'],
                    'caption' => $item['caption'] ?? '',
                    'media_type' => $item['media_type'] ?? 'IMAGE',
                    'media_url' => $item['media_url'] ?? '',
                    'permalink' => $item['permalink'] ?? '',
                    'thumbnail_url' => $item['thumbnail_url'] ?? '',
                    'timestamp' => $item['timestamp'] ?? '',
                    'username' => $item['username'] ?? '',
                    'like_count' => $item['like_count'] ?? 0,
                    'comments_count' => $item['comments_count'] ?? 0,
                ];
            }

            return $media;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Instagram user information.
     *
     * @param string $userId Instagram user ID
     *
     * @return array{
     *     id: string,
     *     username: string,
     *     account_type: string,
     *     media_count: int,
     *     followers_count: int,
     *     follows_count: int,
     *     name: string,
     *     biography: string,
     *     website: string,
     *     profile_picture_url: string,
     * }|string
     */
    public function getUserInfo(string $userId): array|string
    {
        try {
            $fields = 'id,username,account_type,media_count,followers_count,follows_count,name,biography,website,profile_picture_url';

            $response = $this->httpClient->request('GET', "https://graph.instagram.com/{$this->apiVersion}/{$userId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'fields' => $fields,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting user info: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'username' => $data['username'],
                'account_type' => $data['account_type'] ?? 'PERSONAL',
                'media_count' => $data['media_count'] ?? 0,
                'followers_count' => $data['followers_count'] ?? 0,
                'follows_count' => $data['follows_count'] ?? 0,
                'name' => $data['name'] ?? '',
                'biography' => $data['biography'] ?? '',
                'website' => $data['website'] ?? '',
                'profile_picture_url' => $data['profile_picture_url'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error getting user info: '.$e->getMessage();
        }
    }

    /**
     * Search Instagram hashtags.
     *
     * @param string $hashtag Hashtag to search for (without #)
     * @param int    $limit   Number of results (1-100)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     media_count: int,
     * }>
     */
    public function searchHashtags(
        #[With(maximum: 100)]
        string $hashtag,
        int $limit = 10,
    ): array {
        try {
            $hashtag = ltrim($hashtag, '#');

            $response = $this->httpClient->request('GET', "https://graph.instagram.com/{$this->apiVersion}/ig_hashtag_search", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'user_id' => $this->getUserId(),
                    'q' => $hashtag,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $hashtags = [];
            foreach (\array_slice($data['data'], 0, $limit) as $item) {
                $hashtags[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'media_count' => $item['media_count'] ?? 0,
                ];
            }

            return $hashtags;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Instagram media insights.
     *
     * @param string $mediaId Instagram media ID
     *
     * @return array{
     *     impressions: int,
     *     reach: int,
     *     likes: int,
     *     comments: int,
     *     shares: int,
     *     saves: int,
     *     video_views: int,
     *     profile_visits: int,
     *     website_clicks: int,
     * }|string
     */
    public function getMediaInsights(string $mediaId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://graph.instagram.com/{$this->apiVersion}/{$mediaId}/insights", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'metric' => 'impressions,reach,likes,comments,shares,saves,video_views,profile_visits,website_clicks',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting media insights: '.($data['error']['message'] ?? 'Unknown error');
            }

            $insights = [
                'impressions' => 0,
                'reach' => 0,
                'likes' => 0,
                'comments' => 0,
                'shares' => 0,
                'saves' => 0,
                'video_views' => 0,
                'profile_visits' => 0,
                'website_clicks' => 0,
            ];

            foreach ($data['data'] as $insight) {
                $metric = $insight['name'];
                $value = $insight['values'][0]['value'] ?? 0;

                if (isset($insights[$metric])) {
                    $insights[$metric] = $value;
                }
            }

            return $insights;
        } catch (\Exception $e) {
            return 'Error getting media insights: '.$e->getMessage();
        }
    }

    /**
     * Get Instagram user insights.
     *
     * @param string $userId Instagram user ID
     *
     * @return array{
     *     impressions: int,
     *     reach: int,
     *     profile_views: int,
     *     website_clicks: int,
     *     email_contacts: int,
     *     phone_call_clicks: int,
     *     text_message_clicks: int,
     *     follower_count: int,
     * }|string
     */
    public function getUserInsights(string $userId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://graph.instagram.com/{$this->apiVersion}/{$userId}/insights", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'metric' => 'impressions,reach,profile_views,website_clicks,email_contacts,phone_call_clicks,text_message_clicks,follower_count',
                    'period' => 'day',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting user insights: '.($data['error']['message'] ?? 'Unknown error');
            }

            $insights = [
                'impressions' => 0,
                'reach' => 0,
                'profile_views' => 0,
                'website_clicks' => 0,
                'email_contacts' => 0,
                'phone_call_clicks' => 0,
                'text_message_clicks' => 0,
                'follower_count' => 0,
            ];

            foreach ($data['data'] as $insight) {
                $metric = $insight['name'];
                $value = $insight['values'][0]['value'] ?? 0;

                if (isset($insights[$metric])) {
                    $insights[$metric] = $value;
                }
            }

            return $insights;
        } catch (\Exception $e) {
            return 'Error getting user insights: '.$e->getMessage();
        }
    }

    /**
     * Create Instagram media container for posting.
     *
     * @param string $imageUrl  URL of the image to post
     * @param string $caption   Caption for the post
     * @param string $mediaType Media type (IMAGE, VIDEO, CAROUSEL_ALBUM)
     *
     * @return array{
     *     id: string,
     *     status_code: string,
     * }|string
     */
    public function createMediaContainer(
        string $imageUrl,
        string $caption,
        string $mediaType = 'IMAGE',
    ): array|string {
        try {
            $payload = [
                'image_url' => $imageUrl,
                'caption' => $caption,
                'media_type' => $mediaType,
            ];

            $response = $this->httpClient->request('POST', "https://graph.instagram.com/{$this->apiVersion}/{$this->getUserId()}/media", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating media container: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'status_code' => $data['status_code'] ?? 'FINISHED',
            ];
        } catch (\Exception $e) {
            return 'Error creating media container: '.$e->getMessage();
        }
    }

    /**
     * Publish Instagram media container.
     *
     * @param string $creationId Media container ID
     *
     * @return array{
     *     id: string,
     * }|string
     */
    public function publishMediaContainer(string $creationId): array|string
    {
        try {
            $response = $this->httpClient->request('POST', "https://graph.instagram.com/{$this->apiVersion}/{$this->getUserId()}/media_publish", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'creation_id' => $creationId,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error publishing media container: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
            ];
        } catch (\Exception $e) {
            return 'Error publishing media container: '.$e->getMessage();
        }
    }

    /**
     * Get Instagram media by hashtag.
     *
     * @param string $hashtagId Instagram hashtag ID
     * @param int    $limit     Number of media items (1-100)
     *
     * @return array<int, array{
     *     id: string,
     *     caption: string,
     *     media_type: string,
     *     media_url: string,
     *     permalink: string,
     *     timestamp: string,
     *     username: string,
     *     like_count: int,
     *     comments_count: int,
     * }>
     */
    public function getMediaByHashtag(string $hashtagId, int $limit = 25): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://graph.instagram.com/{$this->apiVersion}/{$hashtagId}/top_media", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'user_id' => $this->getUserId(),
                    'fields' => 'id,caption,media_type,media_url,permalink,timestamp,username,like_count,comments_count',
                    'limit' => min(max($limit, 1), 100),
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $media = [];
            foreach ($data['data'] as $item) {
                $media[] = [
                    'id' => $item['id'],
                    'caption' => $item['caption'] ?? '',
                    'media_type' => $item['media_type'] ?? 'IMAGE',
                    'media_url' => $item['media_url'] ?? '',
                    'permalink' => $item['permalink'] ?? '',
                    'timestamp' => $item['timestamp'] ?? '',
                    'username' => $item['username'] ?? '',
                    'like_count' => $item['like_count'] ?? 0,
                    'comments_count' => $item['comments_count'] ?? 0,
                ];
            }

            return $media;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user ID from access token.
     */
    private function getUserId(): string
    {
        try {
            $response = $this->httpClient->request('GET', "https://graph.instagram.com/{$this->apiVersion}/me", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'fields' => 'id',
                ],
            ]);

            $data = $response->toArray();

            return $data['id'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
