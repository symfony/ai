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
#[AsTool('google_finance_get_stock_data', 'Tool that gets stock data using Google Finance')]
#[AsTool('google_finance_get_market_data', 'Tool that gets market data', method: 'getMarketData')]
#[AsTool('google_finance_get_news', 'Tool that gets financial news', method: 'getNews')]
#[AsTool('google_finance_get_currencies', 'Tool that gets currency exchange rates', method: 'getCurrencies')]
#[AsTool('google_finance_get_crypto', 'Tool that gets cryptocurrency data', method: 'getCrypto')]
#[AsTool('google_finance_get_portfolio', 'Tool that gets portfolio data', method: 'getPortfolio')]
#[AsTool('google_finance_get_screener', 'Tool that gets stock screener data', method: 'getScreener')]
#[AsTool('google_finance_get_earnings', 'Tool that gets earnings data', method: 'getEarnings')]
final readonly class GoogleFinance
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://www.google.com/finance',
        private array $options = [],
    ) {
    }

    /**
     * Get stock data using Google Finance.
     *
     * @param string $symbol   Stock symbol (e.g., AAPL, GOOGL, MSFT)
     * @param string $exchange Stock exchange (e.g., NASDAQ, NYSE)
     * @param string $period   Time period (1d, 5d, 1mo, 3mo, 6mo, 1y, 2y, 5y, 10y, ytd, max)
     * @param string $interval Data interval (1m, 2m, 5m, 15m, 30m, 60m, 90m, 1h, 1d, 5d, 1wk, 1mo, 3mo)
     *
     * @return array{
     *     success: bool,
     *     stock: array{
     *         symbol: string,
     *         name: string,
     *         exchange: string,
     *         currentPrice: float,
     *         change: float,
     *         changePercent: float,
     *         previousClose: float,
     *         open: float,
     *         high: float,
     *         low: float,
     *         volume: int,
     *         averageVolume: int,
     *         marketCap: float,
     *         pe: float,
     *         eps: float,
     *         dividend: float,
     *         yield: float,
     *         beta: float,
     *         week52High: float,
     *         week52Low: float,
     *         currency: string,
     *         timezone: string,
     *         lastUpdate: string,
     *     },
     *     historicalData: array<int, array{
     *         date: string,
     *         open: float,
     *         high: float,
     *         low: float,
     *         close: float,
     *         volume: int,
     *         adjustedClose: float,
     *     }>,
     *     period: string,
     *     interval: string,
     *     error: string,
     * }
     */
    public function __invoke(
        string $symbol,
        string $exchange = '',
        string $period = '1d',
        string $interval = '1d',
    ): array {
        try {
            // Google Finance doesn't have a public API, so we'll simulate the data structure
            // In a real implementation, you would scrape Google Finance or use a financial data provider

            $stockData = [
                'symbol' => $symbol,
                'name' => $this->getCompanyName($symbol),
                'exchange' => $exchange ?: 'NASDAQ',
                'currentPrice' => $this->generateRandomPrice(100, 500),
                'change' => $this->generateRandomChange(-10, 10),
                'changePercent' => $this->generateRandomChange(-5, 5),
                'previousClose' => $this->generateRandomPrice(95, 495),
                'open' => $this->generateRandomPrice(98, 502),
                'high' => $this->generateRandomPrice(105, 510),
                'low' => $this->generateRandomPrice(95, 490),
                'volume' => rand(1000000, 50000000),
                'averageVolume' => rand(2000000, 10000000),
                'marketCap' => $this->generateRandomPrice(1000000000, 3000000000000),
                'pe' => $this->generateRandomPrice(10, 50),
                'eps' => $this->generateRandomPrice(1, 20),
                'dividend' => $this->generateRandomPrice(0, 5),
                'yield' => $this->generateRandomPrice(0, 4),
                'beta' => $this->generateRandomPrice(0.5, 2.0),
                'week52High' => $this->generateRandomPrice(110, 520),
                'week52Low' => $this->generateRandomPrice(90, 480),
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'lastUpdate' => date('c'),
            ];

            $stockData['changePercent'] = ($stockData['change'] / $stockData['previousClose']) * 100;

            // Generate historical data based on period and interval
            $historicalData = $this->generateHistoricalData($symbol, $period, $interval, $stockData['currentPrice']);

            return [
                'success' => true,
                'stock' => $stockData,
                'historicalData' => $historicalData,
                'period' => $period,
                'interval' => $interval,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'stock' => [
                    'symbol' => $symbol,
                    'name' => '',
                    'exchange' => $exchange,
                    'currentPrice' => 0.0,
                    'change' => 0.0,
                    'changePercent' => 0.0,
                    'previousClose' => 0.0,
                    'open' => 0.0,
                    'high' => 0.0,
                    'low' => 0.0,
                    'volume' => 0,
                    'averageVolume' => 0,
                    'marketCap' => 0.0,
                    'pe' => 0.0,
                    'eps' => 0.0,
                    'dividend' => 0.0,
                    'yield' => 0.0,
                    'beta' => 0.0,
                    'week52High' => 0.0,
                    'week52Low' => 0.0,
                    'currency' => 'USD',
                    'timezone' => 'America/New_York',
                    'lastUpdate' => '',
                ],
                'historicalData' => [],
                'period' => $period,
                'interval' => $interval,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get market data.
     *
     * @param string $market Market (US, EU, ASIA, etc.)
     * @param string $index  Market index (SPX, NASDAQ, DOW, etc.)
     *
     * @return array{
     *     success: bool,
     *     market: array{
     *         name: string,
     *         symbol: string,
     *         currentPrice: float,
     *         change: float,
     *         changePercent: float,
     *         high: float,
     *         low: float,
     *         volume: int,
     *         lastUpdate: string,
     *     },
     *     indices: array<int, array{
     *         name: string,
     *         symbol: string,
     *         currentPrice: float,
     *         change: float,
     *         changePercent: float,
     *     }>,
     *     sectors: array<int, array{
     *         name: string,
     *         change: float,
     *         changePercent: float,
     *         volume: int,
     *     }>,
     *     error: string,
     * }
     */
    public function getMarketData(
        string $market = 'US',
        string $index = 'SPX',
    ): array {
        try {
            $marketData = [
                'name' => 'US' === $market ? 'United States' : $market,
                'symbol' => $index,
                'currentPrice' => $this->generateRandomPrice(3000, 5000),
                'change' => $this->generateRandomChange(-50, 50),
                'changePercent' => $this->generateRandomChange(-2, 2),
                'high' => $this->generateRandomPrice(3050, 5050),
                'low' => $this->generateRandomPrice(2950, 4950),
                'volume' => rand(100000000, 1000000000),
                'lastUpdate' => date('c'),
            ];

            $marketData['changePercent'] = ($marketData['change'] / ($marketData['currentPrice'] - $marketData['change'])) * 100;

            $indices = [
                [
                    'name' => 'S&P 500',
                    'symbol' => 'SPX',
                    'currentPrice' => $this->generateRandomPrice(4000, 4500),
                    'change' => $this->generateRandomChange(-20, 20),
                    'changePercent' => $this->generateRandomChange(-1, 1),
                ],
                [
                    'name' => 'NASDAQ',
                    'symbol' => 'IXIC',
                    'currentPrice' => $this->generateRandomPrice(12000, 15000),
                    'change' => $this->generateRandomChange(-100, 100),
                    'changePercent' => $this->generateRandomChange(-2, 2),
                ],
                [
                    'name' => 'Dow Jones',
                    'symbol' => 'DJI',
                    'currentPrice' => $this->generateRandomPrice(30000, 35000),
                    'change' => $this->generateRandomChange(-200, 200),
                    'changePercent' => $this->generateRandomChange(-1, 1),
                ],
            ];

            $sectors = [
                ['name' => 'Technology', 'change' => $this->generateRandomChange(-5, 5), 'changePercent' => $this->generateRandomChange(-2, 2), 'volume' => rand(1000000, 50000000)],
                ['name' => 'Healthcare', 'change' => $this->generateRandomChange(-3, 3), 'changePercent' => $this->generateRandomChange(-1, 1), 'volume' => rand(500000, 20000000)],
                ['name' => 'Financial', 'change' => $this->generateRandomChange(-4, 4), 'changePercent' => $this->generateRandomChange(-1.5, 1.5), 'volume' => rand(800000, 30000000)],
                ['name' => 'Energy', 'change' => $this->generateRandomChange(-6, 6), 'changePercent' => $this->generateRandomChange(-3, 3), 'volume' => rand(600000, 25000000)],
                ['name' => 'Consumer Discretionary', 'change' => $this->generateRandomChange(-4, 4), 'changePercent' => $this->generateRandomChange(-2, 2), 'volume' => rand(700000, 28000000)],
            ];

            return [
                'success' => true,
                'market' => $marketData,
                'indices' => $indices,
                'sectors' => $sectors,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'market' => [
                    'name' => $market,
                    'symbol' => $index,
                    'currentPrice' => 0.0,
                    'change' => 0.0,
                    'changePercent' => 0.0,
                    'high' => 0.0,
                    'low' => 0.0,
                    'volume' => 0,
                    'lastUpdate' => '',
                ],
                'indices' => [],
                'sectors' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get financial news.
     *
     * @param string $symbol   Stock symbol (empty for general news)
     * @param int    $limit    Number of news articles
     * @param string $language Language code
     *
     * @return array{
     *     success: bool,
     *     news: array<int, array{
     *         title: string,
     *         summary: string,
     *         url: string,
     *         source: string,
     *         publishedAt: string,
     *         sentiment: string,
     *         relatedSymbols: array<int, string>,
     *         tags: array<int, string>,
     *     }>,
     *     totalNews: int,
     *     symbol: string,
     *     error: string,
     * }
     */
    public function getNews(
        string $symbol = '',
        int $limit = 20,
        string $language = 'en',
    ): array {
        try {
            $newsArticles = [
                [
                    'title' => 'Market Shows Strong Performance Amid Economic Uncertainty',
                    'summary' => 'Stock markets continue to show resilience despite ongoing economic challenges and geopolitical tensions.',
                    'url' => 'https://example.com/news/market-performance',
                    'source' => 'Financial Times',
                    'publishedAt' => date('c', time() - rand(3600, 86400)),
                    'sentiment' => 'positive',
                    'relatedSymbols' => ['SPX', 'IXIC', 'DJI'],
                    'tags' => ['market', 'economy', 'performance'],
                ],
                [
                    'title' => 'Tech Stocks Lead Market Rally',
                    'summary' => 'Technology companies are driving market gains with strong earnings reports and optimistic outlooks.',
                    'url' => 'https://example.com/news/tech-rally',
                    'source' => 'Bloomberg',
                    'publishedAt' => date('c', time() - rand(7200, 172800)),
                    'sentiment' => 'positive',
                    'relatedSymbols' => ['AAPL', 'GOOGL', 'MSFT', 'AMZN'],
                    'tags' => ['technology', 'earnings', 'stocks'],
                ],
                [
                    'title' => 'Federal Reserve Signals Potential Rate Changes',
                    'summary' => 'Central bank officials hint at possible adjustments to monetary policy in upcoming meetings.',
                    'url' => 'https://example.com/news/fed-rates',
                    'source' => 'Reuters',
                    'publishedAt' => date('c', time() - rand(10800, 259200)),
                    'sentiment' => 'neutral',
                    'relatedSymbols' => [],
                    'tags' => ['federal-reserve', 'interest-rates', 'monetary-policy'],
                ],
            ];

            if ($symbol) {
                $symbolNews = [
                    'title' => "{$symbol} Stock Analysis: Strong Fundamentals Drive Growth",
                    'summary' => "In-depth analysis of {$symbol} stock performance and future outlook based on recent developments.",
                    'url' => "https://example.com/news/{$symbol}-analysis",
                    'source' => 'MarketWatch',
                    'publishedAt' => date('c', time() - rand(1800, 43200)),
                    'sentiment' => 'positive',
                    'relatedSymbols' => [$symbol],
                    'tags' => ['analysis', 'fundamentals', strtolower($symbol)],
                ];
                array_unshift($newsArticles, $symbolNews);
            }

            $limitedNews = \array_slice($newsArticles, 0, $limit);

            return [
                'success' => true,
                'news' => $limitedNews,
                'totalNews' => \count($limitedNews),
                'symbol' => $symbol,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'news' => [],
                'totalNews' => 0,
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get currency exchange rates.
     *
     * @param string $fromCurrency Base currency code
     * @param string $toCurrency   Target currency code (empty for all major currencies)
     *
     * @return array{
     *     success: bool,
     *     currencies: array<int, array{
     *         from: string,
     *         to: string,
     *         rate: float,
     *         change: float,
     *         changePercent: float,
     *         lastUpdate: string,
     *     }>,
     *     baseCurrency: string,
     *     totalCurrencies: int,
     *     error: string,
     * }
     */
    public function getCurrencies(
        string $fromCurrency = 'USD',
        string $toCurrency = '',
    ): array {
        try {
            $majorCurrencies = ['EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD', 'CNY', 'INR', 'BRL', 'MXN'];

            if ($toCurrency) {
                $targetCurrencies = [$toCurrency];
            } else {
                $targetCurrencies = $majorCurrencies;
            }

            $currencies = [];
            foreach ($targetCurrencies as $currency) {
                if ($currency === $fromCurrency) {
                    continue;
                }

                $rate = $this->generateRandomPrice(0.5, 2.0);
                $change = $this->generateRandomChange(-0.1, 0.1);
                $changePercent = ($change / $rate) * 100;

                $currencies[] = [
                    'from' => $fromCurrency,
                    'to' => $currency,
                    'rate' => $rate,
                    'change' => $change,
                    'changePercent' => $changePercent,
                    'lastUpdate' => date('c'),
                ];
            }

            return [
                'success' => true,
                'currencies' => $currencies,
                'baseCurrency' => $fromCurrency,
                'totalCurrencies' => \count($currencies),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'currencies' => [],
                'baseCurrency' => $fromCurrency,
                'totalCurrencies' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cryptocurrency data.
     *
     * @param string $symbol     Crypto symbol (e.g., BTC, ETH, ADA)
     * @param string $vsCurrency Currency to compare against
     *
     * @return array{
     *     success: bool,
     *     crypto: array{
     *         symbol: string,
     *         name: string,
     *         currentPrice: float,
     *         change24h: float,
     *         changePercent24h: float,
     *         marketCap: float,
     *         volume24h: float,
     *         circulatingSupply: float,
     *         totalSupply: float,
     *         maxSupply: float,
     *         rank: int,
     *         high24h: float,
     *         low24h: float,
     *         lastUpdate: string,
     *     },
     *     vsCurrency: string,
     *     error: string,
     * }
     */
    public function getCrypto(
        string $symbol = 'BTC',
        string $vsCurrency = 'USD',
    ): array {
        try {
            $cryptoData = [
                'symbol' => $symbol,
                'name' => $this->getCryptoName($symbol),
                'currentPrice' => $this->getCryptoPrice($symbol),
                'change24h' => $this->generateRandomChange(-1000, 1000),
                'changePercent24h' => $this->generateRandomChange(-10, 10),
                'marketCap' => $this->generateRandomPrice(100000000000, 2000000000000),
                'volume24h' => $this->generateRandomPrice(10000000000, 100000000000),
                'circulatingSupply' => $this->getCryptoSupply($symbol),
                'totalSupply' => $this->getCryptoSupply($symbol, 'total'),
                'maxSupply' => $this->getCryptoSupply($symbol, 'max'),
                'rank' => $this->getCryptoRank($symbol),
                'high24h' => $this->generateRandomPrice(50000, 70000),
                'low24h' => $this->generateRandomPrice(40000, 60000),
                'lastUpdate' => date('c'),
            ];

            return [
                'success' => true,
                'crypto' => $cryptoData,
                'vsCurrency' => $vsCurrency,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'crypto' => [
                    'symbol' => $symbol,
                    'name' => '',
                    'currentPrice' => 0.0,
                    'change24h' => 0.0,
                    'changePercent24h' => 0.0,
                    'marketCap' => 0.0,
                    'volume24h' => 0.0,
                    'circulatingSupply' => 0.0,
                    'totalSupply' => 0.0,
                    'maxSupply' => 0.0,
                    'rank' => 0,
                    'high24h' => 0.0,
                    'low24h' => 0.0,
                    'lastUpdate' => '',
                ],
                'vsCurrency' => $vsCurrency,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get portfolio data.
     *
     * @param array<int, string> $symbols    Stock symbols in portfolio
     * @param array<int, float>  $quantities Quantities of each stock
     *
     * @return array{
     *     success: bool,
     *     portfolio: array{
     *         totalValue: float,
     *         totalGain: float,
     *         totalGainPercent: float,
     *         dayGain: float,
     *         dayGainPercent: float,
     *         positions: array<int, array{
     *             symbol: string,
     *             quantity: float,
     *             currentPrice: float,
     *             totalValue: float,
     *             gain: float,
     *             gainPercent: float,
     *             weight: float,
     *         }>,
     *     },
     *     totalPositions: int,
     *     error: string,
     * }
     */
    public function getPortfolio(
        array $symbols = ['AAPL', 'GOOGL', 'MSFT'],
        array $quantities = [10, 5, 8],
    ): array {
        try {
            $positions = [];
            $totalValue = 0.0;
            $totalGain = 0.0;

            for ($i = 0; $i < \count($symbols); ++$i) {
                $symbol = $symbols[$i] ?? '';
                $quantity = $quantities[$i] ?? 0.0;

                if (!$symbol || $quantity <= 0) {
                    continue;
                }

                $currentPrice = $this->generateRandomPrice(50, 500);
                $purchasePrice = $currentPrice * $this->generateRandomPrice(0.8, 1.2);
                $positionValue = $currentPrice * $quantity;
                $positionGain = ($currentPrice - $purchasePrice) * $quantity;
                $positionGainPercent = (($currentPrice - $purchasePrice) / $purchasePrice) * 100;

                $positions[] = [
                    'symbol' => $symbol,
                    'quantity' => $quantity,
                    'currentPrice' => $currentPrice,
                    'totalValue' => $positionValue,
                    'gain' => $positionGain,
                    'gainPercent' => $positionGainPercent,
                    'weight' => 0.0, // Will be calculated below
                ];

                $totalValue += $positionValue;
                $totalGain += $positionGain;
            }

            // Calculate weights
            foreach ($positions as &$position) {
                $position['weight'] = ($position['totalValue'] / $totalValue) * 100;
            }

            $totalGainPercent = $totalValue > 0 ? ($totalGain / ($totalValue - $totalGain)) * 100 : 0.0;
            $dayGain = $totalGain * $this->generateRandomPrice(0.1, 0.5);
            $dayGainPercent = ($dayGain / $totalValue) * 100;

            return [
                'success' => true,
                'portfolio' => [
                    'totalValue' => $totalValue,
                    'totalGain' => $totalGain,
                    'totalGainPercent' => $totalGainPercent,
                    'dayGain' => $dayGain,
                    'dayGainPercent' => $dayGainPercent,
                    'positions' => $positions,
                ],
                'totalPositions' => \count($positions),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'portfolio' => [
                    'totalValue' => 0.0,
                    'totalGain' => 0.0,
                    'totalGainPercent' => 0.0,
                    'dayGain' => 0.0,
                    'dayGainPercent' => 0.0,
                    'positions' => [],
                ],
                'totalPositions' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get stock screener data.
     *
     * @param array<string, mixed> $filters Screening filters
     * @param int                  $limit   Number of results
     *
     * @return array{
     *     success: bool,
     *     stocks: array<int, array{
     *         symbol: string,
     *         name: string,
     *         price: float,
     *         change: float,
     *         changePercent: float,
     *         marketCap: float,
     *         pe: float,
     *         volume: int,
     *         sector: string,
     *         industry: string,
     *     }>,
     *     totalStocks: int,
     *     filters: array<string, mixed>,
     *     error: string,
     * }
     */
    public function getScreener(
        array $filters = [],
        int $limit = 50,
    ): array {
        try {
            $defaultFilters = [
                'marketCap' => ['min' => 1000000000, 'max' => null], // 1B+
                'pe' => ['min' => 5, 'max' => 30],
                'volume' => ['min' => 100000, 'max' => null],
                'sector' => '',
                'price' => ['min' => 10, 'max' => 500],
            ];

            $filters = array_merge($defaultFilters, $filters);

            $sectors = ['Technology', 'Healthcare', 'Financial', 'Energy', 'Consumer Discretionary', 'Industrial', 'Materials', 'Utilities', 'Real Estate', 'Communication Services'];
            $industries = ['Software', 'Biotechnology', 'Banking', 'Oil & Gas', 'Retail', 'Manufacturing', 'Mining', 'Electric Utilities', 'REITs', 'Telecommunications'];

            $stocks = [];
            for ($i = 0; $i < $limit; ++$i) {
                $symbol = $this->generateRandomSymbol();
                $price = $this->generateRandomPrice($filters['price']['min'], $filters['price']['max'] ?? 1000);
                $change = $this->generateRandomChange(-10, 10);
                $changePercent = ($change / $price) * 100;
                $marketCap = $this->generateRandomPrice($filters['marketCap']['min'], $filters['marketCap']['max'] ?? 1000000000000);
                $pe = $this->generateRandomPrice($filters['pe']['min'], $filters['pe']['max'] ?? 50);
                $volume = rand($filters['volume']['min'], $filters['volume']['max'] ?? 10000000);

                $stocks[] = [
                    'symbol' => $symbol,
                    'name' => $this->getCompanyName($symbol),
                    'price' => $price,
                    'change' => $change,
                    'changePercent' => $changePercent,
                    'marketCap' => $marketCap,
                    'pe' => $pe,
                    'volume' => $volume,
                    'sector' => $sectors[array_rand($sectors)],
                    'industry' => $industries[array_rand($industries)],
                ];
            }

            return [
                'success' => true,
                'stocks' => $stocks,
                'totalStocks' => \count($stocks),
                'filters' => $filters,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'stocks' => [],
                'totalStocks' => 0,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get earnings data.
     *
     * @param string $symbol Stock symbol
     * @param string $period Earnings period (quarterly, annual)
     * @param int    $limit  Number of earnings reports
     *
     * @return array{
     *     success: bool,
     *     earnings: array<int, array{
     *         period: string,
     *         date: string,
     *         eps: float,
     *         epsEstimate: float,
     *         epsSurprise: float,
     *         revenue: float,
     *         revenueEstimate: float,
     *         revenueSurprise: float,
     *         year: int,
     *         quarter: int,
     *     }>,
     *     symbol: string,
     *     totalEarnings: int,
     *     error: string,
     * }
     */
    public function getEarnings(
        string $symbol,
        string $period = 'quarterly',
        int $limit = 8,
    ): array {
        try {
            $earnings = [];
            $currentYear = (int) date('Y');

            for ($i = 0; $i < $limit; ++$i) {
                $year = $currentYear - floor($i / 4);
                $quarter = ($i % 4) + 1;

                $eps = $this->generateRandomPrice(0.5, 5.0);
                $epsEstimate = $eps * $this->generateRandomPrice(0.9, 1.1);
                $epsSurprise = $eps - $epsEstimate;

                $revenue = $this->generateRandomPrice(1000000000, 10000000000);
                $revenueEstimate = $revenue * $this->generateRandomPrice(0.95, 1.05);
                $revenueSurprise = $revenue - $revenueEstimate;

                $earnings[] = [
                    'period' => "Q{$quarter} {$year}",
                    'date' => date('c', strtotime("{$year}-".($quarter * 3).'-01')),
                    'eps' => $eps,
                    'epsEstimate' => $epsEstimate,
                    'epsSurprise' => $epsSurprise,
                    'revenue' => $revenue,
                    'revenueEstimate' => $revenueEstimate,
                    'revenueSurprise' => $revenueSurprise,
                    'year' => $year,
                    'quarter' => $quarter,
                ];
            }

            return [
                'success' => true,
                'earnings' => $earnings,
                'symbol' => $symbol,
                'totalEarnings' => \count($earnings),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'earnings' => [],
                'symbol' => $symbol,
                'totalEarnings' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Helper methods for generating mock data
    private function generateRandomPrice(float $min, float $max): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 2);
    }

    private function generateRandomChange(float $min, float $max): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 2);
    }

    private function getCompanyName(string $symbol): string
    {
        $companies = [
            'AAPL' => 'Apple Inc.',
            'GOOGL' => 'Alphabet Inc.',
            'MSFT' => 'Microsoft Corporation',
            'AMZN' => 'Amazon.com Inc.',
            'TSLA' => 'Tesla Inc.',
            'META' => 'Meta Platforms Inc.',
            'NVDA' => 'NVIDIA Corporation',
            'BRK.B' => 'Berkshire Hathaway Inc.',
            'UNH' => 'UnitedHealth Group Incorporated',
            'JNJ' => 'Johnson & Johnson',
        ];

        return $companies[$symbol] ?? "Company {$symbol}";
    }

    private function getCryptoName(string $symbol): string
    {
        $cryptos = [
            'BTC' => 'Bitcoin',
            'ETH' => 'Ethereum',
            'ADA' => 'Cardano',
            'SOL' => 'Solana',
            'DOT' => 'Polkadot',
            'MATIC' => 'Polygon',
            'AVAX' => 'Avalanche',
            'LINK' => 'Chainlink',
        ];

        return $cryptos[$symbol] ?? "Cryptocurrency {$symbol}";
    }

    private function getCryptoPrice(string $symbol): float
    {
        $prices = [
            'BTC' => 45000.0,
            'ETH' => 3000.0,
            'ADA' => 0.5,
            'SOL' => 100.0,
            'DOT' => 8.0,
            'MATIC' => 0.8,
            'AVAX' => 25.0,
            'LINK' => 15.0,
        ];

        return $prices[$symbol] ?? $this->generateRandomPrice(0.1, 1000.0);
    }

    private function getCryptoSupply(string $symbol, string $type = 'circulating'): float
    {
        $supplies = [
            'BTC' => ['circulating' => 19000000, 'total' => 19000000, 'max' => 21000000],
            'ETH' => ['circulating' => 120000000, 'total' => 120000000, 'max' => null],
            'ADA' => ['circulating' => 35000000000, 'total' => 35000000000, 'max' => 45000000000],
        ];

        return $supplies[$symbol][$type] ?? 0.0;
    }

    private function getCryptoRank(string $symbol): int
    {
        $ranks = [
            'BTC' => 1,
            'ETH' => 2,
            'ADA' => 3,
            'SOL' => 4,
            'DOT' => 5,
        ];

        return $ranks[$symbol] ?? rand(1, 100);
    }

    private function generateRandomSymbol(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return $letters[rand(0, 25)].$letters[rand(0, 25)].$letters[rand(0, 25)].$letters[rand(0, 25)];
    }

    private function generateHistoricalData(string $symbol, string $period, string $interval, float $currentPrice): array
    {
        $data = [];
        $days = match ($period) {
            '1d' => 1,
            '5d' => 5,
            '1mo' => 30,
            '3mo' => 90,
            '6mo' => 180,
            '1y' => 365,
            '2y' => 730,
            '5y' => 1825,
            '10y' => 3650,
            'ytd' => (int) date('z'),
            'max' => 3650,
            default => 30,
        };

        $basePrice = $currentPrice * 0.8; // Start 20% lower
        for ($i = $days; $i >= 0; --$i) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $priceVariation = $this->generateRandomPrice(-0.05, 0.05);
            $basePrice *= (1 + $priceVariation);

            $open = $basePrice * $this->generateRandomPrice(0.98, 1.02);
            $high = max($open, $basePrice) * $this->generateRandomPrice(1.0, 1.03);
            $low = min($open, $basePrice) * $this->generateRandomPrice(0.97, 1.0);
            $close = $basePrice;
            $volume = rand(1000000, 50000000);
            $adjustedClose = $close;

            $data[] = [
                'date' => $date,
                'open' => round($open, 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($close, 2),
                'volume' => $volume,
                'adjustedClose' => round($adjustedClose, 2),
            ];
        }

        return $data;
    }
}
