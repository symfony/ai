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
#[AsTool('tiktok_search_videos', 'Tool that searches for TikTok videos')]
#[AsTool('tiktok_get_user_info', 'Tool that gets TikTok user information', method: 'getUserInfo')]
#[AsTool('tiktok_get_user_videos', 'Tool that gets TikTok user videos', method: 'getUserVideos')]
#[AsTool('tiktok_get_video_info', 'Tool that gets TikTok video details', method: 'getVideoInfo')]
#[AsTool('tiktok_get_trending_videos', 'Tool that gets trending TikTok videos', method: 'getTrendingVideos')]
#[AsTool('tiktok_get_hashtag_videos', 'Tool that gets TikTok videos by hashtag', method: 'getHashtagVideos')]
final readonly class TikTok
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v1.3',
        private array $options = [],
    ) {
    }

    /**
     * Search for TikTok videos.
     *
     * @param string $query       Search query
     * @param int    $maxCount    Maximum number of results (1-20)
     * @param string $cursor      Pagination cursor
     * @param string $sortType    Sort type (most_relevant, most_liked, most_shared, most_viewed, newest)
     * @param string $publishTime Publish time filter (last_1_hour, last_24_hours, last_7_days, last_30_days, last_1_year)
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     video_description: string,
     *     duration: int,
     *     cover_image_url: string,
     *     embed_url: string,
     *     like_count: int,
     *     comment_count: int,
     *     share_count: int,
     *     view_count: int,
     *     create_time: int,
     *     hashtag_names: array<int, string>,
     *     mentions: array<int, string>,
     *     video_url: string,
     *     author: array{
     *         id: string,
     *         unique_id: string,
     *         nickname: string,
     *         avatar_thumb: array{url_list: array<int, string>},
     *         follower_count: int,
     *         following_count: int,
     *         aweme_count: int,
     *         verification_info: array{type: int, desc: string},
     *     },
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxCount = 10,
        string $cursor = '',
        string $sortType = 'most_relevant',
        string $publishTime = '',
    ): array {
        try {
            $payload = [
                'keyword' => $query,
                'max_count' => min(max($maxCount, 1), 20),
                'sort_type' => $sortType,
            ];

            if ($cursor) {
                $payload['cursor'] = $cursor;
            }

            if ($publishTime) {
                $payload['publish_time'] = $publishTime;
            }

            $response = $this->httpClient->request('POST', "https://open.tiktokapis.com/{$this->apiVersion}/research/video/query/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (!isset($data['data']['videos'])) {
                return [];
            }

            $videos = [];
            foreach ($data['data']['videos'] as $video) {
                $videos[] = [
                    'id' => $video['id'],
                    'title' => $video['title'] ?? '',
                    'video_description' => $video['video_description'] ?? '',
                    'duration' => $video['duration'] ?? 0,
                    'cover_image_url' => $video['cover_image_url'] ?? '',
                    'embed_url' => $video['embed_url'] ?? '',
                    'like_count' => $video['statistics']['like_count'] ?? 0,
                    'comment_count' => $video['statistics']['comment_count'] ?? 0,
                    'share_count' => $video['statistics']['share_count'] ?? 0,
                    'view_count' => $video['statistics']['view_count'] ?? 0,
                    'create_time' => $video['create_time'] ?? 0,
                    'hashtag_names' => $video['text_extra'] ?? [],
                    'mentions' => $video['text_extra'] ?? [],
                    'video_url' => $video['video']['play_addr']['url_list'][0] ?? '',
                    'author' => [
                        'id' => $video['author']['id'] ?? '',
                        'unique_id' => $video['author']['unique_id'] ?? '',
                        'nickname' => $video['author']['nickname'] ?? '',
                        'avatar_thumb' => [
                            'url_list' => $video['author']['avatar_thumb']['url_list'] ?? [],
                        ],
                        'follower_count' => $video['author']['follower_count'] ?? 0,
                        'following_count' => $video['author']['following_count'] ?? 0,
                        'aweme_count' => $video['author']['aweme_count'] ?? 0,
                        'verification_info' => [
                            'type' => $video['author']['verification_info']['type'] ?? 0,
                            'desc' => $video['author']['verification_info']['desc'] ?? '',
                        ],
                    ],
                ];
            }

            return $videos;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get TikTok user information.
     *
     * @param string $username TikTok username
     *
     * @return array{
     *     id: string,
     *     unique_id: string,
     *     nickname: string,
     *     avatar_thumb: array{url_list: array<int, string>},
     *     follower_count: int,
     *     following_count: int,
     *     aweme_count: int,
     *     verification_info: array{type: int, desc: string},
     *     signature: string,
     *     create_time: int,
     *     custom_verify: string,
     *     enterprise_verify_reason: string,
     *     region: string,
     *     commerce_user_info: array{commerce_user: bool, ad_video_url: string, ad_web_url: string},
     * }|string
     */
    public function getUserInfo(string $username): array|string
    {
        try {
            $response = $this->httpClient->request('POST', "https://open.tiktokapis.com/{$this->apiVersion}/user/info/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'fields' => ['id', 'unique_id', 'nickname', 'avatar_thumb', 'follower_count', 'following_count', 'aweme_count', 'verification_info', 'signature', 'create_time', 'custom_verify', 'enterprise_verify_reason', 'region', 'commerce_user_info'],
                    'username' => $username,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting user info: '.($data['error']['message'] ?? 'Unknown error');
            }

            $user = $data['data']['user'];

            return [
                'id' => $user['id'],
                'unique_id' => $user['unique_id'],
                'nickname' => $user['nickname'],
                'avatar_thumb' => [
                    'url_list' => $user['avatar_thumb']['url_list'] ?? [],
                ],
                'follower_count' => $user['follower_count'] ?? 0,
                'following_count' => $user['following_count'] ?? 0,
                'aweme_count' => $user['aweme_count'] ?? 0,
                'verification_info' => [
                    'type' => $user['verification_info']['type'] ?? 0,
                    'desc' => $user['verification_info']['desc'] ?? '',
                ],
                'signature' => $user['signature'] ?? '',
                'create_time' => $user['create_time'] ?? 0,
                'custom_verify' => $user['custom_verify'] ?? '',
                'enterprise_verify_reason' => $user['enterprise_verify_reason'] ?? '',
                'region' => $user['region'] ?? '',
                'commerce_user_info' => [
                    'commerce_user' => $user['commerce_user_info']['commerce_user'] ?? false,
                    'ad_video_url' => $user['commerce_user_info']['ad_video_url'] ?? '',
                    'ad_web_url' => $user['commerce_user_info']['ad_web_url'] ?? '',
                ],
            ];
        } catch (\Exception $e) {
            return 'Error getting user info: '.$e->getMessage();
        }
    }

    /**
     * Get TikTok user videos.
     *
     * @param string $username TikTok username
     * @param int    $maxCount Maximum number of videos (1-20)
     * @param string $cursor   Pagination cursor
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     video_description: string,
     *     duration: int,
     *     cover_image_url: string,
     *     like_count: int,
     *     comment_count: int,
     *     share_count: int,
     *     view_count: int,
     *     create_time: int,
     *     video_url: string,
     * }>
     */
    public function getUserVideos(
        string $username,
        int $maxCount = 10,
        string $cursor = '',
    ): array {
        try {
            $payload = [
                'username' => $username,
                'max_count' => min(max($maxCount, 1), 20),
            ];

            if ($cursor) {
                $payload['cursor'] = $cursor;
            }

            $response = $this->httpClient->request('POST', "https://open.tiktokapis.com/{$this->apiVersion}/user/videos/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (!isset($data['data']['videos'])) {
                return [];
            }

            $videos = [];
            foreach ($data['data']['videos'] as $video) {
                $videos[] = [
                    'id' => $video['id'],
                    'title' => $video['title'] ?? '',
                    'video_description' => $video['video_description'] ?? '',
                    'duration' => $video['duration'] ?? 0,
                    'cover_image_url' => $video['cover_image_url'] ?? '',
                    'like_count' => $video['statistics']['like_count'] ?? 0,
                    'comment_count' => $video['statistics']['comment_count'] ?? 0,
                    'share_count' => $video['statistics']['share_count'] ?? 0,
                    'view_count' => $video['statistics']['view_count'] ?? 0,
                    'create_time' => $video['create_time'] ?? 0,
                    'video_url' => $video['video']['play_addr']['url_list'][0] ?? '',
                ];
            }

            return $videos;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get TikTok video information.
     *
     * @param string $videoId TikTok video ID
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     video_description: string,
     *     duration: int,
     *     cover_image_url: string,
     *     embed_url: string,
     *     like_count: int,
     *     comment_count: int,
     *     share_count: int,
     *     view_count: int,
     *     create_time: int,
     *     hashtag_names: array<int, string>,
     *     video_url: string,
     *     author: array{
     *         id: string,
     *         unique_id: string,
     *         nickname: string,
     *         avatar_thumb: array{url_list: array<int, string>},
     *         follower_count: int,
     *     },
     * }|string
     */
    public function getVideoInfo(string $videoId): array|string
    {
        try {
            $response = $this->httpClient->request('POST', "https://open.tiktokapis.com/{$this->apiVersion}/video/info/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'video_id' => $videoId,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting video info: '.($data['error']['message'] ?? 'Unknown error');
            }

            $video = $data['data']['video'];

            return [
                'id' => $video['id'],
                'title' => $video['title'] ?? '',
                'video_description' => $video['video_description'] ?? '',
                'duration' => $video['duration'] ?? 0,
                'cover_image_url' => $video['cover_image_url'] ?? '',
                'embed_url' => $video['embed_url'] ?? '',
                'like_count' => $video['statistics']['like_count'] ?? 0,
                'comment_count' => $video['statistics']['comment_count'] ?? 0,
                'share_count' => $video['statistics']['share_count'] ?? 0,
                'view_count' => $video['statistics']['view_count'] ?? 0,
                'create_time' => $video['create_time'] ?? 0,
                'hashtag_names' => $video['text_extra'] ?? [],
                'video_url' => $video['video']['play_addr']['url_list'][0] ?? '',
                'author' => [
                    'id' => $video['author']['id'] ?? '',
                    'unique_id' => $video['author']['unique_id'] ?? '',
                    'nickname' => $video['author']['nickname'] ?? '',
                    'avatar_thumb' => [
                        'url_list' => $video['author']['avatar_thumb']['url_list'] ?? [],
                    ],
                    'follower_count' => $video['author']['follower_count'] ?? 0,
                ],
            ];
        } catch (\Exception $e) {
            return 'Error getting video info: '.$e->getMessage();
        }
    }

    /**
     * Get trending TikTok videos.
     *
     * @param int    $maxCount    Maximum number of videos (1-20)
     * @param string $cursor      Pagination cursor
     * @param string $countryCode Country code for trending videos
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     video_description: string,
     *     duration: int,
     *     cover_image_url: string,
     *     like_count: int,
     *     comment_count: int,
     *     share_count: int,
     *     view_count: int,
     *     create_time: int,
     *     video_url: string,
     *     author: array{
     *         id: string,
     *         unique_id: string,
     *         nickname: string,
     *         follower_count: int,
     *     },
     * }>
     */
    public function getTrendingVideos(
        int $maxCount = 10,
        string $cursor = '',
        string $countryCode = 'US',
    ): array {
        try {
            $payload = [
                'max_count' => min(max($maxCount, 1), 20),
                'country_code' => $countryCode,
            ];

            if ($cursor) {
                $payload['cursor'] = $cursor;
            }

            $response = $this->httpClient->request('POST', "https://open.tiktokapis.com/{$this->apiVersion}/discovery/trending/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (!isset($data['data']['videos'])) {
                return [];
            }

            $videos = [];
            foreach ($data['data']['videos'] as $video) {
                $videos[] = [
                    'id' => $video['id'],
                    'title' => $video['title'] ?? '',
                    'video_description' => $video['video_description'] ?? '',
                    'duration' => $video['duration'] ?? 0,
                    'cover_image_url' => $video['cover_image_url'] ?? '',
                    'like_count' => $video['statistics']['like_count'] ?? 0,
                    'comment_count' => $video['statistics']['comment_count'] ?? 0,
                    'share_count' => $video['statistics']['share_count'] ?? 0,
                    'view_count' => $video['statistics']['view_count'] ?? 0,
                    'create_time' => $video['create_time'] ?? 0,
                    'video_url' => $video['video']['play_addr']['url_list'][0] ?? '',
                    'author' => [
                        'id' => $video['author']['id'] ?? '',
                        'unique_id' => $video['author']['unique_id'] ?? '',
                        'nickname' => $video['author']['nickname'] ?? '',
                        'follower_count' => $video['author']['follower_count'] ?? 0,
                    ],
                ];
            }

            return $videos;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get TikTok videos by hashtag.
     *
     * @param string $hashtag  Hashtag name (without #)
     * @param int    $maxCount Maximum number of videos (1-20)
     * @param string $cursor   Pagination cursor
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     video_description: string,
     *     duration: int,
     *     cover_image_url: string,
     *     like_count: int,
     *     comment_count: int,
     *     share_count: int,
     *     view_count: int,
     *     create_time: int,
     *     video_url: string,
     *     author: array{
     *         id: string,
     *         unique_id: string,
     *         nickname: string,
     *     },
     * }>
     */
    public function getHashtagVideos(
        string $hashtag,
        int $maxCount = 10,
        string $cursor = '',
    ): array {
        try {
            $hashtag = ltrim($hashtag, '#');

            $payload = [
                'hashtag_name' => $hashtag,
                'max_count' => min(max($maxCount, 1), 20),
            ];

            if ($cursor) {
                $payload['cursor'] = $cursor;
            }

            $response = $this->httpClient->request('POST', "https://open.tiktokapis.com/{$this->apiVersion}/discovery/hashtag/videos/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (!isset($data['data']['videos'])) {
                return [];
            }

            $videos = [];
            foreach ($data['data']['videos'] as $video) {
                $videos[] = [
                    'id' => $video['id'],
                    'title' => $video['title'] ?? '',
                    'video_description' => $video['video_description'] ?? '',
                    'duration' => $video['duration'] ?? 0,
                    'cover_image_url' => $video['cover_image_url'] ?? '',
                    'like_count' => $video['statistics']['like_count'] ?? 0,
                    'comment_count' => $video['statistics']['comment_count'] ?? 0,
                    'share_count' => $video['statistics']['share_count'] ?? 0,
                    'view_count' => $video['statistics']['view_count'] ?? 0,
                    'create_time' => $video['create_time'] ?? 0,
                    'video_url' => $video['video']['play_addr']['url_list'][0] ?? '',
                    'author' => [
                        'id' => $video['author']['id'] ?? '',
                        'unique_id' => $video['author']['unique_id'] ?? '',
                        'nickname' => $video['author']['nickname'] ?? '',
                    ],
                ];
            }

            return $videos;
        } catch (\Exception $e) {
            return [];
        }
    }
}
