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
#[AsTool('google_search', 'Tool that searches the web using Google Search API')]
#[AsTool('google_search_results_json', 'Tool that searches Google and returns structured JSON results', method: 'searchJson')]
final readonly class GoogleSearch
{
    /**
     * @param array<string, mixed> $options Additional search options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        #[\SensitiveParameter] private string $searchEngineId,
        private array $options = [],
    ) {
    }

    /**
     * @param string $query  the search query term
     * @param int    $count  The number of search results returned in response.
     *                       Combine this parameter with offset to paginate search results.
     * @param int    $offset The number of search results to skip before returning results.
     *                       In order to paginate results use this parameter together with count.
     *
     * @return array<int, array{
     *     title: string,
     *     snippet: string,
     *     link: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $count = 10,
        #[With(minimum: 0, maximum: 9)]
        int $offset = 0,
    ): array {
        $result = $this->httpClient->request('GET', 'https://www.googleapis.com/customsearch/v1', [
            'query' => array_merge($this->options, [
                'key' => $this->apiKey,
                'cx' => $this->searchEngineId,
                'q' => $query,
                'num' => $count,
                'start' => $offset + 1,
            ]),
        ]);

        $data = $result->toArray();

        return array_map(static function (array $result) {
            return [
                'title' => $result['title'] ?? '',
                'snippet' => $result['snippet'] ?? '',
                'link' => $result['link'] ?? '',
            ];
        }, $data['items'] ?? []);
    }

    /**
     * @param string $query      the search query term
     * @param int    $numResults The number of search results to return
     *
     * @return array<int, array{
     *     title: string,
     *     snippet: string,
     *     link: string,
     * }>
     */
    public function searchJson(
        #[With(maximum: 500)]
        string $query,
        int $numResults = 4,
    ): array {
        return $this->__invoke($query, $numResults);
    }
}
