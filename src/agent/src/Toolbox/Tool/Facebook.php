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
#[AsTool('facebook_post_to_page', 'Tool that posts content to Facebook pages')]
#[AsTool('facebook_get_page_posts', 'Tool that retrieves posts from Facebook pages', method: 'getPagePosts')]
#[AsTool('facebook_get_page_info', 'Tool that gets Facebook page information', method: 'getPageInfo')]
#[AsTool('facebook_search_pages', 'Tool that searches for Facebook pages', method: 'searchPages')]
#[AsTool('facebook_get_insights', 'Tool that gets Facebook page insights', method: 'getInsights')]
final readonly class Facebook
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
     * Post content to a Facebook page.
     *
     * @param string $pageId      Facebook page ID
     * @param string $message     Post message content
     * @param string $link        Optional link to share
     * @param string $pictureUrl  Optional picture URL
     * @param string $name        Optional link name
     * @param string $caption     Optional link caption
     * @param string $description Optional link description
     *
     * @return array{
     *     id: string,
     *     post_id: string,
     * }|string
     */
    public function __invoke(
        string $pageId,
        #[With(maximum: 63206)]
        string $message = '',
        string $link = '',
        string $pictureUrl = '',
        string $name = '',
        string $caption = '',
        string $description = '',
    ): array|string {
        try {
            $postData = [];

            if ($message) {
                $postData['message'] = $message;
            }

            if ($link) {
                $postData['link'] = $link;
            }

            if ($pictureUrl) {
                $postData['picture'] = $pictureUrl;
            }

            if ($name) {
                $postData['name'] = $name;
            }

            if ($caption) {
                $postData['caption'] = $caption;
            }

            if ($description) {
                $postData['description'] = $description;
            }

            if (empty($postData)) {
                return 'Error: At least one field (message, link, picture) must be provided';
            }

            $response = $this->httpClient->request('POST', "https://graph.facebook.com/{$this->apiVersion}/{$pageId}/feed", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => array_merge($this->options, $postData, [
                    'access_token' => $this->accessToken,
                ]),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error posting to Facebook: '.$data['error']['message'];
            }

            return [
                'id' => $data['id'],
                'post_id' => $data['id'],
            ];
        } catch (\Exception $e) {
            return 'Error posting to Facebook: '.$e->getMessage();
        }
    }

    /**
     * Get posts from a Facebook page.
     *
     * @param string $pageId Facebook page ID
     * @param int    $limit  Number of posts to retrieve (1-100)
     * @param string $since  Get posts since this date (YYYY-MM-DD)
     * @param string $until  Get posts until this date (YYYY-MM-DD)
     *
     * @return array<int, array{
     *     id: string,
     *     message: string,
     *     created_time: string,
     *     updated_time: string,
     *     type: string,
     *     link: string,
     *     picture: string,
     *     full_picture: string,
     *     permalink_url: string,
     *     shares: array{count: int},
     *     reactions: array{data: array<int, array{name: string, id: string, type: string}>, summary: array{total_count: int}},
     * }>
     */
    public function getPagePosts(
        string $pageId,
        int $limit = 25,
        string $since = '',
        string $until = '',
    ): array {
        try {
            $fields = 'id,message,created_time,updated_time,type,link,picture,full_picture,permalink_url,shares,reactions.summary(true).limit(0)';

            $params = [
                'fields' => $fields,
                'limit' => min(max($limit, 1), 100),
                'access_token' => $this->accessToken,
            ];

            if ($since) {
                $params['since'] = strtotime($since);
            }
            if ($until) {
                $params['until'] = strtotime($until);
            }

            $response = $this->httpClient->request('GET', "https://graph.facebook.com/{$this->apiVersion}/{$pageId}/posts", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $posts = [];
            foreach ($data['data'] as $post) {
                $posts[] = [
                    'id' => $post['id'],
                    'message' => $post['message'] ?? '',
                    'created_time' => $post['created_time'],
                    'updated_time' => $post['updated_time'],
                    'type' => $post['type'] ?? 'status',
                    'link' => $post['link'] ?? '',
                    'picture' => $post['picture'] ?? '',
                    'full_picture' => $post['full_picture'] ?? '',
                    'permalink_url' => $post['permalink_url'] ?? '',
                    'shares' => [
                        'count' => $post['shares']['count'] ?? 0,
                    ],
                    'reactions' => [
                        'data' => array_map(fn ($reaction) => [
                            'name' => $reaction['name'],
                            'id' => $reaction['id'],
                            'type' => $reaction['type'],
                        ], $post['reactions']['data'] ?? []),
                        'summary' => [
                            'total_count' => $post['reactions']['summary']['total_count'] ?? 0,
                        ],
                    ],
                ];
            }

            return $posts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Facebook page information.
     *
     * @param string $pageId Facebook page ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     username: string,
     *     about: string,
     *     category: string,
     *     description: string,
     *     fan_count: int,
     *     followers_count: int,
     *     link: string,
     *     website: string,
     *     phone: string,
     *     emails: array<int, string>,
     *     location: array{
     *         street: string,
     *         city: string,
     *         state: string,
     *         country: string,
     *         zip: string,
     *         latitude: float,
     *         longitude: float,
     *     },
     *     picture: array{data: array{url: string, is_silhouette: bool}},
     *     cover: array{source: string, offset_y: int, offset_x: int},
     *     hours: array<string, string>,
     *     verification_status: string,
     * }|string
     */
    public function getPageInfo(string $pageId): array|string
    {
        try {
            $fields = 'id,name,username,about,category,description,fan_count,followers_count,link,website,phone,emails,location,picture,cover,hours,verification_status';

            $response = $this->httpClient->request('GET', "https://graph.facebook.com/{$this->apiVersion}/{$pageId}", [
                'query' => array_merge($this->options, [
                    'fields' => $fields,
                    'access_token' => $this->accessToken,
                ]),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting page info: '.$data['error']['message'];
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'username' => $data['username'] ?? '',
                'about' => $data['about'] ?? '',
                'category' => $data['category'] ?? '',
                'description' => $data['description'] ?? '',
                'fan_count' => $data['fan_count'] ?? 0,
                'followers_count' => $data['followers_count'] ?? 0,
                'link' => $data['link'] ?? '',
                'website' => $data['website'] ?? '',
                'phone' => $data['phone'] ?? '',
                'emails' => $data['emails'] ?? [],
                'location' => [
                    'street' => $data['location']['street'] ?? '',
                    'city' => $data['location']['city'] ?? '',
                    'state' => $data['location']['state'] ?? '',
                    'country' => $data['location']['country'] ?? '',
                    'zip' => $data['location']['zip'] ?? '',
                    'latitude' => $data['location']['latitude'] ?? 0.0,
                    'longitude' => $data['location']['longitude'] ?? 0.0,
                ],
                'picture' => [
                    'data' => [
                        'url' => $data['picture']['data']['url'] ?? '',
                        'is_silhouette' => $data['picture']['data']['is_silhouette'] ?? false,
                    ],
                ],
                'cover' => [
                    'source' => $data['cover']['source'] ?? '',
                    'offset_y' => $data['cover']['offset_y'] ?? 0,
                    'offset_x' => $data['cover']['offset_x'] ?? 0,
                ],
                'hours' => $data['hours'] ?? [],
                'verification_status' => $data['verification_status'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error getting page info: '.$e->getMessage();
        }
    }

    /**
     * Search for Facebook pages.
     *
     * @param string $query Search query
     * @param string $type  Type of search (page, place, event, user)
     * @param int    $limit Number of results (1-100)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     category: string,
     *     fan_count: int,
     *     link: string,
     *     picture: array{data: array{url: string}},
     * }>
     */
    public function searchPages(
        #[With(maximum: 500)]
        string $query,
        string $type = 'page',
        int $limit = 25,
    ): array {
        try {
            $fields = 'id,name,category,fan_count,link,picture';

            $response = $this->httpClient->request('GET', "https://graph.facebook.com/{$this->apiVersion}/search", [
                'query' => array_merge($this->options, [
                    'q' => $query,
                    'type' => $type,
                    'fields' => $fields,
                    'limit' => min(max($limit, 1), 100),
                    'access_token' => $this->accessToken,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $pages = [];
            foreach ($data['data'] as $page) {
                $pages[] = [
                    'id' => $page['id'],
                    'name' => $page['name'],
                    'category' => $page['category'] ?? '',
                    'fan_count' => $page['fan_count'] ?? 0,
                    'link' => $page['link'] ?? '',
                    'picture' => [
                        'data' => [
                            'url' => $page['picture']['data']['url'] ?? '',
                        ],
                    ],
                ];
            }

            return $pages;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Facebook page insights.
     *
     * @param string $pageId Facebook page ID
     * @param string $metric Insight metric (page_impressions, page_reach, page_engaged_users, etc.)
     * @param string $period Time period (day, week, days_28)
     * @param string $since  Start date (YYYY-MM-DD)
     * @param string $until  End date (YYYY-MM-DD)
     *
     * @return array{
     *     data: array<int, array{
     *         name: string,
     *         period: string,
     *         values: array<int, array{
     *             value: int|float,
     *             end_time: string,
     *         }>,
     *     }>,
     * }|string
     */
    public function getInsights(
        string $pageId,
        string $metric = 'page_impressions',
        string $period = 'day',
        string $since = '',
        string $until = '',
    ): array|string {
        try {
            $params = [
                'metric' => $metric,
                'period' => $period,
                'access_token' => $this->accessToken,
            ];

            if ($since) {
                $params['since'] = strtotime($since);
            }
            if ($until) {
                $params['until'] = strtotime($until);
            }

            $response = $this->httpClient->request('GET', "https://graph.facebook.com/{$this->apiVersion}/{$pageId}/insights", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting insights: '.$data['error']['message'];
            }

            return [
                'data' => array_map(fn ($insight) => [
                    'name' => $insight['name'],
                    'period' => $insight['period'],
                    'values' => array_map(fn ($value) => [
                        'value' => $value['value'],
                        'end_time' => $value['end_time'],
                    ], $insight['values']),
                ], $data['data']),
            ];
        } catch (\Exception $e) {
            return 'Error getting insights: '.$e->getMessage();
        }
    }

    /**
     * Upload photo to Facebook page.
     *
     * @param string $pageId    Facebook page ID
     * @param string $photoPath Path to the photo file
     * @param string $message   Optional message with the photo
     * @param string $caption   Optional photo caption
     *
     * @return array{
     *     id: string,
     *     post_id: string,
     * }|string
     */
    public function uploadPhoto(
        string $pageId,
        string $photoPath,
        string $message = '',
        string $caption = '',
    ): array|string {
        try {
            if (!file_exists($photoPath)) {
                return 'Error: Photo file does not exist';
            }

            $photoData = file_get_contents($photoPath);
            $photoBase64 = base64_encode($photoData);

            $postData = [
                'source' => $photoBase64,
                'access_token' => $this->accessToken,
            ];

            if ($message) {
                $postData['message'] = $message;
            }

            if ($caption) {
                $postData['caption'] = $caption;
            }

            $response = $this->httpClient->request('POST', "https://graph.facebook.com/{$this->apiVersion}/{$pageId}/photos", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $postData,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error uploading photo: '.$data['error']['message'];
            }

            return [
                'id' => $data['id'],
                'post_id' => $data['id'],
            ];
        } catch (\Exception $e) {
            return 'Error uploading photo: '.$e->getMessage();
        }
    }
}
