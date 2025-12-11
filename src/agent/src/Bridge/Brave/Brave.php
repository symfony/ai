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
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('brave_search', 'Tool that searches the web using Brave Search')]
final class Brave implements HasSourcesInterface
{
    use HasSourcesTrait;

    /**
     * @param array<string, mixed> $options See https://api-dashboard.search.brave.com/app/documentation/web-search/query#WebSearchAPIQueryParameters
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly array $options = [],
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
        $results = $data['web']['results'] ?? [];

        foreach ($results as $result) {
            $this->addSource(
                new Source($result['title'] ?? '', $result['url'] ?? '', $result['description'] ?? '')
            );
        }

        return array_map(static function (array $result) {
            return ['title' => $result['title'], 'description' => $result['description'], 'url' => $result['url']];
        }, $data['web']['results'] ?? []);
    }
}
