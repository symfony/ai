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
#[AsTool('youtube_search', 'Tool that searches YouTube for videos')]
#[AsTool('youtube_search_detailed', 'Tool that searches YouTube with detailed information', method: 'searchDetailed')]
final readonly class YouTubeSearch
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private array $options = [],
    ) {
    }

    /**
     * Search YouTube for videos.
     *
     * @param string $query      Search query or person name
     * @param int    $maxResults Maximum number of results to return
     * @param string $order      Sort order: relevance, date, rating, title, videoCount, viewCount
     *
     * @return array<int, array{
     *     video_id: string,
     *     title: string,
     *     description: string,
     *     channel_title: string,
     *     published_at: string,
     *     duration: string,
     *     view_count: int,
     *     like_count: int,
     *     url: string,
     *     thumbnail_url: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 10,
        string $order = 'relevance',
    ): array {
        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/youtube/v3/search', [
                'query' => array_merge($this->options, [
                    'part' => 'snippet',
                    'q' => $query,
                    'type' => 'video',
                    'maxResults' => $maxResults,
                    'order' => $order,
                    'key' => $this->apiKey,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $videoIds = array_map(fn ($item) => $item['id']['videoId'], $data['items']);

            // Get detailed information for each video
            return $this->getVideoDetails($videoIds, $data['items']);
        } catch (\Exception $e) {
            return [
                [
                    'video_id' => 'error',
                    'title' => 'Search Error',
                    'description' => 'Unable to search YouTube: '.$e->getMessage(),
                    'channel_title' => '',
                    'published_at' => '',
                    'duration' => '',
                    'view_count' => 0,
                    'like_count' => 0,
                    'url' => '',
                    'thumbnail_url' => '',
                ],
            ];
        }
    }

    /**
     * Search YouTube with detailed information.
     *
     * @param string $query      Search query or person name
     * @param int    $maxResults Maximum number of results to return
     * @param string $order      Sort order: relevance, date, rating, title, videoCount, viewCount
     *
     * @return array<int, array{
     *     video_id: string,
     *     title: string,
     *     description: string,
     *     channel_title: string,
     *     published_at: string,
     *     duration: string,
     *     view_count: int,
     *     like_count: int,
     *     comment_count: int,
     *     url: string,
     *     thumbnail_url: string,
     *     tags: array<int, string>,
     *     category_id: string,
     * }>
     */
    public function searchDetailed(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 10,
        string $order = 'relevance',
    ): array {
        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/youtube/v3/search', [
                'query' => array_merge($this->options, [
                    'part' => 'snippet',
                    'q' => $query,
                    'type' => 'video',
                    'maxResults' => $maxResults,
                    'order' => $order,
                    'key' => $this->apiKey,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $videoIds = array_map(fn ($item) => $item['id']['videoId'], $data['items']);

            // Get detailed information including statistics
            return $this->getDetailedVideoInfo($videoIds, $data['items']);
        } catch (\Exception $e) {
            return [
                [
                    'video_id' => 'error',
                    'title' => 'Search Error',
                    'description' => 'Unable to search YouTube: '.$e->getMessage(),
                    'channel_title' => '',
                    'published_at' => '',
                    'duration' => '',
                    'view_count' => 0,
                    'like_count' => 0,
                    'comment_count' => 0,
                    'url' => '',
                    'thumbnail_url' => '',
                    'tags' => [],
                    'category_id' => '',
                ],
            ];
        }
    }

    /**
     * Get video details for a list of video IDs.
     *
     * @param array<int, string>               $videoIds
     * @param array<int, array<string, mixed>> $searchItems
     *
     * @return array<int, array<string, mixed>>
     */
    private function getVideoDetails(array $videoIds, array $searchItems): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/youtube/v3/videos', [
                'query' => [
                    'part' => 'contentDetails,statistics',
                    'id' => implode(',', $videoIds),
                    'key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();
            $videoDetails = $data['items'] ?? [];

            $results = [];
            foreach ($searchItems as $index => $item) {
                $videoId = $item['id']['videoId'];
                $details = $this->findVideoDetails($videoDetails, $videoId);

                $results[] = [
                    'video_id' => $videoId,
                    'title' => $item['snippet']['title'],
                    'description' => $item['snippet']['description'],
                    'channel_title' => $item['snippet']['channelTitle'],
                    'published_at' => $item['snippet']['publishedAt'],
                    'duration' => $details['duration'] ?? '',
                    'view_count' => (int) ($details['viewCount'] ?? 0),
                    'like_count' => (int) ($details['likeCount'] ?? 0),
                    'url' => "https://www.youtube.com/watch?v={$videoId}",
                    'thumbnail_url' => $item['snippet']['thumbnails']['high']['url'] ?? '',
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get detailed video information including statistics.
     *
     * @param array<int, string>               $videoIds
     * @param array<int, array<string, mixed>> $searchItems
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDetailedVideoInfo(array $videoIds, array $searchItems): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/youtube/v3/videos', [
                'query' => [
                    'part' => 'contentDetails,statistics,snippet',
                    'id' => implode(',', $videoIds),
                    'key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();
            $videoDetails = $data['items'] ?? [];

            $results = [];
            foreach ($searchItems as $index => $item) {
                $videoId = $item['id']['videoId'];
                $details = $this->findVideoDetails($videoDetails, $videoId);

                $results[] = [
                    'video_id' => $videoId,
                    'title' => $item['snippet']['title'],
                    'description' => $item['snippet']['description'],
                    'channel_title' => $item['snippet']['channelTitle'],
                    'published_at' => $item['snippet']['publishedAt'],
                    'duration' => $details['duration'] ?? '',
                    'view_count' => (int) ($details['viewCount'] ?? 0),
                    'like_count' => (int) ($details['likeCount'] ?? 0),
                    'comment_count' => (int) ($details['commentCount'] ?? 0),
                    'url' => "https://www.youtube.com/watch?v={$videoId}",
                    'thumbnail_url' => $item['snippet']['thumbnails']['high']['url'] ?? '',
                    'tags' => $details['tags'] ?? [],
                    'category_id' => $details['categoryId'] ?? '',
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Find video details by video ID.
     *
     * @param array<int, array<string, mixed>> $videoDetails
     *
     * @return array<string, mixed>
     */
    private function findVideoDetails(array $videoDetails, string $videoId): array
    {
        foreach ($videoDetails as $video) {
            if ($video['id'] === $videoId) {
                return [
                    'duration' => $this->parseDuration($video['contentDetails']['duration'] ?? ''),
                    'viewCount' => $video['statistics']['viewCount'] ?? '0',
                    'likeCount' => $video['statistics']['likeCount'] ?? '0',
                    'commentCount' => $video['statistics']['commentCount'] ?? '0',
                    'tags' => $video['snippet']['tags'] ?? [],
                    'categoryId' => $video['snippet']['categoryId'] ?? '',
                ];
            }
        }

        return [];
    }

    /**
     * Parse ISO 8601 duration to readable format.
     */
    private function parseDuration(string $duration): string
    {
        $duration = preg_replace('/[^0-9HMS]/', '', $duration);

        if (preg_match('/PT(\d+H)?(\d+M)?(\d+S)?/', $duration, $matches)) {
            $hours = isset($matches[1]) ? (int) $matches[1] : 0;
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
            $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

            if ($hours > 0) {
                return \sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
            } else {
                return \sprintf('%d:%02d', $minutes, $seconds);
            }
        }

        return $duration;
    }
}
