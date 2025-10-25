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
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('polygon_get_ticker_details', 'Tool that gets Polygon ticker details')]
#[AsTool('polygon_get_ticker_news', 'Tool that gets Polygon ticker news', method: 'getTickerNews')]
#[AsTool('polygon_get_ticker_trades', 'Tool that gets Polygon ticker trades', method: 'getTickerTrades')]
#[AsTool('polygon_get_ticker_quotes', 'Tool that gets Polygon ticker quotes', method: 'getTickerQuotes')]
#[AsTool('polygon_get_aggregates', 'Tool that gets Polygon aggregates', method: 'getAggregates')]
#[AsTool('polygon_get_grouped_daily', 'Tool that gets Polygon grouped daily data', method: 'getGroupedDaily')]
#[AsTool('polygon_get_market_status', 'Tool that gets Polygon market status', method: 'getMarketStatus')]
#[AsTool('polygon_get_exchanges', 'Tool that gets Polygon exchanges', method: 'getExchanges')]
final readonly class Polygon
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.polygon.io',
        private array $options = [],
    ) {
    }

    /**
     * Get Polygon ticker details.
     *
     * @param string $ticker Stock ticker symbol
     * @param string $date   Date (YYYY-MM-DD)
     *
     * @return array{
     *     success: bool,
     *     ticker: array{
     *         ticker: string,
     *         name: string,
     *         market: string,
     *         locale: string,
     *         primaryExchange: string,
     *         type: string,
     *         active: bool,
     *         currencyName: string,
     *         currencySymbol: string,
     *         baseCurrencySymbol: string,
     *         baseCurrencyName: string,
     *         cik: string,
     *         compositeFigi: string,
     *         shareClassFigi: string,
     *         marketCap: float,
     *         description: string,
     *         homepageUrl: string,
     *         totalEmployees: int,
     *         listDate: string,
     *         branding: array{
     *             logoUrl: string,
     *             iconUrl: string,
     *         },
     *         shareClassSharesOutstanding: int,
     *         weightedSharesOutstanding: int,
     *         roundLot: int,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(
        string $ticker,
        string $date = '',
    ): array {
        try {
            $params = [
                'apikey' => $this->apiKey,
            ];

            if ($date) {
                $params['date'] = $date;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v3/reference/tickers/{$ticker}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();
            $result = $data['results'] ?? [];

            return [
                'success' => true,
                'ticker' => [
                    'ticker' => $result['ticker'] ?? $ticker,
                    'name' => $result['name'] ?? '',
                    'market' => $result['market'] ?? '',
                    'locale' => $result['locale'] ?? '',
                    'primaryExchange' => $result['primary_exchange'] ?? '',
                    'type' => $result['type'] ?? '',
                    'active' => $result['active'] ?? false,
                    'currencyName' => $result['currency_name'] ?? '',
                    'currencySymbol' => $result['currency_symbol'] ?? '',
                    'baseCurrencySymbol' => $result['base_currency_symbol'] ?? '',
                    'baseCurrencyName' => $result['base_currency_name'] ?? '',
                    'cik' => $result['cik'] ?? '',
                    'compositeFigi' => $result['composite_figi'] ?? '',
                    'shareClassFigi' => $result['share_class_figi'] ?? '',
                    'marketCap' => $result['market_cap'] ?? 0.0,
                    'description' => $result['description'] ?? '',
                    'homepageUrl' => $result['homepage_url'] ?? '',
                    'totalEmployees' => $result['total_employees'] ?? 0,
                    'listDate' => $result['list_date'] ?? '',
                    'branding' => [
                        'logoUrl' => $result['branding']['logo_url'] ?? '',
                        'iconUrl' => $result['branding']['icon_url'] ?? '',
                    ],
                    'shareClassSharesOutstanding' => $result['share_class_shares_outstanding'] ?? 0,
                    'weightedSharesOutstanding' => $result['weighted_shares_outstanding'] ?? 0,
                    'roundLot' => $result['round_lot'] ?? 0,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'ticker' => [
                    'ticker' => $ticker,
                    'name' => '',
                    'market' => '',
                    'locale' => '',
                    'primaryExchange' => '',
                    'type' => '',
                    'active' => false,
                    'currencyName' => '',
                    'currencySymbol' => '',
                    'baseCurrencySymbol' => '',
                    'baseCurrencyName' => '',
                    'cik' => '',
                    'compositeFigi' => '',
                    'shareClassFigi' => '',
                    'marketCap' => 0.0,
                    'description' => '',
                    'homepageUrl' => '',
                    'totalEmployees' => 0,
                    'listDate' => '',
                    'branding' => ['logoUrl' => '', 'iconUrl' => ''],
                    'shareClassSharesOutstanding' => 0,
                    'weightedSharesOutstanding' => 0,
                    'roundLot' => 0,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Polygon ticker news.
     *
     * @param string $ticker         Stock ticker symbol
     * @param string $publishedUtc   Published date (YYYY-MM-DD)
     * @param string $publishedUtcLt Published date less than (YYYY-MM-DD)
     * @param string $publishedUtcGt Published date greater than (YYYY-MM-DD)
     * @param int    $limit          Number of results
     * @param string $sort           Sort order (published_utc, -published_utc)
     * @param string $order          Order (asc, desc)
     *
     * @return array{
     *     success: bool,
     *     news: array<int, array{
     *         id: string,
     *         publisher: array{
     *             name: string,
     *             homepageUrl: string,
     *             logoUrl: string,
     *             faviconUrl: string,
     *         },
     *         title: string,
     *         author: string,
     *         publishedUtc: string,
     *         articleUrl: string,
     *         tickers: array<int, string>,
     *         ampUrl: string,
     *         imageUrl: string,
     *         description: string,
     *         keywords: array<int, string>,
     *     }>,
     *     count: int,
     *     error: string,
     * }
     */
    public function getTickerNews(
        string $ticker,
        string $publishedUtc = '',
        string $publishedUtcLt = '',
        string $publishedUtcGt = '',
        int $limit = 10,
        string $sort = 'published_utc',
        string $order = 'desc',
    ): array {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'ticker' => $ticker,
                'limit' => max(1, min($limit, 1000)),
                'sort' => $sort,
                'order' => $order,
            ];

            if ($publishedUtc) {
                $params['published_utc'] = $publishedUtc;
            }

            if ($publishedUtcLt) {
                $params['published_utc.lt'] = $publishedUtcLt;
            }

            if ($publishedUtcGt) {
                $params['published_utc.gt'] = $publishedUtcGt;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v2/reference/news", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'news' => array_map(fn ($article) => [
                    'id' => $article['id'] ?? '',
                    'publisher' => [
                        'name' => $article['publisher']['name'] ?? '',
                        'homepageUrl' => $article['publisher']['homepage_url'] ?? '',
                        'logoUrl' => $article['publisher']['logo_url'] ?? '',
                        'faviconUrl' => $article['publisher']['favicon_url'] ?? '',
                    ],
                    'title' => $article['title'] ?? '',
                    'author' => $article['author'] ?? '',
                    'publishedUtc' => $article['published_utc'] ?? '',
                    'articleUrl' => $article['article_url'] ?? '',
                    'tickers' => $article['tickers'] ?? [],
                    'ampUrl' => $article['amp_url'] ?? '',
                    'imageUrl' => $article['image_url'] ?? '',
                    'description' => $article['description'] ?? '',
                    'keywords' => $article['keywords'] ?? [],
                ], $data['results'] ?? []),
                'count' => $data['count'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'news' => [],
                'count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Polygon ticker trades.
     *
     * @param string $ticker      Stock ticker symbol
     * @param string $timestamp   Timestamp (YYYY-MM-DD or Unix timestamp)
     * @param string $timestampLt Timestamp less than
     * @param string $timestampGt Timestamp greater than
     * @param int    $limit       Number of results
     * @param string $order       Order (asc, desc)
     * @param string $sort        Sort field
     *
     * @return array{
     *     success: bool,
     *     trades: array<int, array{
     *         conditions: array<int, int>,
     *         exchange: string,
     *         price: float,
     *         sipTimestamp: int,
     *         size: int,
     *         timeframe: string,
     *         participantTimestamp: int,
     *         sequenceNumber: int,
     *         trfId: int,
     *         trfTimestamp: int,
     *         yahoo: bool,
     *     }>,
     *     count: int,
     *     error: string,
     * }
     */
    public function getTickerTrades(
        string $ticker,
        string $timestamp = '',
        string $timestampLt = '',
        string $timestampGt = '',
        int $limit = 10,
        string $order = 'desc',
        string $sort = 'timestamp',
    ): array {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'limit' => max(1, min($limit, 50000)),
                'order' => $order,
                'sort' => $sort,
            ];

            if ($timestamp) {
                $params['timestamp'] = $timestamp;
            }

            if ($timestampLt) {
                $params['timestamp.lt'] = $timestampLt;
            }

            if ($timestampGt) {
                $params['timestamp.gt'] = $timestampGt;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v3/trades/{$ticker}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'trades' => array_map(fn ($trade) => [
                    'conditions' => $trade['conditions'] ?? [],
                    'exchange' => $trade['exchange'] ?? '',
                    'price' => $trade['price'] ?? 0.0,
                    'sipTimestamp' => $trade['sip_timestamp'] ?? 0,
                    'size' => $trade['size'] ?? 0,
                    'timeframe' => $trade['timeframe'] ?? '',
                    'participantTimestamp' => $trade['participant_timestamp'] ?? 0,
                    'sequenceNumber' => $trade['sequence_number'] ?? 0,
                    'trfId' => $trade['trf_id'] ?? 0,
                    'trfTimestamp' => $trade['trf_timestamp'] ?? 0,
                    'yahoo' => $trade['yahoo'] ?? false,
                ], $data['results'] ?? []),
                'count' => $data['count'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'trades' => [],
                'count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Polygon ticker quotes.
     *
     * @param string $ticker      Stock ticker symbol
     * @param string $timestamp   Timestamp (YYYY-MM-DD or Unix timestamp)
     * @param string $timestampLt Timestamp less than
     * @param string $timestampGt Timestamp greater than
     * @param int    $limit       Number of results
     * @param string $order       Order (asc, desc)
     * @param string $sort        Sort field
     *
     * @return array{
     *     success: bool,
     *     quotes: array<int, array{
     *         conditions: array<int, int>,
     *         exchange: string,
     *         ask: float,
     *         askSize: int,
     *         bid: float,
     *         bidSize: int,
     *         participantTimestamp: int,
     *         sequenceNumber: int,
     *         sipTimestamp: int,
     *         timeframe: string,
     *         trfId: int,
     *         trfTimestamp: int,
     *         yahoo: bool,
     *     }>,
     *     count: int,
     *     error: string,
     * }
     */
    public function getTickerQuotes(
        string $ticker,
        string $timestamp = '',
        string $timestampLt = '',
        string $timestampGt = '',
        int $limit = 10,
        string $order = 'desc',
        string $sort = 'timestamp',
    ): array {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'limit' => max(1, min($limit, 50000)),
                'order' => $order,
                'sort' => $sort,
            ];

            if ($timestamp) {
                $params['timestamp'] = $timestamp;
            }

            if ($timestampLt) {
                $params['timestamp.lt'] = $timestampLt;
            }

            if ($timestampGt) {
                $params['timestamp.gt'] = $timestampGt;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v3/quotes/{$ticker}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'quotes' => array_map(fn ($quote) => [
                    'conditions' => $quote['conditions'] ?? [],
                    'exchange' => $quote['exchange'] ?? '',
                    'ask' => $quote['ask'] ?? 0.0,
                    'askSize' => $quote['ask_size'] ?? 0,
                    'bid' => $quote['bid'] ?? 0.0,
                    'bidSize' => $quote['bid_size'] ?? 0,
                    'participantTimestamp' => $quote['participant_timestamp'] ?? 0,
                    'sequenceNumber' => $quote['sequence_number'] ?? 0,
                    'sipTimestamp' => $quote['sip_timestamp'] ?? 0,
                    'timeframe' => $quote['timeframe'] ?? '',
                    'trfId' => $quote['trf_id'] ?? 0,
                    'trfTimestamp' => $quote['trf_timestamp'] ?? 0,
                    'yahoo' => $quote['yahoo'] ?? false,
                ], $data['results'] ?? []),
                'count' => $data['count'] ?? 0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'quotes' => [],
                'count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Polygon aggregates.
     *
     * @param string $ticker     Stock ticker symbol
     * @param int    $multiplier Size of the timespan multiplier
     * @param string $timespan   Size of the time window (minute, hour, day, week, month, quarter, year)
     * @param string $from       Start of the aggregate time window
     * @param string $to         End of the aggregate time window
     * @param bool   $adjusted   Whether or not the results are adjusted for splits
     * @param string $sort       Sort the results by timestamp (asc, desc)
     * @param int    $limit      Limits the number of base aggregates queried to create the aggregate results
     *
     * @return array{
     *     success: bool,
     *     aggregates: array<int, array{
     *         ticker: string,
     *         queryCount: int,
     *         resultsCount: int,
     *         adjusted: bool,
     *         results: array<int, array{
     *             v: float,
     *             vw: float,
     *             o: float,
     *             c: float,
     *             h: float,
     *             l: float,
     *             t: int,
     *             n: int,
     *         }>,
     *         status: string,
     *         requestId: string,
     *         count: int,
     *     }>,
     *     error: string,
     * }
     */
    public function getAggregates(
        string $ticker,
        int $multiplier,
        string $timespan,
        string $from,
        string $to,
        bool $adjusted = true,
        string $sort = 'asc',
        int $limit = 50000,
    ): array {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'ticker' => $ticker,
                'multiplier' => $multiplier,
                'timespan' => $timespan,
                'from' => $from,
                'to' => $to,
                'adjusted' => $adjusted ? 'true' : 'false',
                'sort' => $sort,
                'limit' => max(1, min($limit, 50000)),
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v2/aggs/ticker/{$ticker}/range/{$multiplier}/{$timespan}/{$from}/{$to}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'aggregates' => [
                    [
                        'ticker' => $data['ticker'] ?? $ticker,
                        'queryCount' => $data['queryCount'] ?? 0,
                        'resultsCount' => $data['resultsCount'] ?? 0,
                        'adjusted' => $data['adjusted'] ?? $adjusted,
                        'results' => array_map(fn ($result) => [
                            'v' => $result['v'] ?? 0.0,
                            'vw' => $result['vw'] ?? 0.0,
                            'o' => $result['o'] ?? 0.0,
                            'c' => $result['c'] ?? 0.0,
                            'h' => $result['h'] ?? 0.0,
                            'l' => $result['l'] ?? 0.0,
                            't' => $result['t'] ?? 0,
                            'n' => $result['n'] ?? 0,
                        ], $data['results'] ?? []),
                        'status' => $data['status'] ?? '',
                        'requestId' => $data['request_id'] ?? '',
                        'count' => \count($data['results'] ?? []),
                    ],
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'aggregates' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Polygon grouped daily data.
     *
     * @param string $date     Date (YYYY-MM-DD)
     * @param bool   $adjusted Whether or not the results are adjusted for splits
     *
     * @return array{
     *     success: bool,
     *     groupedDaily: array{
     *         queryCount: int,
     *         resultsCount: int,
     *         adjusted: bool,
     *         results: array<int, array{
     *             T: string,
     *             v: float,
     *             vw: float,
     *             o: float,
     *             c: float,
     *             h: float,
     *             l: float,
     *             t: int,
     *             n: int,
     *         }>,
     *         status: string,
     *         requestId: string,
     *         count: int,
     *     },
     *     error: string,
     * }
     */
    public function getGroupedDaily(
        string $date,
        bool $adjusted = true,
    ): array {
        try {
            $params = [
                'apikey' => $this->apiKey,
                'date' => $date,
                'adjusted' => $adjusted ? 'true' : 'false',
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v2/aggs/grouped/locale/us/market/stocks/{$date}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'groupedDaily' => [
                    'queryCount' => $data['queryCount'] ?? 0,
                    'resultsCount' => $data['resultsCount'] ?? 0,
                    'adjusted' => $data['adjusted'] ?? $adjusted,
                    'results' => array_map(fn ($result) => [
                        'T' => $result['T'] ?? '',
                        'v' => $result['v'] ?? 0.0,
                        'vw' => $result['vw'] ?? 0.0,
                        'o' => $result['o'] ?? 0.0,
                        'c' => $result['c'] ?? 0.0,
                        'h' => $result['h'] ?? 0.0,
                        'l' => $result['l'] ?? 0.0,
                        't' => $result['t'] ?? 0,
                        'n' => $result['n'] ?? 0,
                    ], $data['results'] ?? []),
                    'status' => $data['status'] ?? '',
                    'requestId' => $data['request_id'] ?? '',
                    'count' => \count($data['results'] ?? []),
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'groupedDaily' => [
                    'queryCount' => 0,
                    'resultsCount' => 0,
                    'adjusted' => $adjusted,
                    'results' => [],
                    'status' => '',
                    'requestId' => '',
                    'count' => 0,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Polygon market status.
     *
     * @return array{
     *     success: bool,
     *     market: string,
     *     serverTime: string,
     *         exchanges: array<int, string>,
     *     currencies: array<int, string>,
     *     error: string,
     * }
     */
    public function getMarketStatus(): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/marketstatus/now", [
                'query' => array_merge($this->options, ['apikey' => $this->apiKey]),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'market' => $data['market'] ?? '',
                'serverTime' => $data['serverTime'] ?? '',
                'exchanges' => $data['exchanges'] ?? [],
                'currencies' => $data['currencies'] ?? [],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'market' => '',
                'serverTime' => '',
                'exchanges' => [],
                'currencies' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Polygon exchanges.
     *
     * @return array{
     *     success: bool,
     *     exchanges: array<int, array{
     *         id: string,
     *         type: string,
     *         market: string,
     *         mic: string,
     *         name: string,
     *         tape: string,
     *     }>,
     *     error: string,
     * }
     */
    public function getExchanges(): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v3/reference/exchanges", [
                'query' => array_merge($this->options, ['apikey' => $this->apiKey]),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'exchanges' => array_map(fn ($exchange) => [
                    'id' => $exchange['id'] ?? '',
                    'type' => $exchange['type'] ?? '',
                    'market' => $exchange['market'] ?? '',
                    'mic' => $exchange['mic'] ?? '',
                    'name' => $exchange['name'] ?? '',
                    'tape' => $exchange['tape'] ?? '',
                ], $data['results'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'exchanges' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}
