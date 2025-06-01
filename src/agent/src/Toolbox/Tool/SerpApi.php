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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool(name: 'serpapi', description: 'search for information on the internet')]
final readonly class SerpApi
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {
    }

    /**
     * @param string $query The search query to use
     */
    public function __invoke(string $query): string
    {
        $response = $this->httpClient->request('GET', 'https://serpapi.com/search', [
            'query' => [
                'q' => $query,
                'api_key' => $this->apiKey,
            ],
        ]);

        return \sprintf('Results for "%s" are "%s".', $query, $this->extractBestResponse($response->toArray()));
    }

    /**
     * @param array<string, mixed> $results
     */
    private function extractBestResponse(array $results): string
    {
        return implode('. ', array_map(fn ($story) => $story['title'], $results['organic_results']));
    }
}
