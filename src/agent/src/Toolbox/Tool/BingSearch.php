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
#[AsTool('bing_search', 'Tool that searches the web using Bing Search API')]
#[AsTool('bing_search_results_json', 'Tool that searches Bing and returns structured JSON results', method: 'searchJson')]
final readonly class BingSearch
{
    /**
     * @param array<string, mixed> $options Additional search options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
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
     *     url: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $count = 10,
        #[With(minimum: 0, maximum: 9)]
        int $offset = 0,
    ): array {
        try {
            $response = $this->httpClient->request('GET', 'https://api.bing.microsoft.com/v7.0/search', [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->apiKey,
                ],
                'query' => array_merge($this->options, [
                    'q' => $query,
                    'count' => $count,
                    'offset' => $offset,
                    'mkt' => 'en-US',
                    'safesearch' => 'Moderate',
                ]),
            ]);

            $data = $response->toArray();

            $results = [];
            foreach ($data['webPages']['value'] ?? [] as $result) {
                $results[] = [
                    'title' => $result['name'] ?? '',
                    'snippet' => $result['snippet'] ?? '',
                    'url' => $result['url'] ?? '',
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'title' => 'Search Error',
                    'snippet' => 'Unable to perform search: '.$e->getMessage(),
                    'url' => '',
                ],
            ];
        }
    }

    /**
     * @param string $query      the search query term
     * @param int    $numResults The number of search results to return
     *
     * @return array<int, array{
     *     title: string,
     *     snippet: string,
     *     url: string,
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
