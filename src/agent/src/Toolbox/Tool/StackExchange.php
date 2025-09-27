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
#[AsTool('stack_exchange', 'Tool that searches StackExchange for programming questions and answers')]
#[AsTool('stack_exchange_questions', 'Tool that gets questions from StackExchange', method: 'getQuestions')]
#[AsTool('stack_exchange_answers', 'Tool that gets answers for a specific question', method: 'getAnswers')]
final readonly class StackExchange
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private ?string $apiKey = null,
        private string $site = 'stackoverflow',
        private array $options = [],
    ) {
    }

    /**
     * Search StackExchange for programming questions and answers.
     *
     * @param string $query search query for programming questions
     * @param int    $limit Maximum number of results to return
     * @param string $sort  Sort order: "relevance", "activity", "votes", "creation", "hot", "week", "month"
     *
     * @return array<int, array{
     *     question_id: int,
     *     title: string,
     *     body: string,
     *     score: int,
     *     view_count: int,
     *     answer_count: int,
     *     accepted_answer_id: int|null,
     *     creation_date: string,
     *     last_activity_date: string,
     *     owner: array{display_name: string, reputation: int},
     *     tags: array<int, string>,
     *     link: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $limit = 10,
        string $sort = 'relevance',
    ): array {
        try {
            $response = $this->httpClient->request('GET', 'https://api.stackexchange.com/2.3/search/advanced', [
                'query' => array_merge($this->options, [
                    'q' => $query,
                    'site' => $this->site,
                    'sort' => $sort,
                    'order' => 'desc',
                    'pagesize' => $limit,
                    'filter' => 'withbody',
                    'key' => $this->apiKey,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $results = [];
            foreach ($data['items'] as $question) {
                $results[] = [
                    'question_id' => $question['question_id'],
                    'title' => $question['title'],
                    'body' => $this->cleanHtml($question['body']),
                    'score' => $question['score'],
                    'view_count' => $question['view_count'],
                    'answer_count' => $question['answer_count'],
                    'accepted_answer_id' => $question['accepted_answer_id'] ?? null,
                    'creation_date' => date('Y-m-d H:i:s', $question['creation_date']),
                    'last_activity_date' => date('Y-m-d H:i:s', $question['last_activity_date']),
                    'owner' => [
                        'display_name' => $question['owner']['display_name'] ?? 'Unknown',
                        'reputation' => $question['owner']['reputation'] ?? 0,
                    ],
                    'tags' => $question['tags'] ?? [],
                    'link' => $question['link'],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'question_id' => 0,
                    'title' => 'Search Error',
                    'body' => 'Unable to search StackExchange: '.$e->getMessage(),
                    'score' => 0,
                    'view_count' => 0,
                    'answer_count' => 0,
                    'accepted_answer_id' => null,
                    'creation_date' => '',
                    'last_activity_date' => '',
                    'owner' => ['display_name' => '', 'reputation' => 0],
                    'tags' => [],
                    'link' => '',
                ],
            ];
        }
    }

    /**
     * Get questions from StackExchange.
     *
     * @param string $tag        Tag to filter questions by (optional)
     * @param int    $limit      Maximum number of results to return
     * @param string $sort       Sort order: "activity", "votes", "creation", "hot", "week", "month"
     * @param string $timeFilter Time filter: "all", "week", "month", "quarter", "year"
     *
     * @return array<int, array{
     *     question_id: int,
     *     title: string,
     *     body: string,
     *     score: int,
     *     view_count: int,
     *     answer_count: int,
     *     accepted_answer_id: int|null,
     *     creation_date: string,
     *     last_activity_date: string,
     *     owner: array{display_name: string, reputation: int},
     *     tags: array<int, string>,
     *     link: string,
     * }>
     */
    public function getQuestions(
        string $tag = '',
        int $limit = 10,
        string $sort = 'activity',
        string $timeFilter = 'all',
    ): array {
        try {
            $params = [
                'site' => $this->site,
                'sort' => $sort,
                'order' => 'desc',
                'pagesize' => $limit,
                'filter' => 'withbody',
                'key' => $this->apiKey,
            ];

            if ($tag) {
                $params['tagged'] = $tag;
            }

            if ('all' !== $timeFilter) {
                $params['fromdate'] = $this->getTimestampForFilter($timeFilter);
            }

            $response = $this->httpClient->request('GET', 'https://api.stackexchange.com/2.3/questions', [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $results = [];
            foreach ($data['items'] as $question) {
                $results[] = [
                    'question_id' => $question['question_id'],
                    'title' => $question['title'],
                    'body' => $this->cleanHtml($question['body']),
                    'score' => $question['score'],
                    'view_count' => $question['view_count'],
                    'answer_count' => $question['answer_count'],
                    'accepted_answer_id' => $question['accepted_answer_id'] ?? null,
                    'creation_date' => date('Y-m-d H:i:s', $question['creation_date']),
                    'last_activity_date' => date('Y-m-d H:i:s', $question['last_activity_date']),
                    'owner' => [
                        'display_name' => $question['owner']['display_name'] ?? 'Unknown',
                        'reputation' => $question['owner']['reputation'] ?? 0,
                    ],
                    'tags' => $question['tags'] ?? [],
                    'link' => $question['link'],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'question_id' => 0,
                    'title' => 'Error',
                    'body' => 'Unable to get questions: '.$e->getMessage(),
                    'score' => 0,
                    'view_count' => 0,
                    'answer_count' => 0,
                    'accepted_answer_id' => null,
                    'creation_date' => '',
                    'last_activity_date' => '',
                    'owner' => ['display_name' => '', 'reputation' => 0],
                    'tags' => [],
                    'link' => '',
                ],
            ];
        }
    }

    /**
     * Get answers for a specific question.
     *
     * @param int    $questionId The question ID
     * @param string $sort       Sort order: "activity", "creation", "votes"
     * @param int    $limit      Maximum number of results to return
     *
     * @return array<int, array{
     *     answer_id: int,
     *     question_id: int,
     *     body: string,
     *     score: int,
     *     is_accepted: bool,
     *     creation_date: string,
     *     last_activity_date: string,
     *     owner: array{display_name: string, reputation: int},
     *     link: string,
     * }>
     */
    public function getAnswers(int $questionId, string $sort = 'votes', int $limit = 10): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.stackexchange.com/2.3/questions/{$questionId}/answers", [
                'query' => array_merge($this->options, [
                    'site' => $this->site,
                    'sort' => $sort,
                    'order' => 'desc',
                    'pagesize' => $limit,
                    'filter' => 'withbody',
                    'key' => $this->apiKey,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $results = [];
            foreach ($data['items'] as $answer) {
                $results[] = [
                    'answer_id' => $answer['answer_id'],
                    'question_id' => $answer['question_id'],
                    'body' => $this->cleanHtml($answer['body']),
                    'score' => $answer['score'],
                    'is_accepted' => $answer['is_accepted'],
                    'creation_date' => date('Y-m-d H:i:s', $answer['creation_date']),
                    'last_activity_date' => date('Y-m-d H:i:s', $answer['last_activity_date']),
                    'owner' => [
                        'display_name' => $answer['owner']['display_name'] ?? 'Unknown',
                        'reputation' => $answer['owner']['reputation'] ?? 0,
                    ],
                    'link' => $answer['link'],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'answer_id' => 0,
                    'question_id' => $questionId,
                    'body' => 'Unable to get answers: '.$e->getMessage(),
                    'score' => 0,
                    'is_accepted' => false,
                    'creation_date' => '',
                    'last_activity_date' => '',
                    'owner' => ['display_name' => '', 'reputation' => 0],
                    'link' => '',
                ],
            ];
        }
    }

    /**
     * Clean HTML from StackExchange content.
     */
    private function cleanHtml(string $html): string
    {
        // Remove HTML tags and decode entities
        $text = strip_tags($html);
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        // Clean up extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Get timestamp for time filter.
     */
    private function getTimestampForFilter(string $filter): int
    {
        $now = time();

        return match ($filter) {
            'week' => $now - (7 * 24 * 60 * 60),
            'month' => $now - (30 * 24 * 60 * 60),
            'quarter' => $now - (90 * 24 * 60 * 60),
            'year' => $now - (365 * 24 * 60 * 60),
            default => 0,
        };
    }
}
