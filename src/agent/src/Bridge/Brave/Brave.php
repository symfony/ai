<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Brave;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('brave_search', 'Tool that searches the web using Brave Search')]
final readonly class Brave
{
    /**
     * @param array<string, mixed> $options See https://api-dashboard.search.brave.com/app/documentation/web-search/query#WebSearchAPIQueryParameters
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
     *     description: string,
     *     url: string,
     * }>
     */
    public function __invoke(
        #[With(maxLength: 500)]
        string $query,
        int $count = 20,
        #[With(minimum: 0, maximum: 9)]
        int $offset = 0,
    ): array {
        $result = $this->httpClient->request('GET', 'https://api.search.brave.com/res/v1/web/search', [
            'headers' => ['X-Subscription-Token' => $this->apiKey],
            'query' => array_merge($this->options, [
                'q' => $query,
                'count' => $count,
                'offset' => $offset,
            ]),
        ]);

        $data = $result->toArray();

        $results = [];
        if (isset($data['web']) && \is_array($data['web']) && isset($data['web']['results']) && \is_array($data['web']['results'])) {
            $results = $data['web']['results'];
        }

        return array_values(array_map(static function (mixed $result): array {
            if (!\is_array($result)) {
                return ['title' => '', 'description' => '', 'url' => ''];
            }

            return [
                'title' => \is_string($result['title'] ?? null) ? $result['title'] : '',
                'description' => \is_string($result['description'] ?? null) ? $result['description'] : '',
                'url' => \is_string($result['url'] ?? null) ? $result['url'] : '',
            ];
        }, $results));
    }
}
