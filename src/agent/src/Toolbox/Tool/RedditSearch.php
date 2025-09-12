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
#[AsTool('reddit_search', 'Tool that searches for posts on Reddit')]
final readonly class RedditSearch
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private ?string $clientId = null,
        #[\SensitiveParameter] private ?string $clientSecret = null,
        #[\SensitiveParameter] private ?string $userAgent = null,
        private array $options = [],
    ) {
    }

    /**
     * Search for posts on Reddit.
     *
     * @param string $query      Query string that post title should contain, or '*' if anything is allowed
     * @param string $sort       Sort method: "relevance", "hot", "top", "new", or "comments"
     * @param string $timeFilter Time period to filter by: "all", "day", "hour", "month", "week", or "year"
     * @param string $subreddit  Name of subreddit, like "all" for r/all
     * @param int    $limit      Maximum number of results to return
     *
     * @return array<int, array{
     *     title: string,
     *     author: string,
     *     score: int,
     *     num_comments: int,
     *     created_utc: string,
     *     url: string,
     *     permalink: string,
     *     subreddit: string,
     *     selftext: string,
     *     is_self: bool,
     *     thumbnail: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        string $sort = 'relevance',
        string $timeFilter = 'all',
        string $subreddit = 'all',
        int $limit = 10,
    ): array {
        try {
            // Use Reddit's JSON API (no authentication required for public data)
            $url = $this->buildRedditUrl($query, $sort, $timeFilter, $subreddit, $limit);

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => $this->userAgent ?? 'Symfony-AI-Agent/1.0',
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['data']['children'])) {
                return [];
            }

            $results = [];
            foreach ($data['data']['children'] as $post) {
                $postData = $post['data'];

                $results[] = [
                    'title' => $postData['title'] ?? '',
                    'author' => $postData['author'] ?? '',
                    'score' => $postData['score'] ?? 0,
                    'num_comments' => $postData['num_comments'] ?? 0,
                    'created_utc' => date('Y-m-d H:i:s', $postData['created_utc'] ?? 0),
                    'url' => $postData['url'] ?? '',
                    'permalink' => 'https://reddit.com'.($postData['permalink'] ?? ''),
                    'subreddit' => $postData['subreddit'] ?? '',
                    'selftext' => $postData['selftext'] ?? '',
                    'is_self' => $postData['is_self'] ?? false,
                    'thumbnail' => $postData['thumbnail'] ?? '',
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'title' => 'Search Error',
                    'author' => '',
                    'score' => 0,
                    'num_comments' => 0,
                    'created_utc' => '',
                    'url' => '',
                    'permalink' => '',
                    'subreddit' => '',
                    'selftext' => 'Unable to search Reddit: '.$e->getMessage(),
                    'is_self' => false,
                    'thumbnail' => '',
                ],
            ];
        }
    }

    /**
     * Get popular posts from a subreddit.
     *
     * @param string $subreddit Name of subreddit
     * @param int    $limit     Maximum number of results to return
     *
     * @return array<int, array{
     *     title: string,
     *     author: string,
     *     score: int,
     *     num_comments: int,
     *     created_utc: string,
     *     url: string,
     *     permalink: string,
     *     subreddit: string,
     *     selftext: string,
     *     is_self: bool,
     *     thumbnail: string,
     * }>
     */
    public function getPopularPosts(string $subreddit = 'all', int $limit = 10): array
    {
        return $this->__invoke('*', 'hot', 'day', $subreddit, $limit);
    }

    /**
     * Get top posts from a subreddit.
     *
     * @param string $subreddit  Name of subreddit
     * @param string $timeFilter Time period: "all", "day", "hour", "month", "week", or "year"
     * @param int    $limit      Maximum number of results to return
     *
     * @return array<int, array{
     *     title: string,
     *     author: string,
     *     score: int,
     *     num_comments: int,
     *     created_utc: string,
     *     url: string,
     *     permalink: string,
     *     subreddit: string,
     *     selftext: string,
     *     is_self: bool,
     *     thumbnail: string,
     * }>
     */
    public function getTopPosts(string $subreddit = 'all', string $timeFilter = 'week', int $limit = 10): array
    {
        return $this->__invoke('*', 'top', $timeFilter, $subreddit, $limit);
    }

    /**
     * Build Reddit API URL for search.
     */
    private function buildRedditUrl(string $query, string $sort, string $timeFilter, string $subreddit, int $limit): string
    {
        $baseUrl = 'https://www.reddit.com/r/'.$subreddit.'/search.json';

        $params = [
            'q' => $query,
            'sort' => $sort,
            't' => $timeFilter,
            'limit' => $limit,
            'restrict_sr' => 'all' !== $subreddit ? 'true' : 'false',
            'raw_json' => '1',
        ];

        // Clean up query parameter
        if ('*' === $query) {
            unset($params['q']);
        }

        return $baseUrl.'?'.http_build_query($params);
    }
}
