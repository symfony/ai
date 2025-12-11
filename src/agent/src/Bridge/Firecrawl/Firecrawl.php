<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Firecrawl;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @see https://www.firecrawl.dev/
 */
#[AsTool('firecrawl_scrape', description: 'Allow to scrape website using url', method: 'scrape')]
#[AsTool('firecrawl_crawl', description: 'Allow to crawl website using url', method: 'crawl')]
#[AsTool('firecrawl_map', description: 'Allow to retrieve all urls from a website using url', method: 'map')]
final class Firecrawl
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $endpoint,
    ) {
    }

    /**
     * @return array{
     *     url: string,
     *     markdown: string,
     *     html: string,
     * }
     */
    public function scrape(string $url): array
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/v1/scrape', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'url' => $url,
                'formats' => ['markdown', 'html'],
            ],
        ]);

        $scrapingPayload = $response->toArray();

        return [
            'url' => $url,
            'markdown' => $scrapingPayload['data']['markdown'],
            'html' => $scrapingPayload['data']['html'],
        ];
    }

    /**
     * @return array<int, array{
     *     url: string,
     *     markdown: string,
     *     html: string,
     * }>|array{}
     */
    public function crawl(string $url): array
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/v1/crawl', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'url' => $url,
                'scrapeOptions' => [
                    'formats' => ['markdown', 'html'],
                ],
            ],
        ]);

        $crawlingPayload = $response->toArray();

        $scrapingStatusRequest = fn (array $crawlingPayload): ResponseInterface => $this->httpClient->request('GET', \sprintf('%s/v1/crawl/%s', $this->endpoint, $crawlingPayload['id']), [
            'auth_bearer' => $this->apiKey,
        ]);

        while ('scraping' === $scrapingStatusRequest($crawlingPayload)->toArray()['status']) {
            usleep(500);
        }

        $scrapingPayload = $this->httpClient->request('GET', \sprintf('%s/v1/crawl/%s', $this->endpoint, $crawlingPayload['id']), [
            'auth_bearer' => $this->apiKey,
        ]);

        $finalPayload = $scrapingPayload->toArray();

        return array_map(static fn (array $scrapedItem) => [
            'url' => $scrapedItem['metadata']['og:url'],
            'markdown' => $scrapedItem['markdown'],
            'html' => $scrapedItem['html'],
        ], $finalPayload['data']);
    }

    /**
     * @return array{
     *     url: string,
     *     links: array<string>,
     * }
     */
    public function map(string $url): array
    {
        $response = $this->httpClient->request('POST', \sprintf('%s/v1/map', $this->endpoint), [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'url' => $url,
            ],
        ]);

        $mappingPayload = $response->toArray();

        return [
            'url' => $url,
            'links' => $mappingPayload['links'],
        ];
    }
}
