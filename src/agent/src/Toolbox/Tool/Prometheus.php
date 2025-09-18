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
#[AsTool('prometheus_query', 'Tool that queries Prometheus metrics')]
#[AsTool('prometheus_query_range', 'Tool that queries Prometheus metrics over time range', method: 'queryRange')]
#[AsTool('prometheus_get_series', 'Tool that gets Prometheus series', method: 'getSeries')]
#[AsTool('prometheus_get_labels', 'Tool that gets Prometheus labels', method: 'getLabels')]
#[AsTool('prometheus_get_targets', 'Tool that gets Prometheus targets', method: 'getTargets')]
#[AsTool('prometheus_get_alerts', 'Tool that gets Prometheus alerts', method: 'getAlerts')]
final readonly class Prometheus
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $baseUrl,
        private string $apiVersion = 'v1',
        private array $options = [],
    ) {
    }

    /**
     * Query Prometheus metrics.
     *
     * @param string $query   PromQL query
     * @param string $time    Evaluation timestamp (Unix timestamp or RFC3339)
     * @param string $timeout Query timeout (e.g., 5s, 1m)
     *
     * @return array{
     *     status: string,
     *     data: array{
     *         resultType: string,
     *         result: array<int, array{
     *             metric: array<string, string>,
     *             value: array{0: int, 1: string}|null,
     *             values: array<int, array{0: int, 1: string}>|null,
     *         }>,
     *     },
     * }|string
     */
    public function __invoke(
        string $query,
        string $time = '',
        string $timeout = '5s',
    ): array|string {
        try {
            $params = [
                'query' => $query,
                'timeout' => $timeout,
            ];

            if ($time) {
                $params['time'] = $time;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/{$this->apiVersion}/query", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error querying Prometheus: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'status' => $data['status'],
                'data' => [
                    'resultType' => $data['data']['resultType'],
                    'result' => array_map(fn ($result) => [
                        'metric' => $result['metric'],
                        'value' => $result['value'] ?? null,
                        'values' => $result['values'] ?? null,
                    ], $data['data']['result'] ?? []),
                ],
            ];
        } catch (\Exception $e) {
            return 'Error querying Prometheus: '.$e->getMessage();
        }
    }

    /**
     * Query Prometheus metrics over time range.
     *
     * @param string $query   PromQL query
     * @param string $start   Start time (Unix timestamp or RFC3339)
     * @param string $end     End time (Unix timestamp or RFC3339)
     * @param string $step    Query resolution step width (e.g., 15s, 1m, 5m)
     * @param string $timeout Query timeout (e.g., 5s, 1m)
     *
     * @return array{
     *     status: string,
     *     data: array{
     *         resultType: string,
     *         result: array<int, array{
     *             metric: array<string, string>,
     *             values: array<int, array{0: int, 1: string}>,
     *         }>,
     *     },
     * }|string
     */
    public function queryRange(
        string $query,
        string $start,
        string $end,
        string $step,
        string $timeout = '5s',
    ): array|string {
        try {
            $params = [
                'query' => $query,
                'start' => $start,
                'end' => $end,
                'step' => $step,
                'timeout' => $timeout,
            ];

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/{$this->apiVersion}/query_range", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error querying Prometheus range: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'status' => $data['status'],
                'data' => [
                    'resultType' => $data['data']['resultType'],
                    'result' => array_map(fn ($result) => [
                        'metric' => $result['metric'],
                        'values' => $result['values'] ?? [],
                    ], $data['data']['result'] ?? []),
                ],
            ];
        } catch (\Exception $e) {
            return 'Error querying Prometheus range: '.$e->getMessage();
        }
    }

    /**
     * Get Prometheus series.
     *
     * @param string $match Series selector (e.g., up, {job="prometheus"})
     * @param string $start Start time (Unix timestamp or RFC3339)
     * @param string $end   End time (Unix timestamp or RFC3339)
     *
     * @return array{
     *     status: string,
     *     data: array<int, array<string, string>>,
     * }|string
     */
    public function getSeries(
        string $match,
        string $start = '',
        string $end = '',
    ): array|string {
        try {
            $params = [
                'match[]' => $match,
            ];

            if ($start) {
                $params['start'] = $start;
            }
            if ($end) {
                $params['end'] = $end;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/{$this->apiVersion}/series", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting Prometheus series: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'status' => $data['status'],
                'data' => array_map(fn ($series) => $series, $data['data'] ?? []),
            ];
        } catch (\Exception $e) {
            return 'Error getting Prometheus series: '.$e->getMessage();
        }
    }

    /**
     * Get Prometheus labels.
     *
     * @param string $match Series selector (optional)
     * @param string $start Start time (Unix timestamp or RFC3339)
     * @param string $end   End time (Unix timestamp or RFC3339)
     *
     * @return array{
     *     status: string,
     *     data: array<int, string>,
     * }|string
     */
    public function getLabels(
        string $match = '',
        string $start = '',
        string $end = '',
    ): array|string {
        try {
            $params = [];

            if ($match) {
                $params['match[]'] = $match;
            }
            if ($start) {
                $params['start'] = $start;
            }
            if ($end) {
                $params['end'] = $end;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/{$this->apiVersion}/labels", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting Prometheus labels: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'status' => $data['status'],
                'data' => $data['data'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error getting Prometheus labels: '.$e->getMessage();
        }
    }

    /**
     * Get Prometheus targets.
     *
     * @param string $state Target state filter (active, dropped)
     *
     * @return array{
     *     status: string,
     *     data: array{
     *         activeTargets: array<int, array{
     *             discoveredLabels: array<string, string>,
     *             labels: array<string, string>,
     *             scrapePool: string,
     *             scrapeUrl: string,
     *             globalUrl: string,
     *             lastError: string,
     *             lastScrape: string,
     *             lastScrapeDuration: float,
     *             health: string,
     *             scrapeInterval: string,
     *             scrapeTimeout: string,
     *         }>,
     *         droppedTargets: array<int, array{
     *             discoveredLabels: array<string, string>,
     *         }>,
     *     },
     * }|string
     */
    public function getTargets(string $state = ''): array|string
    {
        try {
            $params = [];

            if ($state) {
                $params['state'] = $state;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/{$this->apiVersion}/targets", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting Prometheus targets: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'status' => $data['status'],
                'data' => [
                    'activeTargets' => array_map(fn ($target) => [
                        'discoveredLabels' => $target['discoveredLabels'] ?? [],
                        'labels' => $target['labels'] ?? [],
                        'scrapePool' => $target['scrapePool'] ?? '',
                        'scrapeUrl' => $target['scrapeUrl'] ?? '',
                        'globalUrl' => $target['globalUrl'] ?? '',
                        'lastError' => $target['lastError'] ?? '',
                        'lastScrape' => $target['lastScrape'] ?? '',
                        'lastScrapeDuration' => $target['lastScrapeDuration'] ?? 0.0,
                        'health' => $target['health'] ?? 'unknown',
                        'scrapeInterval' => $target['scrapeInterval'] ?? '',
                        'scrapeTimeout' => $target['scrapeTimeout'] ?? '',
                    ], $data['data']['activeTargets'] ?? []),
                    'droppedTargets' => array_map(fn ($target) => [
                        'discoveredLabels' => $target['discoveredLabels'] ?? [],
                    ], $data['data']['droppedTargets'] ?? []),
                ],
            ];
        } catch (\Exception $e) {
            return 'Error getting Prometheus targets: '.$e->getMessage();
        }
    }

    /**
     * Get Prometheus alerts.
     *
     * @return array{
     *     status: string,
     *     data: array{
     *         alerts: array<int, array{
     *             labels: array<string, string>,
     *             annotations: array<string, string>,
     *             state: string,
     *             activeAt: string,
     *             value: string,
     *             partialFingerprint: string,
     *         }>,
     *     },
     * }|string
     */
    public function getAlerts(): array|string
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer '.$this->apiKey;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/{$this->apiVersion}/alerts", [
                'headers' => $headers,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting Prometheus alerts: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'status' => $data['status'],
                'data' => [
                    'alerts' => array_map(fn ($alert) => [
                        'labels' => $alert['labels'] ?? [],
                        'annotations' => $alert['annotations'] ?? [],
                        'state' => $alert['state'] ?? 'unknown',
                        'activeAt' => $alert['activeAt'] ?? '',
                        'value' => $alert['value'] ?? '',
                        'partialFingerprint' => $alert['partialFingerprint'] ?? '',
                    ], $data['data']['alerts'] ?? []),
                ],
            ];
        } catch (\Exception $e) {
            return 'Error getting Prometheus alerts: '.$e->getMessage();
        }
    }
}
