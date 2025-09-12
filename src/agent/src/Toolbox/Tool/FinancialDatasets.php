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
#[AsTool('financial_datasets_search', 'Tool that searches financial datasets')]
#[AsTool('financial_datasets_get_company_data', 'Tool that gets company financial data', method: 'getCompanyData')]
#[AsTool('financial_datasets_get_stock_prices', 'Tool that gets stock price data', method: 'getStockPrices')]
#[AsTool('financial_datasets_get_earnings', 'Tool that gets earnings data', method: 'getEarnings')]
#[AsTool('financial_datasets_get_dividends', 'Tool that gets dividend data', method: 'getDividends')]
#[AsTool('financial_datasets_get_splits', 'Tool that gets stock split data', method: 'getSplits')]
#[AsTool('financial_datasets_get_insider_trading', 'Tool that gets insider trading data', method: 'getInsiderTrading')]
#[AsTool('financial_datasets_get_fundamentals', 'Tool that gets fundamental data', method: 'getFundamentals')]
final readonly class FinancialDatasets
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey = '',
        private string $baseUrl = 'https://financialmodelingprep.com/api/v3',
        private array $options = [],
    ) {
    }

    /**
     * Search financial datasets.
     *
     * @param string $query    Search query
     * @param string $type     Dataset type (stock, etf, mutual-fund, index, forex, crypto)
     * @param string $exchange Exchange (NASDAQ, NYSE, AMEX, etc.)
     * @param int    $limit    Number of results
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array{
     *         symbol: string,
     *         name: string,
     *         price: float,
     *         exchange: string,
     *         exchangeShortName: string,
     *         type: string,
     *         country: string,
     *         currency: string,
     *         isEtf: bool,
     *         isActivelyTrading: bool,
     *         marketCap: float,
     *         volume: int,
     *         change: float,
     *         changePercent: float,
     *     }>,
     *     count: int,
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        string $type = 'stock',
        string $exchange = '',
        int $limit = 50,
    ): array {
        try {
            $params = [
                'query' => $query,
                'limit' => max(1, min($limit, 1000)),
            ];

            if ($this->apiKey) {
                $params['apikey'] = $this->apiKey;
            }

            $endpoint = match ($type) {
                'stock' => 'stock-screener',
                'etf' => 'etf-screener',
                'mutual-fund' => 'mutual-fund-screener',
                'index' => 'index-screener',
                'forex' => 'forex-screener',
                'crypto' => 'crypto-screener',
                default => 'stock-screener',
            };

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/{$endpoint}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'results' => array_map(fn ($result) => [
                    'symbol' => $result['symbol'] ?? '',
                    'name' => $result['name'] ?? '',
                    'price' => $result['price'] ?? 0.0,
                    'exchange' => $result['exchange'] ?? '',
                    'exchangeShortName' => $result['exchangeShortName'] ?? '',
                    'type' => $result['type'] ?? $type,
                    'country' => $result['country'] ?? '',
                    'currency' => $result['currency'] ?? '',
                    'isEtf' => $result['isEtf'] ?? false,
                    'isActivelyTrading' => $result['isActivelyTrading'] ?? false,
                    'marketCap' => $result['marketCap'] ?? 0.0,
                    'volume' => $result['volume'] ?? 0,
                    'change' => $result['change'] ?? 0.0,
                    'changePercent' => $result['changePercent'] ?? 0.0,
                ], $data),
                'count' => \count($data),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get company financial data.
     *
     * @param string $symbol Stock symbol
     * @param string $period Period (annual, quarter)
     * @param int    $limit  Number of periods
     *
     * @return array{
     *     success: bool,
     *     company: array{
     *         symbol: string,
     *         companyName: string,
     *         currency: string,
     *         industry: string,
     *         website: string,
     *         description: string,
     *         ceo: string,
     *         sector: string,
     *         country: string,
     *         fullTimeEmployees: string,
     *         phone: string,
     *         address: string,
     *         city: string,
     *         state: string,
     *         zip: string,
     *         dcfDiff: float,
     *         dcf: float,
     *         image: string,
     *         ipoDate: string,
     *         defaultImage: bool,
     *         isEtf: bool,
     *         isActivelyTrading: bool,
     *         isADR: bool,
     *         isFund: bool,
     *         isReit: bool,
     *     },
     *     financials: array<int, array{
     *         date: string,
     *         symbol: string,
     *         reportedCurrency: string,
     *         cik: string,
     *         fillingDate: string,
     *         acceptedDate: string,
     *         calendarYear: string,
     *         period: string,
     *         revenue: float,
     *         costOfRevenue: float,
     *         grossProfit: float,
     *         grossProfitRatio: float,
     *         researchAndDevelopmentExpenses: float,
     *         generalAndAdministrativeExpenses: float,
     *         sellingAndMarketingExpenses: float,
     *         sellingGeneralAndAdministrativeExpenses: float,
     *         otherExpenses: float,
     *         operatingExpenses: float,
     *         costAndExpenses: float,
     *         interestIncome: float,
     *         interestExpense: float,
     *         depreciationAndAmortization: float,
     *         ebitda: float,
     *         ebitdaratio: float,
     *         operatingIncome: float,
     *         operatingIncomeRatio: float,
     *         totalOtherIncomeExpensesNet: float,
     *         incomeBeforeTax: float,
     *         incomeBeforeTaxRatio: float,
     *         incomeTaxExpense: float,
     *         netIncome: float,
     *         netIncomeRatio: float,
     *         eps: float,
     *         epsdiluted: float,
     *         weightedAverageShsOut: float,
     *         weightedAverageShsOutDil: float,
     *         link: string,
     *         finalLink: string,
     *     }>,
     *     error: string,
     * }
     */
    public function getCompanyData(
        string $symbol,
        string $period = 'annual',
        int $limit = 5,
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 10)),
            ];

            if ($this->apiKey) {
                $params['apikey'] = $this->apiKey;
            }

            // Get company profile
            $profileResponse = $this->httpClient->request('GET', "{$this->baseUrl}/profile/{$symbol}", [
                'query' => array_merge($this->options, $params),
            ]);

            $profileData = $profileResponse->toArray();
            $company = $profileData[0] ?? [];

            // Get income statement
            $financialsResponse = $this->httpClient->request('GET', "{$this->baseUrl}/income-statement/{$symbol}", [
                'query' => array_merge($this->options, $params),
            ]);

            $financialsData = $financialsResponse->toArray();

            return [
                'success' => true,
                'company' => [
                    'symbol' => $company['symbol'] ?? $symbol,
                    'companyName' => $company['companyName'] ?? '',
                    'currency' => $company['currency'] ?? '',
                    'industry' => $company['industry'] ?? '',
                    'website' => $company['website'] ?? '',
                    'description' => $company['description'] ?? '',
                    'ceo' => $company['ceo'] ?? '',
                    'sector' => $company['sector'] ?? '',
                    'country' => $company['country'] ?? '',
                    'fullTimeEmployees' => $company['fullTimeEmployees'] ?? '',
                    'phone' => $company['phone'] ?? '',
                    'address' => $company['address'] ?? '',
                    'city' => $company['city'] ?? '',
                    'state' => $company['state'] ?? '',
                    'zip' => $company['zip'] ?? '',
                    'dcfDiff' => $company['dcfDiff'] ?? 0.0,
                    'dcf' => $company['dcf'] ?? 0.0,
                    'image' => $company['image'] ?? '',
                    'ipoDate' => $company['ipoDate'] ?? '',
                    'defaultImage' => $company['defaultImage'] ?? false,
                    'isEtf' => $company['isEtf'] ?? false,
                    'isActivelyTrading' => $company['isActivelyTrading'] ?? false,
                    'isADR' => $company['isADR'] ?? false,
                    'isFund' => $company['isFund'] ?? false,
                    'isReit' => $company['isReit'] ?? false,
                ],
                'financials' => array_map(fn ($financial) => [
                    'date' => $financial['date'] ?? '',
                    'symbol' => $financial['symbol'] ?? $symbol,
                    'reportedCurrency' => $financial['reportedCurrency'] ?? '',
                    'cik' => $financial['cik'] ?? '',
                    'fillingDate' => $financial['fillingDate'] ?? '',
                    'acceptedDate' => $financial['acceptedDate'] ?? '',
                    'calendarYear' => $financial['calendarYear'] ?? '',
                    'period' => $financial['period'] ?? '',
                    'revenue' => $financial['revenue'] ?? 0.0,
                    'costOfRevenue' => $financial['costOfRevenue'] ?? 0.0,
                    'grossProfit' => $financial['grossProfit'] ?? 0.0,
                    'grossProfitRatio' => $financial['grossProfitRatio'] ?? 0.0,
                    'researchAndDevelopmentExpenses' => $financial['researchAndDevelopmentExpenses'] ?? 0.0,
                    'generalAndAdministrativeExpenses' => $financial['generalAndAdministrativeExpenses'] ?? 0.0,
                    'sellingAndMarketingExpenses' => $financial['sellingAndMarketingExpenses'] ?? 0.0,
                    'sellingGeneralAndAdministrativeExpenses' => $financial['sellingGeneralAndAdministrativeExpenses'] ?? 0.0,
                    'otherExpenses' => $financial['otherExpenses'] ?? 0.0,
                    'operatingExpenses' => $financial['operatingExpenses'] ?? 0.0,
                    'costAndExpenses' => $financial['costAndExpenses'] ?? 0.0,
                    'interestIncome' => $financial['interestIncome'] ?? 0.0,
                    'interestExpense' => $financial['interestExpense'] ?? 0.0,
                    'depreciationAndAmortization' => $financial['depreciationAndAmortization'] ?? 0.0,
                    'ebitda' => $financial['ebitda'] ?? 0.0,
                    'ebitdaratio' => $financial['ebitdaratio'] ?? 0.0,
                    'operatingIncome' => $financial['operatingIncome'] ?? 0.0,
                    'operatingIncomeRatio' => $financial['operatingIncomeRatio'] ?? 0.0,
                    'totalOtherIncomeExpensesNet' => $financial['totalOtherIncomeExpensesNet'] ?? 0.0,
                    'incomeBeforeTax' => $financial['incomeBeforeTax'] ?? 0.0,
                    'incomeBeforeTaxRatio' => $financial['incomeBeforeTaxRatio'] ?? 0.0,
                    'incomeTaxExpense' => $financial['incomeTaxExpense'] ?? 0.0,
                    'netIncome' => $financial['netIncome'] ?? 0.0,
                    'netIncomeRatio' => $financial['netIncomeRatio'] ?? 0.0,
                    'eps' => $financial['eps'] ?? 0.0,
                    'epsdiluted' => $financial['epsdiluted'] ?? 0.0,
                    'weightedAverageShsOut' => $financial['weightedAverageShsOut'] ?? 0.0,
                    'weightedAverageShsOutDil' => $financial['weightedAverageShsOutDil'] ?? 0.0,
                    'link' => $financial['link'] ?? '',
                    'finalLink' => $financial['finalLink'] ?? '',
                ], $financialsData),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'company' => [
                    'symbol' => $symbol,
                    'companyName' => '',
                    'currency' => '',
                    'industry' => '',
                    'website' => '',
                    'description' => '',
                    'ceo' => '',
                    'sector' => '',
                    'country' => '',
                    'fullTimeEmployees' => '',
                    'phone' => '',
                    'address' => '',
                    'city' => '',
                    'state' => '',
                    'zip' => '',
                    'dcfDiff' => 0.0,
                    'dcf' => 0.0,
                    'image' => '',
                    'ipoDate' => '',
                    'defaultImage' => false,
                    'isEtf' => false,
                    'isActivelyTrading' => false,
                    'isADR' => false,
                    'isFund' => false,
                    'isReit' => false,
                ],
                'financials' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get stock price data.
     *
     * @param string $symbol     Stock symbol
     * @param string $from       Start date (YYYY-MM-DD)
     * @param string $to         End date (YYYY-MM-DD)
     * @param string $timeseries Time series (1day, 5day, 1month, 3month, 1year, 5year, max)
     *
     * @return array{
     *     success: bool,
     *     prices: array<int, array{
     *         date: string,
     *         open: float,
     *         high: float,
     *         low: float,
     *         close: float,
     *         adjClose: float,
     *         volume: int,
     *         unadjustedVolume: float,
     *         change: float,
     *         changePercent: float,
     *         vwap: float,
     *         label: string,
     *         changeOverTime: float,
     *     }>,
     *     symbol: string,
     *     historical: bool,
     *     error: string,
     * }
     */
    public function getStockPrices(
        string $symbol,
        string $from = '',
        string $to = '',
        string $timeseries = '1month',
    ): array {
        try {
            $params = [];

            if ($from && $to) {
                $params['from'] = $from;
                $params['to'] = $to;
            } else {
                $params['timeseries'] = $timeseries;
            }

            if ($this->apiKey) {
                $params['apikey'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/historical-price-full/{$symbol}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();
            $historicalData = $data['historical'] ?? [];

            return [
                'success' => true,
                'prices' => array_map(fn ($price) => [
                    'date' => $price['date'] ?? '',
                    'open' => $price['open'] ?? 0.0,
                    'high' => $price['high'] ?? 0.0,
                    'low' => $price['low'] ?? 0.0,
                    'close' => $price['close'] ?? 0.0,
                    'adjClose' => $price['adjClose'] ?? 0.0,
                    'volume' => $price['volume'] ?? 0,
                    'unadjustedVolume' => $price['unadjustedVolume'] ?? 0.0,
                    'change' => $price['change'] ?? 0.0,
                    'changePercent' => $price['changePercent'] ?? 0.0,
                    'vwap' => $price['vwap'] ?? 0.0,
                    'label' => $price['label'] ?? '',
                    'changeOverTime' => $price['changeOverTime'] ?? 0.0,
                ], $historicalData),
                'symbol' => $data['symbol'] ?? $symbol,
                'historical' => $data['historical'] ?? true,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'prices' => [],
                'symbol' => $symbol,
                'historical' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get earnings data.
     *
     * @param string $symbol Stock symbol
     * @param int    $limit  Number of quarters
     *
     * @return array{
     *     success: bool,
     *     earnings: array<int, array{
     *         date: string,
     *         symbol: string,
     *         reportedDate: string,
     *         reportedEPS: float,
     *         estimatedEPS: float,
     *         surprise: float,
     *         surprisePercentage: float,
     *         fiscalDateEnding: string,
     *         fiscalQuarter: int,
     *         fiscalYear: int,
     *     }>,
     *     error: string,
     * }
     */
    public function getEarnings(
        string $symbol,
        int $limit = 4,
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 20)),
            ];

            if ($this->apiKey) {
                $params['apikey'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/earning_calendar/{$symbol}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'earnings' => array_map(fn ($earning) => [
                    'date' => $earning['date'] ?? '',
                    'symbol' => $earning['symbol'] ?? $symbol,
                    'reportedDate' => $earning['reportedDate'] ?? '',
                    'reportedEPS' => $earning['reportedEPS'] ?? 0.0,
                    'estimatedEPS' => $earning['estimatedEPS'] ?? 0.0,
                    'surprise' => $earning['surprise'] ?? 0.0,
                    'surprisePercentage' => $earning['surprisePercentage'] ?? 0.0,
                    'fiscalDateEnding' => $earning['fiscalDateEnding'] ?? '',
                    'fiscalQuarter' => $earning['fiscalQuarter'] ?? 0,
                    'fiscalYear' => $earning['fiscalYear'] ?? 0,
                ], $data),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'earnings' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get dividend data.
     *
     * @param string $symbol Stock symbol
     * @param int    $limit  Number of dividends
     *
     * @return array{
     *     success: bool,
     *     dividends: array<int, array{
     *         date: string,
     *         label: string,
     *         adjDividend: float,
     *         dividend: float,
     *         recordDate: string,
     *         paymentDate: string,
     *         declarationDate: string,
     *     }>,
     *     error: string,
     * }
     */
    public function getDividends(
        string $symbol,
        int $limit = 10,
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 100)),
            ];

            if ($this->apiKey) {
                $params['apikey'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/historical-price-full/stock_dividend/{$symbol}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();
            $historicalData = $data['historical'] ?? [];

            return [
                'success' => true,
                'dividends' => array_map(fn ($dividend) => [
                    'date' => $dividend['date'] ?? '',
                    'label' => $dividend['label'] ?? '',
                    'adjDividend' => $dividend['adjDividend'] ?? 0.0,
                    'dividend' => $dividend['dividend'] ?? 0.0,
                    'recordDate' => $dividend['recordDate'] ?? '',
                    'paymentDate' => $dividend['paymentDate'] ?? '',
                    'declarationDate' => $dividend['declarationDate'] ?? '',
                ], $historicalData),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'dividends' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get stock split data.
     *
     * @param string $symbol Stock symbol
     * @param int    $limit  Number of splits
     *
     * @return array{
     *     success: bool,
     *     splits: array<int, array{
     *         date: string,
     *         label: string,
     *         numerator: float,
     *         denominator: float,
     *         splitRatio: string,
     *     }>,
     *     error: string,
     * }
     */
    public function getSplits(
        string $symbol,
        int $limit = 10,
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 100)),
            ];

            if ($this->apiKey) {
                $params['apikey'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/historical-price-full/stock_split/{$symbol}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();
            $historicalData = $data['historical'] ?? [];

            return [
                'success' => true,
                'splits' => array_map(fn ($split) => [
                    'date' => $split['date'] ?? '',
                    'label' => $split['label'] ?? '',
                    'numerator' => $split['numerator'] ?? 0.0,
                    'denominator' => $split['denominator'] ?? 0.0,
                    'splitRatio' => $split['splitRatio'] ?? '',
                ], $historicalData),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'splits' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get insider trading data.
     *
     * @param string $symbol Stock symbol
     * @param int    $limit  Number of transactions
     *
     * @return array{
     *     success: bool,
     *     insiderTrading: array<int, array{
     *         symbol: string,
     *         name: string,
     *         cik: string,
     *         reportingCik: string,
     *         ownerType: string,
     *         isDirector: bool,
     *         isOfficer: bool,
     *         isTenPercentOwner: bool,
     *         isOther: bool,
     *         otherText: string,
     *         officerTitle: string,
     *         dateOfTransaction: string,
     *         dateOfOriginalExecution: string,
     *         numberOfSecuritiesTransacted: float,
     *         numberOfSecuritiesOwned: float,
     *         sharesOwnedFollowingTransaction: float,
     *         ownedFollowingTransaction: float,
     *         transactionAcquiredDisposedCode: string,
     *         transactionAcquiredDisposedCodeDescription: string,
     *         transactionPricePerShare: float,
     *         transactionShares: float,
     *         transactionTotalValue: float,
     *         transactionCode: string,
     *         transactionCodeDescription: string,
     *         transactionTimeliness: string,
     *         transactionTimelinessDescription: string,
     *         filingDate: string,
     *         filingUrl: string,
     *         amendmentDate: string,
     *         amendmentUrl: string,
     *         reportOrFilingPeriodDateOfEvent: string,
     *         isNoSecuritiesInvolved: bool,
     *         securityTitle: string,
     *         securityClass: string,
     *         securitiesAcquired: float,
     *         securitiesDisposed: float,
     *         securitiesOwned: float,
     *         securitiesOwnedFollowingTransaction: float,
     *         natureOfOwnership: string,
     *         natureOfOwnershipDescription: string,
     *         securityAcquiredDisposed: string,
     *         securityAcquiredDisposedDescription: string,
     *         transactionDate: string,
     *         transactionShares: float,
     *         transactionPricePerShare: float,
     *         transactionAcquiredDisposedCode: string,
     *         transactionAcquiredDisposedCodeDescription: string,
     *         underlyingSecurityTitle: string,
     *         underlyingSecurityShares: float,
     *         underlyingSecurityValue: float,
     *         ownershipNature: string,
     *         ownershipNatureDescription: string,
     *     }>,
     *     error: string,
     * }
     */
    public function getInsiderTrading(
        string $symbol,
        int $limit = 10,
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 100)),
            ];

            if ($this->apiKey) {
                $params['apikey'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/insider-trading", [
                'query' => array_merge($this->options, array_merge($params, ['symbol' => $symbol])),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'insiderTrading' => array_map(fn ($transaction) => [
                    'symbol' => $transaction['symbol'] ?? $symbol,
                    'name' => $transaction['name'] ?? '',
                    'cik' => $transaction['cik'] ?? '',
                    'reportingCik' => $transaction['reportingCik'] ?? '',
                    'ownerType' => $transaction['ownerType'] ?? '',
                    'isDirector' => $transaction['isDirector'] ?? false,
                    'isOfficer' => $transaction['isOfficer'] ?? false,
                    'isTenPercentOwner' => $transaction['isTenPercentOwner'] ?? false,
                    'isOther' => $transaction['isOther'] ?? false,
                    'otherText' => $transaction['otherText'] ?? '',
                    'officerTitle' => $transaction['officerTitle'] ?? '',
                    'dateOfTransaction' => $transaction['dateOfTransaction'] ?? '',
                    'dateOfOriginalExecution' => $transaction['dateOfOriginalExecution'] ?? '',
                    'numberOfSecuritiesTransacted' => $transaction['numberOfSecuritiesTransacted'] ?? 0.0,
                    'numberOfSecuritiesOwned' => $transaction['numberOfSecuritiesOwned'] ?? 0.0,
                    'sharesOwnedFollowingTransaction' => $transaction['sharesOwnedFollowingTransaction'] ?? 0.0,
                    'ownedFollowingTransaction' => $transaction['ownedFollowingTransaction'] ?? 0.0,
                    'transactionAcquiredDisposedCode' => $transaction['transactionAcquiredDisposedCode'] ?? '',
                    'transactionAcquiredDisposedCodeDescription' => $transaction['transactionAcquiredDisposedCodeDescription'] ?? '',
                    'transactionPricePerShare' => $transaction['transactionPricePerShare'] ?? 0.0,
                    'transactionShares' => $transaction['transactionShares'] ?? 0.0,
                    'transactionTotalValue' => $transaction['transactionTotalValue'] ?? 0.0,
                    'transactionCode' => $transaction['transactionCode'] ?? '',
                    'transactionCodeDescription' => $transaction['transactionCodeDescription'] ?? '',
                    'transactionTimeliness' => $transaction['transactionTimeliness'] ?? '',
                    'transactionTimelinessDescription' => $transaction['transactionTimelinessDescription'] ?? '',
                    'filingDate' => $transaction['filingDate'] ?? '',
                    'filingUrl' => $transaction['filingUrl'] ?? '',
                    'amendmentDate' => $transaction['amendmentDate'] ?? '',
                    'amendmentUrl' => $transaction['amendmentUrl'] ?? '',
                    'reportOrFilingPeriodDateOfEvent' => $transaction['reportOrFilingPeriodDateOfEvent'] ?? '',
                    'isNoSecuritiesInvolved' => $transaction['isNoSecuritiesInvolved'] ?? false,
                    'securityTitle' => $transaction['securityTitle'] ?? '',
                    'securityClass' => $transaction['securityClass'] ?? '',
                    'securitiesAcquired' => $transaction['securitiesAcquired'] ?? 0.0,
                    'securitiesDisposed' => $transaction['securitiesDisposed'] ?? 0.0,
                    'securitiesOwned' => $transaction['securitiesOwned'] ?? 0.0,
                    'securitiesOwnedFollowingTransaction' => $transaction['securitiesOwnedFollowingTransaction'] ?? 0.0,
                    'natureOfOwnership' => $transaction['natureOfOwnership'] ?? '',
                    'natureOfOwnershipDescription' => $transaction['natureOfOwnershipDescription'] ?? '',
                    'securityAcquiredDisposed' => $transaction['securityAcquiredDisposed'] ?? '',
                    'securityAcquiredDisposedDescription' => $transaction['securityAcquiredDisposedDescription'] ?? '',
                    'transactionDate' => $transaction['transactionDate'] ?? '',
                    'underlyingSecurityTitle' => $transaction['underlyingSecurityTitle'] ?? '',
                    'underlyingSecurityShares' => $transaction['underlyingSecurityShares'] ?? 0.0,
                    'underlyingSecurityValue' => $transaction['underlyingSecurityValue'] ?? 0.0,
                    'ownershipNature' => $transaction['ownershipNature'] ?? '',
                    'ownershipNatureDescription' => $transaction['ownershipNatureDescription'] ?? '',
                ], $data),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'insiderTrading' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get fundamental data.
     *
     * @param string $symbol Stock symbol
     * @param int    $limit  Number of periods
     *
     * @return array{
     *     success: bool,
     *     fundamentals: array<int, array{
     *         symbol: string,
     *         date: string,
     *         reportedCurrency: string,
     *         cik: string,
     *         fillingDate: string,
     *         acceptedDate: string,
     *         calendarYear: string,
     *         period: string,
     *         revenue: float,
     *         costOfRevenue: float,
     *         grossProfit: float,
     *         grossProfitRatio: float,
     *         researchAndDevelopmentExpenses: float,
     *         generalAndAdministrativeExpenses: float,
     *         sellingAndMarketingExpenses: float,
     *         sellingGeneralAndAdministrativeExpenses: float,
     *         otherExpenses: float,
     *         operatingExpenses: float,
     *         costAndExpenses: float,
     *         interestIncome: float,
     *         interestExpense: float,
     *         depreciationAndAmortization: float,
     *         ebitda: float,
     *         ebitdaratio: float,
     *         operatingIncome: float,
     *         operatingIncomeRatio: float,
     *         totalOtherIncomeExpensesNet: float,
     *         incomeBeforeTax: float,
     *         incomeBeforeTaxRatio: float,
     *         incomeTaxExpense: float,
     *         netIncome: float,
     *         netIncomeRatio: float,
     *         eps: float,
     *         epsdiluted: float,
     *         weightedAverageShsOut: float,
     *         weightedAverageShsOutDil: float,
     *         link: string,
     *         finalLink: string,
     *     }>,
     *     error: string,
     * }
     */
    public function getFundamentals(
        string $symbol,
        int $limit = 5,
    ): array {
        try {
            $params = [
                'limit' => max(1, min($limit, 10)),
            ];

            if ($this->apiKey) {
                $params['apikey'] = $this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/income-statement/{$symbol}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'fundamentals' => array_map(fn ($fundamental) => [
                    'symbol' => $fundamental['symbol'] ?? $symbol,
                    'date' => $fundamental['date'] ?? '',
                    'reportedCurrency' => $fundamental['reportedCurrency'] ?? '',
                    'cik' => $fundamental['cik'] ?? '',
                    'fillingDate' => $fundamental['fillingDate'] ?? '',
                    'acceptedDate' => $fundamental['acceptedDate'] ?? '',
                    'calendarYear' => $fundamental['calendarYear'] ?? '',
                    'period' => $fundamental['period'] ?? '',
                    'revenue' => $fundamental['revenue'] ?? 0.0,
                    'costOfRevenue' => $fundamental['costOfRevenue'] ?? 0.0,
                    'grossProfit' => $fundamental['grossProfit'] ?? 0.0,
                    'grossProfitRatio' => $fundamental['grossProfitRatio'] ?? 0.0,
                    'researchAndDevelopmentExpenses' => $fundamental['researchAndDevelopmentExpenses'] ?? 0.0,
                    'generalAndAdministrativeExpenses' => $fundamental['generalAndAdministrativeExpenses'] ?? 0.0,
                    'sellingAndMarketingExpenses' => $fundamental['sellingAndMarketingExpenses'] ?? 0.0,
                    'sellingGeneralAndAdministrativeExpenses' => $fundamental['sellingGeneralAndAdministrativeExpenses'] ?? 0.0,
                    'otherExpenses' => $fundamental['otherExpenses'] ?? 0.0,
                    'operatingExpenses' => $fundamental['operatingExpenses'] ?? 0.0,
                    'costAndExpenses' => $fundamental['costAndExpenses'] ?? 0.0,
                    'interestIncome' => $fundamental['interestIncome'] ?? 0.0,
                    'interestExpense' => $fundamental['interestExpense'] ?? 0.0,
                    'depreciationAndAmortization' => $fundamental['depreciationAndAmortization'] ?? 0.0,
                    'ebitda' => $fundamental['ebitda'] ?? 0.0,
                    'ebitdaratio' => $fundamental['ebitdaratio'] ?? 0.0,
                    'operatingIncome' => $fundamental['operatingIncome'] ?? 0.0,
                    'operatingIncomeRatio' => $fundamental['operatingIncomeRatio'] ?? 0.0,
                    'totalOtherIncomeExpensesNet' => $fundamental['totalOtherIncomeExpensesNet'] ?? 0.0,
                    'incomeBeforeTax' => $fundamental['incomeBeforeTax'] ?? 0.0,
                    'incomeBeforeTaxRatio' => $fundamental['incomeBeforeTaxRatio'] ?? 0.0,
                    'incomeTaxExpense' => $fundamental['incomeTaxExpense'] ?? 0.0,
                    'netIncome' => $fundamental['netIncome'] ?? 0.0,
                    'netIncomeRatio' => $fundamental['netIncomeRatio'] ?? 0.0,
                    'eps' => $fundamental['eps'] ?? 0.0,
                    'epsdiluted' => $fundamental['epsdiluted'] ?? 0.0,
                    'weightedAverageShsOut' => $fundamental['weightedAverageShsOut'] ?? 0.0,
                    'weightedAverageShsOutDil' => $fundamental['weightedAverageShsOutDil'] ?? 0.0,
                    'link' => $fundamental['link'] ?? '',
                    'finalLink' => $fundamental['finalLink'] ?? '',
                ], $data),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'fundamentals' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}
