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
#[AsTool('google_books', 'Tool that searches the Google Books API')]
#[AsTool('google_books_detailed', 'Tool that gets detailed book information', method: 'getBookDetails')]
final readonly class GoogleBooks
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private array $options = [],
    ) {
    }

    /**
     * Search Google Books for books.
     *
     * @param string $query      Query to look up on Google Books
     * @param int    $maxResults Maximum number of results to return
     * @param string $orderBy    Sort order: relevance, newest
     * @param string $printType  Print type filter: all, books, magazines
     *
     * @return array<int, array{
     *     book_id: string,
     *     title: string,
     *     authors: array<int, string>,
     *     publisher: string,
     *     published_date: string,
     *     description: string,
     *     isbn_13: string,
     *     isbn_10: string,
     *     page_count: int,
     *     categories: array<int, string>,
     *     language: string,
     *     preview_link: string,
     *     info_link: string,
     *     thumbnail_url: string,
     *     average_rating: float,
     *     ratings_count: int,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 10,
        string $orderBy = 'relevance',
        string $printType = 'all',
    ): array {
        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/books/v1/volumes', [
                'query' => array_merge($this->options, [
                    'q' => $query,
                    'maxResults' => $maxResults,
                    'orderBy' => $orderBy,
                    'printType' => $printType,
                    'key' => $this->apiKey,
                ]),
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $results = [];
            foreach ($data['items'] as $item) {
                $volumeInfo = $item['volumeInfo'];
                $saleInfo = $item['saleInfo'] ?? [];

                $results[] = [
                    'book_id' => $item['id'],
                    'title' => $volumeInfo['title'] ?? '',
                    'authors' => $volumeInfo['authors'] ?? [],
                    'publisher' => $volumeInfo['publisher'] ?? '',
                    'published_date' => $volumeInfo['publishedDate'] ?? '',
                    'description' => $volumeInfo['description'] ?? '',
                    'isbn_13' => $this->extractIsbn($volumeInfo['industryIdentifiers'] ?? [], 'ISBN_13'),
                    'isbn_10' => $this->extractIsbn($volumeInfo['industryIdentifiers'] ?? [], 'ISBN_10'),
                    'page_count' => $volumeInfo['pageCount'] ?? 0,
                    'categories' => $volumeInfo['categories'] ?? [],
                    'language' => $volumeInfo['language'] ?? '',
                    'preview_link' => $volumeInfo['previewLink'] ?? '',
                    'info_link' => $volumeInfo['infoLink'] ?? '',
                    'thumbnail_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
                    'average_rating' => (float) ($volumeInfo['averageRating'] ?? 0),
                    'ratings_count' => (int) ($volumeInfo['ratingsCount'] ?? 0),
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'book_id' => 'error',
                    'title' => 'Search Error',
                    'authors' => [],
                    'publisher' => '',
                    'published_date' => '',
                    'description' => 'Unable to search Google Books: '.$e->getMessage(),
                    'isbn_13' => '',
                    'isbn_10' => '',
                    'page_count' => 0,
                    'categories' => [],
                    'language' => '',
                    'preview_link' => '',
                    'info_link' => '',
                    'thumbnail_url' => '',
                    'average_rating' => 0.0,
                    'ratings_count' => 0,
                ],
            ];
        }
    }

    /**
     * Get detailed information for a specific book.
     *
     * @param string $bookId The Google Books ID
     *
     * @return array{
     *     book_id: string,
     *     title: string,
     *     authors: array<int, string>,
     *     publisher: string,
     *     published_date: string,
     *     description: string,
     *     isbn_13: string,
     *     isbn_10: string,
     *     page_count: int,
     *     categories: array<int, string>,
     *     language: string,
     *     preview_link: string,
     *     info_link: string,
     *     thumbnail_url: string,
     *     average_rating: float,
     *     ratings_count: int,
     *     maturity_rating: string,
     *     subtitle: string,
     *     series_info: array<string, mixed>,
     *     dimensions: array<string, string>,
     *     price: array<string, mixed>,
     *     availability: string,
     * }|null
     */
    public function getBookDetails(string $bookId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', "https://www.googleapis.com/books/v1/volumes/{$bookId}", [
                'query' => [
                    'key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['volumeInfo'])) {
                return null;
            }

            $volumeInfo = $data['volumeInfo'];
            $saleInfo = $data['saleInfo'] ?? [];

            return [
                'book_id' => $data['id'],
                'title' => $volumeInfo['title'] ?? '',
                'authors' => $volumeInfo['authors'] ?? [],
                'publisher' => $volumeInfo['publisher'] ?? '',
                'published_date' => $volumeInfo['publishedDate'] ?? '',
                'description' => $volumeInfo['description'] ?? '',
                'isbn_13' => $this->extractIsbn($volumeInfo['industryIdentifiers'] ?? [], 'ISBN_13'),
                'isbn_10' => $this->extractIsbn($volumeInfo['industryIdentifiers'] ?? [], 'ISBN_10'),
                'page_count' => $volumeInfo['pageCount'] ?? 0,
                'categories' => $volumeInfo['categories'] ?? [],
                'language' => $volumeInfo['language'] ?? '',
                'preview_link' => $volumeInfo['previewLink'] ?? '',
                'info_link' => $volumeInfo['infoLink'] ?? '',
                'thumbnail_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
                'average_rating' => (float) ($volumeInfo['averageRating'] ?? 0),
                'ratings_count' => (int) ($volumeInfo['ratingsCount'] ?? 0),
                'maturity_rating' => $volumeInfo['maturityRating'] ?? '',
                'subtitle' => $volumeInfo['subtitle'] ?? '',
                'series_info' => $this->extractSeriesInfo($volumeInfo),
                'dimensions' => $volumeInfo['dimensions'] ?? [],
                'price' => $saleInfo['listPrice'] ?? [],
                'availability' => $saleInfo['saleability'] ?? '',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Search books by author.
     *
     * @param string $author     Author name
     * @param int    $maxResults Maximum number of results to return
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchByAuthor(string $author, int $maxResults = 10): array
    {
        return $this->__invoke("inauthor:{$author}", $maxResults);
    }

    /**
     * Search books by title.
     *
     * @param string $title      Book title
     * @param int    $maxResults Maximum number of results to return
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchByTitle(string $title, int $maxResults = 10): array
    {
        return $this->__invoke("intitle:{$title}", $maxResults);
    }

    /**
     * Search books by subject.
     *
     * @param string $subject    Subject or category
     * @param int    $maxResults Maximum number of results to return
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchBySubject(string $subject, int $maxResults = 10): array
    {
        return $this->__invoke("subject:{$subject}", $maxResults);
    }

    /**
     * Extract ISBN from industry identifiers.
     *
     * @param array<int, array<string, string>> $identifiers
     */
    private function extractIsbn(array $identifiers, string $type): string
    {
        foreach ($identifiers as $identifier) {
            if ($identifier['type'] === $type) {
                return $identifier['identifier'];
            }
        }

        return '';
    }

    /**
     * Extract series information from volume info.
     *
     * @param array<string, mixed> $volumeInfo
     *
     * @return array<string, mixed>
     */
    private function extractSeriesInfo(array $volumeInfo): array
    {
        $seriesInfo = [];

        if (isset($volumeInfo['seriesInfo'])) {
            $seriesInfo = $volumeInfo['seriesInfo'];
        }

        return $seriesInfo;
    }
}
