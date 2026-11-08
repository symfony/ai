<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\SpecStream;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tool integration of specstream.ai.
 *
 * @see https://specstream.ai/
 */
#[AsTool('specstream_search', description: 'Search the live web and return an answer when there is one, with matching pages and their snippets', method: 'search')]
#[AsTool('specstream_extract', description: 'Read the main text of web pages or documents from their URLs', method: 'extract')]
final class SpecStream implements HasSourcesInterface
{
    use HasSourcesTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.specstream.ai',
    ) {
    }

    /**
     * @param string $query The question or search terms, in everyday words
     *
     * @return array{
     *     answer: string|null,
     *     results: array<int, array{title: string, url: string, snippet: string}>,
     * }
     */
    public function search(
        #[Schema(minLength: 1, maxLength: 2000)]
        string $query,
    ): array {
        $response = $this->httpClient->request('GET', \sprintf('%s/v1/web-search', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'query' => ['q' => $query],
        ]);

        $data = $response->toArray();

        $results = [];
        foreach ($data['sources'] ?? [] as $source) {
            if (!isset($source['url'])) {
                continue;
            }

            $result = [
                'title' => $source['title'] ?? '',
                'url' => $source['url'],
                'snippet' => $source['snippet'] ?? '',
            ];

            $this->addSource(new Source($result['title'], $result['url'], $result['snippet']));
            $results[] = $result;
        }

        $answer = $data['answer']['value'] ?? null;

        return [
            'answer' => \is_string($answer) ? $answer : null,
            'results' => $results,
        ];
    }

    /**
     * @param string[] $urls The http or https URLs to read, 1 to 20
     *
     * @return array<int, array{url: string, status: string, title: string, text: string}>
     */
    public function extract(
        #[Schema(minItems: 1, maxItems: 20)]
        array $urls,
    ): array {
        $response = $this->httpClient->request('POST', \sprintf('%s/v1/extract', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'urls' => array_values($urls),
                'format' => 'markdown',
            ],
        ]);

        $pages = [];
        foreach ($response->toArray()['results'] ?? [] as $item) {
            $page = [
                'url' => $item['url'] ?? '',
                'status' => $item['status'] ?? 'failed',
                'title' => $item['title'] ?? '',
                'text' => $item['text'] ?? '',
            ];

            if ('ok' === $page['status']) {
                $this->addSource(new Source($page['title'], $page['url'], $page['text']));
            }

            $pages[] = $page;
        }

        return $pages;
    }
}
