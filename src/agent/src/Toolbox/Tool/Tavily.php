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
 * Tool integration of tavily.com.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('tavily_search', description: 'search for information on the internet', method: 'search')]
#[AsTool('tavily_extract', description: 'fetch content from websites', method: 'extract')]
final readonly class Tavily
{
    /**
     * @param array<string, string|string[]|int|bool> $options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private array $options = ['include_images' => false],
    ) {
    }

    /**
     * @param string $query The search query to use
     */
    public function search(string $query): string
    {
        $result = $this->httpClient->request('POST', 'https://api.tavily.com/search', [
            'json' => array_merge($this->options, [
                'query' => $query,
                'api_key' => $this->apiKey,
            ]),
        ]);

        return $result->getContent();
    }

    /**
     * @param string[] $urls URLs to fetch information from
     */
    public function extract(array $urls): string
    {
        $result = $this->httpClient->request('POST', 'https://api.tavily.com/extract', [
            'json' => [
                'urls' => $urls,
                'api_key' => $this->apiKey,
            ],
        ]);

        return $result->getContent();
    }
}
