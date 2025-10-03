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
#[AsTool('pub_med', 'Tool that searches the PubMed API for medical literature')]
#[AsTool('pub_med_abstract', 'Tool that gets detailed abstracts from PubMed', method: 'getAbstract')]
final readonly class PubMed
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private array $options = [],
    ) {
    }

    /**
     * Search PubMed for medical literature.
     *
     * @param string $query      search query for medical literature
     * @param int    $maxResults The maximum number of results to return
     * @param string $sort       Sort order (relevance, pub_date, first_author, journal, title)
     *
     * @return array<int, array{
     *     pmid: string,
     *     title: string,
     *     authors: string,
     *     journal: string,
     *     publication_date: string,
     *     abstract: string,
     *     doi: string,
     *     mesh_terms: array<int, string>,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 10,
        string $sort = 'relevance',
    ): array {
        try {
            // Search PubMed
            $searchResponse = $this->httpClient->request('GET', 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi', [
                'query' => array_merge($this->options, [
                    'db' => 'pubmed',
                    'term' => $query,
                    'retmax' => $maxResults,
                    'retmode' => 'json',
                    'sort' => $sort,
                ]),
            ]);

            $searchData = $searchResponse->toArray();
            $pmids = $searchData['esearchresult']['idlist'] ?? [];

            if (empty($pmids)) {
                return [];
            }

            // Get detailed information for each PMID
            $detailsResponse = $this->httpClient->request('GET', 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi', [
                'query' => [
                    'db' => 'pubmed',
                    'id' => implode(',', $pmids),
                    'retmode' => 'xml',
                    'rettype' => 'abstract',
                ],
            ]);

            $xml = $detailsResponse->getContent();

            return $this->parsePubMedXml($xml);
        } catch (\Exception $e) {
            return [
                [
                    'pmid' => 'error',
                    'title' => 'Search Error',
                    'authors' => '',
                    'journal' => '',
                    'publication_date' => '',
                    'abstract' => 'Unable to search PubMed: '.$e->getMessage(),
                    'doi' => '',
                    'mesh_terms' => [],
                ],
            ];
        }
    }

    /**
     * Get detailed abstract for a specific PMID.
     *
     * @param string $pmid PubMed ID
     *
     * @return array{
     *     pmid: string,
     *     title: string,
     *     authors: string,
     *     journal: string,
     *     publication_date: string,
     *     abstract: string,
     *     doi: string,
     *     mesh_terms: array<int, string>,
     *     keywords: array<int, string>,
     *     references: int,
     * }|null
     */
    public function getAbstract(string $pmid): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi', [
                'query' => [
                    'db' => 'pubmed',
                    'id' => $pmid,
                    'retmode' => 'xml',
                    'rettype' => 'abstract',
                ],
            ]);

            $xml = $response->getContent();
            $results = $this->parsePubMedXml($xml);

            if (empty($results)) {
                return null;
            }

            $result = $results[0];

            // Add additional details
            $result['keywords'] = $this->extractKeywords($xml);
            $result['references'] = $this->countReferences($xml);

            return $result;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse PubMed XML response.
     *
     * @return array<int, array{
     *     pmid: string,
     *     title: string,
     *     authors: string,
     *     journal: string,
     *     publication_date: string,
     *     abstract: string,
     *     doi: string,
     *     mesh_terms: array<int, string>,
     * }>
     */
    private function parsePubMedXml(string $xml): array
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);

            $articles = $dom->getElementsByTagName('PubmedArticle');
            $results = [];

            foreach ($articles as $article) {
                $pmid = $this->getElementText($article, 'PMID');
                $title = $this->getElementText($article, 'ArticleTitle');
                $journal = $this->getElementText($article, 'Title'); // Journal title
                $abstract = $this->getElementText($article, 'AbstractText');
                $doi = $this->extractDoi($article);
                $authors = $this->extractAuthors($article);
                $publicationDate = $this->extractPublicationDate($article);
                $meshTerms = $this->extractMeshTerms($article);

                $results[] = [
                    'pmid' => $pmid,
                    'title' => $title,
                    'authors' => $authors,
                    'journal' => $journal,
                    'publication_date' => $publicationDate,
                    'abstract' => $abstract,
                    'doi' => $doi,
                    'mesh_terms' => $meshTerms,
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'pmid' => 'parse_error',
                    'title' => 'Parse Error',
                    'authors' => '',
                    'journal' => '',
                    'publication_date' => '',
                    'abstract' => 'Unable to parse PubMed response: '.$e->getMessage(),
                    'doi' => '',
                    'mesh_terms' => [],
                ],
            ];
        }
    }

    /**
     * Get text content of an element.
     */
    private function getElementText(\DOMElement $parent, string $tagName): string
    {
        $elements = $parent->getElementsByTagName($tagName);
        if ($elements->length > 0) {
            return $elements->item(0)->textContent ?? '';
        }

        return '';
    }

    /**
     * Extract DOI from article.
     */
    private function extractDoi(\DOMElement $article): string
    {
        $elinkElements = $article->getElementsByTagName('ELocationID');
        foreach ($elinkElements as $element) {
            $type = $element->getAttribute('EIdType');
            if ('doi' === $type) {
                return $element->textContent ?? '';
            }
        }

        return '';
    }

    /**
     * Extract authors from article.
     */
    private function extractAuthors(\DOMElement $article): string
    {
        $authorList = $article->getElementsByTagName('AuthorList');
        if (0 === $authorList->length) {
            return '';
        }

        $authors = [];
        $authorElements = $authorList->item(0)->getElementsByTagName('Author');

        foreach ($authorElements as $author) {
            $lastName = $this->getElementText($author, 'LastName');
            $firstName = $this->getElementText($author, 'ForeName');

            if ($lastName) {
                $authorName = $lastName;
                if ($firstName) {
                    $authorName .= ', '.$firstName;
                }
                $authors[] = $authorName;
            }
        }

        return implode('; ', $authors);
    }

    /**
     * Extract publication date.
     */
    private function extractPublicationDate(\DOMElement $article): string
    {
        $pubDate = $article->getElementsByTagName('PubDate');
        if (0 === $pubDate->length) {
            return '';
        }

        $year = $this->getElementText($pubDate->item(0), 'Year');
        $month = $this->getElementText($pubDate->item(0), 'Month');
        $day = $this->getElementText($pubDate->item(0), 'Day');

        $date = $year;
        if ($month) {
            $date .= '-'.$month;
        }
        if ($day) {
            $date .= '-'.$day;
        }

        return $date;
    }

    /**
     * Extract MeSH terms.
     *
     * @return array<int, string>
     */
    private function extractMeshTerms(\DOMElement $article): array
    {
        $meshHeadings = $article->getElementsByTagName('MeshHeading');
        $terms = [];

        foreach ($meshHeadings as $heading) {
            $descriptor = $this->getElementText($heading, 'DescriptorName');
            if ($descriptor) {
                $terms[] = $descriptor;
            }
        }

        return $terms;
    }

    /**
     * Extract keywords from article.
     *
     * @return array<int, string>
     */
    private function extractKeywords(string $xml): array
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);

            $keywords = [];
            $keywordElements = $dom->getElementsByTagName('Keyword');

            foreach ($keywordElements as $keyword) {
                $text = $keyword->textContent ?? '';
                if ($text) {
                    $keywords[] = $text;
                }
            }

            return $keywords;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Count references in article.
     */
    private function countReferences(string $xml): int
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);

            $refElements = $dom->getElementsByTagName('Reference');

            return $refElements->length;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
