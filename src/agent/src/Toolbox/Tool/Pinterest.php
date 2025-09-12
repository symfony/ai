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
#[AsTool('pinterest_search_pins', 'Tool that searches Pinterest pins')]
#[AsTool('pinterest_get_user_boards', 'Tool that gets Pinterest user boards', method: 'getUserBoards')]
#[AsTool('pinterest_get_board_pins', 'Tool that gets Pinterest board pins', method: 'getBoardPins')]
#[AsTool('pinterest_create_pin', 'Tool that creates Pinterest pins', method: 'createPin')]
#[AsTool('pinterest_get_user_info', 'Tool that gets Pinterest user information', method: 'getUserInfo')]
#[AsTool('pinterest_search_boards', 'Tool that searches Pinterest boards', method: 'searchBoards')]
final readonly class Pinterest
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v1',
        private array $options = [],
    ) {
    }

    /**
     * Search Pinterest pins.
     *
     * @param string $query    Search query
     * @param int    $limit    Number of results (1-250)
     * @param string $bookmark Pagination bookmark
     * @param string $sort     Sort order (popular, newest)
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     url: string,
     *     image: array{
     *         original: array{url: string, width: int, height: int},
     *         small: array{url: string, width: int, height: int},
     *         medium: array{url: string, width: int, height: int},
     *     },
     *     board: array{
     *         id: string,
     *         name: string,
     *         url: string,
     *     },
     *     creator: array{
     *         id: string,
     *         username: string,
     *         first_name: string,
     *         last_name: string,
     *         image: array{small: array{url: string}},
     *     },
     *     counts: array{
     *         saves: int,
     *         comments: int,
     *     },
     *     created_at: string,
     *     link: string,
     *     note: string,
     *     color: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $limit = 25,
        string $bookmark = '',
        string $sort = 'popular',
    ): array {
        try {
            $params = [
                'query' => $query,
                'limit' => min(max($limit, 1), 250),
                'sort' => $sort,
            ];

            if ($bookmark) {
                $params['bookmark'] = $bookmark;
            }

            $response = $this->httpClient->request('GET', "https://api.pinterest.com/{$this->apiVersion}/search/pins", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $pins = [];
            foreach ($data['data'] as $pin) {
                $pins[] = [
                    'id' => $pin['id'],
                    'title' => $pin['title'] ?? '',
                    'description' => $pin['description'] ?? '',
                    'url' => $pin['url'] ?? '',
                    'image' => [
                        'original' => [
                            'url' => $pin['image']['original']['url'] ?? '',
                            'width' => $pin['image']['original']['width'] ?? 0,
                            'height' => $pin['image']['original']['height'] ?? 0,
                        ],
                        'small' => [
                            'url' => $pin['image']['small']['url'] ?? '',
                            'width' => $pin['image']['small']['width'] ?? 0,
                            'height' => $pin['image']['small']['height'] ?? 0,
                        ],
                        'medium' => [
                            'url' => $pin['image']['medium']['url'] ?? '',
                            'width' => $pin['image']['medium']['width'] ?? 0,
                            'height' => $pin['image']['medium']['height'] ?? 0,
                        ],
                    ],
                    'board' => [
                        'id' => $pin['board']['id'] ?? '',
                        'name' => $pin['board']['name'] ?? '',
                        'url' => $pin['board']['url'] ?? '',
                    ],
                    'creator' => [
                        'id' => $pin['creator']['id'] ?? '',
                        'username' => $pin['creator']['username'] ?? '',
                        'first_name' => $pin['creator']['first_name'] ?? '',
                        'last_name' => $pin['creator']['last_name'] ?? '',
                        'image' => [
                            'small' => [
                                'url' => $pin['creator']['image']['small']['url'] ?? '',
                            ],
                        ],
                    ],
                    'counts' => [
                        'saves' => $pin['counts']['saves'] ?? 0,
                        'comments' => $pin['counts']['comments'] ?? 0,
                    ],
                    'created_at' => $pin['created_at'] ?? '',
                    'link' => $pin['link'] ?? '',
                    'note' => $pin['note'] ?? '',
                    'color' => $pin['color'] ?? '',
                ];
            }

            return $pins;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Pinterest user boards.
     *
     * @param string $username Pinterest username
     * @param int    $limit    Number of results (1-250)
     * @param string $bookmark Pagination bookmark
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     url: string,
     *     creator: array{
     *         id: string,
     *         username: string,
     *         first_name: string,
     *         last_name: string,
     *     },
     *     counts: array{
     *         pins: int,
     *         collaborators: int,
     *         followers: int,
     *     },
     *     created_at: string,
     *     privacy: string,
     *     reason: string,
     * }>
     */
    public function getUserBoards(
        string $username,
        int $limit = 25,
        string $bookmark = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 250),
            ];

            if ($bookmark) {
                $params['bookmark'] = $bookmark;
            }

            $response = $this->httpClient->request('GET', "https://api.pinterest.com/{$this->apiVersion}/users/{$username}/boards", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $boards = [];
            foreach ($data['data'] as $board) {
                $boards[] = [
                    'id' => $board['id'],
                    'name' => $board['name'],
                    'description' => $board['description'] ?? '',
                    'url' => $board['url'],
                    'creator' => [
                        'id' => $board['creator']['id'] ?? '',
                        'username' => $board['creator']['username'] ?? '',
                        'first_name' => $board['creator']['first_name'] ?? '',
                        'last_name' => $board['creator']['last_name'] ?? '',
                    ],
                    'counts' => [
                        'pins' => $board['counts']['pins'] ?? 0,
                        'collaborators' => $board['counts']['collaborators'] ?? 0,
                        'followers' => $board['counts']['followers'] ?? 0,
                    ],
                    'created_at' => $board['created_at'],
                    'privacy' => $board['privacy'] ?? 'public',
                    'reason' => $board['reason'] ?? '',
                ];
            }

            return $boards;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Pinterest board pins.
     *
     * @param string $boardId  Pinterest board ID
     * @param int    $limit    Number of results (1-250)
     * @param string $bookmark Pagination bookmark
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     url: string,
     *     image: array{
     *         original: array{url: string, width: int, height: int},
     *         small: array{url: string, width: int, height: int},
     *         medium: array{url: string, width: int, height: int},
     *     },
     *     board: array{
     *         id: string,
     *         name: string,
     *         url: string,
     *     },
     *     creator: array{
     *         id: string,
     *         username: string,
     *         first_name: string,
     *         last_name: string,
     *     },
     *     counts: array{
     *         saves: int,
     *         comments: int,
     *     },
     *     created_at: string,
     *     link: string,
     *     note: string,
     * }>
     */
    public function getBoardPins(
        string $boardId,
        int $limit = 25,
        string $bookmark = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 250),
            ];

            if ($bookmark) {
                $params['bookmark'] = $bookmark;
            }

            $response = $this->httpClient->request('GET', "https://api.pinterest.com/{$this->apiVersion}/boards/{$boardId}/pins", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $pins = [];
            foreach ($data['data'] as $pin) {
                $pins[] = [
                    'id' => $pin['id'],
                    'title' => $pin['title'] ?? '',
                    'description' => $pin['description'] ?? '',
                    'url' => $pin['url'] ?? '',
                    'image' => [
                        'original' => [
                            'url' => $pin['image']['original']['url'] ?? '',
                            'width' => $pin['image']['original']['width'] ?? 0,
                            'height' => $pin['image']['original']['height'] ?? 0,
                        ],
                        'small' => [
                            'url' => $pin['image']['small']['url'] ?? '',
                            'width' => $pin['image']['small']['width'] ?? 0,
                            'height' => $pin['image']['small']['height'] ?? 0,
                        ],
                        'medium' => [
                            'url' => $pin['image']['medium']['url'] ?? '',
                            'width' => $pin['image']['medium']['width'] ?? 0,
                            'height' => $pin['image']['medium']['height'] ?? 0,
                        ],
                    ],
                    'board' => [
                        'id' => $pin['board']['id'] ?? '',
                        'name' => $pin['board']['name'] ?? '',
                        'url' => $pin['board']['url'] ?? '',
                    ],
                    'creator' => [
                        'id' => $pin['creator']['id'] ?? '',
                        'username' => $pin['creator']['username'] ?? '',
                        'first_name' => $pin['creator']['first_name'] ?? '',
                        'last_name' => $pin['creator']['last_name'] ?? '',
                    ],
                    'counts' => [
                        'saves' => $pin['counts']['saves'] ?? 0,
                        'comments' => $pin['counts']['comments'] ?? 0,
                    ],
                    'created_at' => $pin['created_at'] ?? '',
                    'link' => $pin['link'] ?? '',
                    'note' => $pin['note'] ?? '',
                ];
            }

            return $pins;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Pinterest pin.
     *
     * @param string $boardId     Pinterest board ID
     * @param string $imageUrl    URL of the image to pin
     * @param string $title       Pin title
     * @param string $description Pin description
     * @param string $link        Optional link URL
     * @param string $note        Optional note
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     url: string,
     *     image: array{
     *         original: array{url: string, width: int, height: int},
     *     },
     *     board: array{
     *         id: string,
     *         name: string,
     *     },
     *     creator: array{
     *         id: string,
     *         username: string,
     *     },
     *     counts: array{
     *         saves: int,
     *         comments: int,
     *     },
     *     created_at: string,
     *     link: string,
     *     note: string,
     * }|string
     */
    public function createPin(
        string $boardId,
        string $imageUrl,
        string $title,
        string $description = '',
        string $link = '',
        string $note = '',
    ): array|string {
        try {
            $payload = [
                'board_id' => $boardId,
                'image_url' => $imageUrl,
                'title' => $title,
            ];

            if ($description) {
                $payload['description'] = $description;
            }

            if ($link) {
                $payload['link'] = $link;
            }

            if ($note) {
                $payload['note'] = $note;
            }

            $response = $this->httpClient->request('POST', "https://api.pinterest.com/{$this->apiVersion}/pins", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating pin: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'url' => $data['url'],
                'image' => [
                    'original' => [
                        'url' => $data['image']['original']['url'],
                        'width' => $data['image']['original']['width'],
                        'height' => $data['image']['original']['height'],
                    ],
                ],
                'board' => [
                    'id' => $data['board']['id'],
                    'name' => $data['board']['name'],
                ],
                'creator' => [
                    'id' => $data['creator']['id'],
                    'username' => $data['creator']['username'],
                ],
                'counts' => [
                    'saves' => $data['counts']['saves'] ?? 0,
                    'comments' => $data['counts']['comments'] ?? 0,
                ],
                'created_at' => $data['created_at'],
                'link' => $data['link'] ?? '',
                'note' => $data['note'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error creating pin: '.$e->getMessage();
        }
    }

    /**
     * Get Pinterest user information.
     *
     * @param string $username Pinterest username
     *
     * @return array{
     *     id: string,
     *     username: string,
     *     first_name: string,
     *     last_name: string,
     *     bio: string,
     *     created_at: string,
     *     counts: array{
     *         pins: int,
     *         following: int,
     *         followers: int,
     *         boards: int,
     *         likes: int,
     *     },
     *     image: array{
     *         small: array{url: string},
     *         large: array{url: string},
     *     },
     *     website: string,
     *     location: string,
     *     account_type: string,
     * }|string
     */
    public function getUserInfo(string $username): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.pinterest.com/{$this->apiVersion}/users/{$username}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting user info: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'username' => $data['username'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'bio' => $data['bio'] ?? '',
                'created_at' => $data['created_at'],
                'counts' => [
                    'pins' => $data['counts']['pins'] ?? 0,
                    'following' => $data['counts']['following'] ?? 0,
                    'followers' => $data['counts']['followers'] ?? 0,
                    'boards' => $data['counts']['boards'] ?? 0,
                    'likes' => $data['counts']['likes'] ?? 0,
                ],
                'image' => [
                    'small' => [
                        'url' => $data['image']['small']['url'] ?? '',
                    ],
                    'large' => [
                        'url' => $data['image']['large']['url'] ?? '',
                    ],
                ],
                'website' => $data['website'] ?? '',
                'location' => $data['location'] ?? '',
                'account_type' => $data['account_type'] ?? 'personal',
            ];
        } catch (\Exception $e) {
            return 'Error getting user info: '.$e->getMessage();
        }
    }

    /**
     * Search Pinterest boards.
     *
     * @param string $query    Search query
     * @param int    $limit    Number of results (1-250)
     * @param string $bookmark Pagination bookmark
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     url: string,
     *     creator: array{
     *         id: string,
     *         username: string,
     *         first_name: string,
     *         last_name: string,
     *     },
     *     counts: array{
     *         pins: int,
     *         collaborators: int,
     *         followers: int,
     *     },
     *     created_at: string,
     *     privacy: string,
     * }>
     */
    public function searchBoards(
        #[With(maximum: 500)]
        string $query,
        int $limit = 25,
        string $bookmark = '',
    ): array {
        try {
            $params = [
                'query' => $query,
                'limit' => min(max($limit, 1), 250),
            ];

            if ($bookmark) {
                $params['bookmark'] = $bookmark;
            }

            $response = $this->httpClient->request('GET', "https://api.pinterest.com/{$this->apiVersion}/search/boards", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['data'])) {
                return [];
            }

            $boards = [];
            foreach ($data['data'] as $board) {
                $boards[] = [
                    'id' => $board['id'],
                    'name' => $board['name'],
                    'description' => $board['description'] ?? '',
                    'url' => $board['url'],
                    'creator' => [
                        'id' => $board['creator']['id'] ?? '',
                        'username' => $board['creator']['username'] ?? '',
                        'first_name' => $board['creator']['first_name'] ?? '',
                        'last_name' => $board['creator']['last_name'] ?? '',
                    ],
                    'counts' => [
                        'pins' => $board['counts']['pins'] ?? 0,
                        'collaborators' => $board['counts']['collaborators'] ?? 0,
                        'followers' => $board['counts']['followers'] ?? 0,
                    ],
                    'created_at' => $board['created_at'],
                    'privacy' => $board['privacy'] ?? 'public',
                ];
            }

            return $boards;
        } catch (\Exception $e) {
            return [];
        }
    }
}
