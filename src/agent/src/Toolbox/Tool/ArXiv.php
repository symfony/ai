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
#[AsTool('arxiv_search', 'Tool that searches the ArXiv API for scientific papers')]
final readonly class ArXiv
{
    /**
     * @param array<string, mixed> $options Additional search options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private array $options = [],
    ) {
    }

    /**
     * @param string $query      search query to look up on ArXiv
     * @param int    $maxResults The maximum number of results to return
     * @param int    $start      The starting position for results (for pagination)
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     authors: array<int, string>,
     *     summary: string,
     *     published: string,
     *     updated: string,
     *     categories: array<int, string>,
     *     link: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 10,
        #[With(minimum: 0)]
        int $start = 0,
    ): array {
        try {
            // Check if query is an ArXiv identifier
            if ($this->isArxivIdentifier($query)) {
                return $this->searchById($query);
            }

            return $this->searchByQuery($query, $maxResults, $start);
        } catch (\Exception $e) {
            return [
                [
                    'id' => 'error',
                    'title' => 'Search Error',
                    'authors' => [],
                    'summary' => 'Unable to perform ArXiv search: '.$e->getMessage(),
                    'published' => '',
                    'updated' => '',
                    'categories' => [],
                    'link' => '',
                ],
            ];
        }
    }

    /**
     * Check if a query is an ArXiv identifier.
     */
    private function isArxivIdentifier(string $query): bool
    {
        // ArXiv identifier patterns
        $patterns = [
            '/^\d{4}\.\d{4,5}(v\d+)?$/',  // YYMM.NNNN or YYMM.NNNNvN
            '/^\d{7}\.\d+$/',             // YYMMNNN.N
            '/^[a-z-]+\/\d{7}$/',         // category/YYMMNNN
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Search ArXiv by specific paper ID.
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     authors: array<int, string>,
     *     summary: string,
     *     published: string,
     *     updated: string,
     *     categories: array<int, string>,
     *     link: string,
     * }>
     */
    private function searchById(string $id): array
    {
        $response = $this->httpClient->request('GET', 'http://export.arxiv.org/api/query', [
            'query' => [
                'id_list' => $id,
                'max_results' => 1,
            ],
        ]);

        $xml = $response->getContent();

        return $this->parseArxivXml($xml);
    }

    /**
     * Search ArXiv by query string.
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     authors: array<int, string>,
     *     summary: string,
     *     published: string,
     *     updated: string,
     *     categories: array<int, string>,
     *     link: string,
     * }>
     */
    private function searchByQuery(string $query, int $maxResults, int $start): array
    {
        $response = $this->httpClient->request('GET', 'http://export.arxiv.org/api/query', [
            'query' => array_merge($this->options, [
                'search_query' => $query,
                'start' => $start,
                'max_results' => $maxResults,
                'sortBy' => 'relevance',
                'sortOrder' => 'descending',
            ]),
        ]);

        $xml = $response->getContent();

        return $this->parseArxivXml($xml);
    }

    /**
     * Parse ArXiv XML response.
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     authors: array<int, string>,
     *     summary: string,
     *     published: string,
     *     updated: string,
     *     categories: array<int, string>,
     *     link: string,
     * }>
     */
    private function parseArxivXml(string $xml): array
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);

            $entries = $dom->getElementsByTagName('entry');
            $results = [];

            foreach ($entries as $entry) {
                $id = $this->getElementText($entry, 'id');
                $title = $this->getElementText($entry, 'title');
                $summary = $this->getElementText($entry, 'summary');
                $published = $this->getElementText($entry, 'published');
                $updated = $this->getElementText($entry, 'updated');

                // Extract authors
                $authors = [];
                $authorNodes = $entry->getElementsByTagName('author');
                foreach ($authorNodes as $authorNode) {
                    $name = $this->getElementText($authorNode, 'name');
                    if ($name) {
                        $authors[] = $name;
                    }
                }

                // Extract categories
                $categories = [];
                $categoryNodes = $entry->getElementsByTagName('category');
                foreach ($categoryNodes as $categoryNode) {
                    $term = $categoryNode->getAttribute('term');
                    if ($term) {
                        $categories[] = $term;
                    }
                }

                // Extract link
                $link = '';
                $linkNodes = $entry->getElementsByTagName('link');
                foreach ($linkNodes as $linkNode) {
                    if ('pdf' === $linkNode->getAttribute('title')) {
                        $link = $linkNode->getAttribute('href');
                        break;
                    }
                }

                $results[] = [
                    'id' => $id,
                    'title' => $title,
                    'authors' => $authors,
                    'summary' => trim($summary),
                    'published' => $published,
                    'updated' => $updated,
                    'categories' => $categories,
                    'link' => $link,
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'id' => 'parse_error',
                    'title' => 'Parse Error',
                    'authors' => [],
                    'summary' => 'Unable to parse ArXiv response: '.$e->getMessage(),
                    'published' => '',
                    'updated' => '',
                    'categories' => [],
                    'link' => '',
                ],
            ];
        }
    }

    private function getElementText(\DOMElement $parent, string $tagName): string
    {
        $elements = $parent->getElementsByTagName($tagName);
        if ($elements->length > 0) {
            return $elements->item(0)->textContent ?? '';
        }

        return '';
    }
}
