<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Ollama;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AsTool('web_search', description: 'perform a web search using Ollama', method: 'webSearch')]
#[AsTool('fetch_webpage', description: 'fetch the content of a webpage using Ollama', method: 'fetchWebPage')]
final class Ollama implements HasSourcesInterface
{
    use HasSourcesTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly string $endpoint = 'https://ollama.com/api',
    ) {
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     url: string,
     *     content: string,
     * }>
     */
    public function webSearch(string $query, int $maxResults = 5): array
    {
        $response = $this->request('POST', 'web_search', [
            'query' => $query,
            'max_results' => $maxResults,
        ]);

        $content = $response->toArray();

        return $content['results'];
    }

    /**
     * @return array{
     *     title: string,
     *     content: string,
     *     links: string[],
     * }
     */
    public function fetchWebPage(string $url): array
    {
        $response = $this->request('POST', 'web_fetch', [
            'url' => $url,
        ]);

        $payload = $response->toArray();

        $this->addSource(
            new Source($payload['title'], implode(', ', $payload['links']), $payload['content'])
        );

        return $payload;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $endpoint, array $options): ResponseInterface
    {
        return match (true) {
            !$this->httpClient instanceof ScopingHttpClient && null === $this->apiKey => throw new InvalidArgumentException('The api key must be set when using a non-scoping http client.'),
            $this->httpClient instanceof ScopingHttpClient => $this->httpClient->request($method, $endpoint, [
                'json' => $options,
            ]),
            default => $this->httpClient->request($method, \sprintf('%s/%s', $this->endpoint, $endpoint), [
                'auth_bearer' => $this->apiKey,
                'json' => $options,
            ]),
        };
    }
}
