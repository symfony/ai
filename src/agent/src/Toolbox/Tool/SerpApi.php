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
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool(name: 'serpapi', description: 'search for information on the internet')]
final class SerpApi implements HasSourcesInterface
{
    use HasSourcesTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {
    }

    /**
     * @param string $query The search query to use
     *
     * @return array{title: string, link: string, content: string}[]
     */
    public function __invoke(string $query): array
    {
        $result = $this->httpClient->request('GET', 'https://serpapi.com/search', [
            'query' => [
                'q' => $query,
                'api_key' => $this->apiKey,
            ],
        ]);

        $data = $result->toArray();

        $results = [];
        foreach ($data['organic_results'] as $result) {
            $results[] = [
                'title' => $result['title'],
                'link' => $result['link'],
                'content' => $result['snippet'],
            ];

            $this->addSource(new Source($result['title'], $result['link'], $result['snippet']));
        }

        return $results;
    }
}
