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
#[AsTool('yahoo_finance_news', 'Tool that searches financial news on Yahoo Finance')]
#[AsTool('yahoo_finance_quote', 'Tool that gets stock quote information from Yahoo Finance', method: 'getQuote')]
final readonly class YahooFinance
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
     * @param string $query company ticker query to look up
     * @param int    $topK  The number of results to return
     *
     * @return array<int, array{
     *     title: string,
     *     summary: string,
     *     link: string,
     *     published: string,
     *     source: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $topK = 10,
    ): array {
        try {
            $ticker = strtoupper(trim($query));

            // Get company information first
            $companyInfo = $this->getCompanyInfo($ticker);
            if (empty($companyInfo)) {
                return [
                    [
                        'title' => 'Company Not Found',
                        'summary' => "Company ticker {$ticker} not found.",
                        'link' => '',
                        'published' => '',
                        'source' => 'Error',
                    ],
                ];
            }

            // Get news for the ticker
            $newsData = $this->fetchYahooFinanceNews($ticker, $topK);

            if (empty($newsData)) {
                return [
                    [
                        'title' => 'No News Found',
                        'summary' => "No news found for company ticker {$ticker}.",
                        'link' => '',
                        'published' => '',
                        'source' => 'Yahoo Finance',
                    ],
                ];
            }

            return $newsData;
        } catch (\Exception $e) {
            return [
                [
                    'title' => 'Error',
                    'summary' => 'Unable to fetch financial news: '.$e->getMessage(),
                    'link' => '',
                    'published' => '',
                    'source' => 'Error',
                ],
            ];
        }
    }

    /**
     * Get stock quote information.
     *
     * @param string $ticker The stock ticker symbol
     *
     * @return array{
     *     symbol: string,
     *     name: string,
     *     price: float,
     *     change: float,
     *     changePercent: float,
     *     volume: int,
     *     marketCap: string,
     *     currency: string,
     * }|null
     */
    public function getQuote(string $ticker): ?array
    {
        try {
            $ticker = strtoupper(trim($ticker));

            // Use Yahoo Finance API (unofficial)
            $response = $this->httpClient->request('GET', "https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}", [
                'query' => [
                    'interval' => '1d',
                    'range' => '1d',
                ],
            ]);

            $data = $response->toArray();

            if (empty($data['chart']['result'])) {
                return null;
            }

            $result = $data['chart']['result'][0];
            $meta = $result['meta'];
            $quote = $result['indicators']['quote'][0];

            return [
                'symbol' => $meta['symbol'],
                'name' => $meta['longName'] ?? $meta['shortName'] ?? $ticker,
                'price' => $meta['regularMarketPrice'] ?? 0.0,
                'change' => $meta['regularMarketPrice'] - ($meta['previousClose'] ?? 0),
                'changePercent' => (($meta['regularMarketPrice'] ?? 0) - ($meta['previousClose'] ?? 0)) / ($meta['previousClose'] ?? 1) * 100,
                'volume' => $meta['regularMarketVolume'] ?? 0,
                'marketCap' => $this->formatNumber($meta['marketCap'] ?? 0),
                'currency' => $meta['currency'] ?? 'USD',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get company information.
     *
     * @return array<string, mixed>|null
     */
    private function getCompanyInfo(string $ticker): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://query2.finance.yahoo.com/v1/finance/search', [
                'query' => [
                    'q' => $ticker,
                    'quotesCount' => 1,
                    'newsCount' => 0,
                ],
            ]);

            $data = $response->toArray();

            if (empty($data['quotes'])) {
                return null;
            }

            return $data['quotes'][0];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch Yahoo Finance news.
     *
     * @return array<int, array{
     *     title: string,
     *     summary: string,
     *     link: string,
     *     published: string,
     *     source: string,
     * }>
     */
    private function fetchYahooFinanceNews(string $ticker, int $topK): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://query2.finance.yahoo.com/v1/finance/search', [
                'query' => [
                    'q' => $ticker,
                    'quotesCount' => 0,
                    'newsCount' => $topK,
                ],
            ]);

            $data = $response->toArray();
            $news = $data['news'] ?? [];
            $results = [];

            foreach ($news as $article) {
                $results[] = [
                    'title' => $article['title'] ?? '',
                    'summary' => $article['summary'] ?? '',
                    'link' => $article['link'] ?? '',
                    'published' => $article['providerPublishTime'] ? date('Y-m-d H:i:s', $article['providerPublishTime']) : '',
                    'source' => $article['publisher'] ?? 'Yahoo Finance',
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Format large numbers with appropriate suffixes.
     */
    private function formatNumber(int $number): string
    {
        if ($number >= 1000000000000) {
            return round($number / 1000000000000, 2).'T';
        }

        if ($number >= 1000000000) {
            return round($number / 1000000000, 2).'B';
        }

        if ($number >= 1000000) {
            return round($number / 1000000, 2).'M';
        }

        if ($number >= 1000) {
            return round($number / 1000, 2).'K';
        }

        return (string) $number;
    }
}
