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
#[AsTool('newrelic_get_applications', 'Tool that gets New Relic applications')]
#[AsTool('newrelic_get_metrics', 'Tool that gets New Relic metrics', method: 'getMetrics')]
#[AsTool('newrelic_get_events', 'Tool that gets New Relic events', method: 'getEvents')]
#[AsTool('newrelic_get_alerts', 'Tool that gets New Relic alerts', method: 'getAlerts')]
#[AsTool('newrelic_get_deployments', 'Tool that gets New Relic deployments', method: 'getDeployments')]
#[AsTool('newrelic_get_errors', 'Tool that gets New Relic errors', method: 'getErrors')]
final readonly class NewRelic
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $apiVersion = 'v2',
        private array $options = [],
    ) {
    }

    /**
     * Get New Relic applications.
     *
     * @param string $name    Application name filter
     * @param int    $perPage Number of applications per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     language: string,
     *     health_status: string,
     *     reporting: bool,
     *     last_reported_at: string,
     *     application_summary: array{
     *         response_time: float,
     *         throughput: float,
     *         error_rate: float,
     *         apdex_score: float,
     *         host_count: int,
     *         instance_count: int,
     *     },
     *     links: array{
     *         application_instances: array<int, int>,
     *         alert_policy: int|null,
     *         server: array<int, int>,
     *     },
     *     settings: array{
     *         app_apdex_threshold: float,
     *         end_user_apdex_threshold: float,
     *         enable_real_user_monitoring: bool,
     *         use_server_side_config: bool,
     *     },
     * }>
     */
    public function __invoke(
        string $name = '',
        int $perPage = 50,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            if ($name) {
                $params['filter[name]'] = $name;
            }

            $response = $this->httpClient->request('GET', "https://api.newrelic.com/{$this->apiVersion}/applications.json", [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($app) => [
                'id' => $app['id'],
                'name' => $app['name'],
                'language' => $app['language'],
                'health_status' => $app['health_status'],
                'reporting' => $app['reporting'],
                'last_reported_at' => $app['last_reported_at'],
                'application_summary' => [
                    'response_time' => $app['application_summary']['response_time'],
                    'throughput' => $app['application_summary']['throughput'],
                    'error_rate' => $app['application_summary']['error_rate'],
                    'apdex_score' => $app['application_summary']['apdex_score'],
                    'host_count' => $app['application_summary']['host_count'],
                    'instance_count' => $app['application_summary']['instance_count'],
                ],
                'links' => [
                    'application_instances' => $app['links']['application_instances'] ?? [],
                    'alert_policy' => $app['links']['alert_policy'],
                    'server' => $app['links']['server'] ?? [],
                ],
                'settings' => [
                    'app_apdex_threshold' => $app['settings']['app_apdex_threshold'],
                    'end_user_apdex_threshold' => $app['settings']['end_user_apdex_threshold'],
                    'enable_real_user_monitoring' => $app['settings']['enable_real_user_monitoring'],
                    'use_server_side_config' => $app['settings']['use_server_side_config'],
                ],
            ], $data['applications'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get New Relic metrics.
     *
     * @param int    $applicationId Application ID
     * @param string $names         Comma-separated metric names
     * @param string $from          Start time (ISO 8601)
     * @param string $to            End time (ISO 8601)
     * @param string $values        Comma-separated values (call_count, response_time, etc.)
     *
     * @return array{
     *     metric_data: array{
     *         from: string,
     *         to: string,
     *         metrics_not_found: array<int, string>,
     *         metrics_found: array<int, string>,
     *         metrics: array<int, array{
     *             name: string,
     *             timeslices: array<int, array{
     *                 from: string,
     *                 to: string,
     *                 values: array<string, float>,
     *             }>,
     *         }>,
     *     },
     * }|string
     */
    public function getMetrics(
        int $applicationId,
        string $names,
        string $from,
        string $to,
        string $values = 'call_count,response_time,error_count',
    ): array|string {
        try {
            $params = [
                'names' => $names,
                'values' => $values,
                'from' => $from,
                'to' => $to,
                'summarize' => 'true',
            ];

            $response = $this->httpClient->request('GET', "https://api.newrelic.com/{$this->apiVersion}/applications/{$applicationId}/metrics/data.json", [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting metrics: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'metric_data' => [
                    'from' => $data['metric_data']['from'],
                    'to' => $data['metric_data']['to'],
                    'metrics_not_found' => $data['metric_data']['metrics_not_found'] ?? [],
                    'metrics_found' => $data['metric_data']['metrics_found'] ?? [],
                    'metrics' => array_map(fn ($metric) => [
                        'name' => $metric['name'],
                        'timeslices' => array_map(fn ($slice) => [
                            'from' => $slice['from'],
                            'to' => $slice['to'],
                            'values' => $slice['values'],
                        ], $metric['timeslices'] ?? []),
                    ], $data['metric_data']['metrics'] ?? []),
                ],
            ];
        } catch (\Exception $e) {
            return 'Error getting metrics: '.$e->getMessage();
        }
    }

    /**
     * Get New Relic events.
     *
     * @param string $query NRQL query
     * @param int    $limit Number of events to retrieve
     *
     * @return array{
     *     results: array<int, array<string, mixed>>,
     *     metadata: array{
     *         time_zone: string,
     *         raw_since: string,
     *         raw_until: string,
     *         raw_comparison_with: string,
     *         messages: array<int, string>,
     *         contents: array{
     *             function: string,
     *             limit: int,
     *             offset: int,
     *             order_by: string,
     *         },
     *     },
     * }|string
     */
    public function getEvents(
        string $query,
        int $limit = 100,
    ): array|string {
        try {
            $payload = [
                'query' => $query,
                'limit' => min(max($limit, 1), 1000),
            ];

            $response = $this->httpClient->request('POST', "https://api.newrelic.com/{$this->apiVersion}/graphql", [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => 'query($query: String!, $limit: Int!) {
                        actor {
                            nrql(query: $query, limit: $limit) {
                                results
                                metadata {
                                    timeZone
                                    rawSince
                                    rawUntil
                                    rawComparisonWith
                                    messages
                                    contents {
                                        function
                                        limit
                                        offset
                                        orderBy
                                    }
                                }
                            }
                        }
                    }',
                    'variables' => $payload,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error getting events: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $nrql = $data['data']['actor']['nrql'];

            return [
                'results' => $nrql['results'] ?? [],
                'metadata' => [
                    'time_zone' => $nrql['metadata']['timeZone'],
                    'raw_since' => $nrql['metadata']['rawSince'],
                    'raw_until' => $nrql['metadata']['rawUntil'],
                    'raw_comparison_with' => $nrql['metadata']['rawComparisonWith'],
                    'messages' => $nrql['metadata']['messages'] ?? [],
                    'contents' => [
                        'function' => $nrql['metadata']['contents']['function'],
                        'limit' => $nrql['metadata']['contents']['limit'],
                        'offset' => $nrql['metadata']['contents']['offset'],
                        'order_by' => $nrql['metadata']['contents']['orderBy'],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error getting events: '.$e->getMessage();
        }
    }

    /**
     * Get New Relic alerts.
     *
     * @param int    $perPage Number of alerts per page
     * @param int    $page    Page number
     * @param string $filter  Filter (all, open, closed, acknowledged)
     *
     * @return array<int, array{
     *     id: int,
     *     type: string,
     *     incident_id: int,
     *     title: string,
     *     body: string,
     *     alert_url: string,
     *     entity_type: string,
     *     entity_group_id: int|null,
     *     entity_guid: string,
     *     entity_name: string,
     *     priority: string,
     *     owner_user_id: int|null,
     *     notification_channel_ids: array<int, int>,
     *     runbook_url: string,
     *     created_at_epoch_millis: int,
     *     updated_at_epoch_millis: int,
     *     state: string,
     *     source: string,
     *     violation_id: int,
     *     policy_id: int,
     *     condition_name: string,
     *     condition_id: int,
     *     policy_name: string,
     *     policy_url: string,
     *     runbook_url: string,
     *     incident_url: string,
     *     entity_guid: string,
     *     entity_name: string,
     *     entity_type: string,
     *     entity_alert_severity: string,
     *     entity_alert_priority: string,
     * }>
     */
    public function getAlerts(
        int $perPage = 50,
        int $page = 1,
        string $filter = 'all',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'filter[state]' => $filter,
            ];

            $response = $this->httpClient->request('GET', "https://api.newrelic.com/{$this->apiVersion}/alerts_violations.json", [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($alert) => [
                'id' => $alert['id'],
                'type' => $alert['type'],
                'incident_id' => $alert['incident_id'],
                'title' => $alert['title'],
                'body' => $alert['body'],
                'alert_url' => $alert['alert_url'],
                'entity_type' => $alert['entity_type'],
                'entity_group_id' => $alert['entity_group_id'],
                'entity_guid' => $alert['entity_guid'],
                'entity_name' => $alert['entity_name'],
                'priority' => $alert['priority'] ?? 'normal',
                'owner_user_id' => $alert['owner_user_id'],
                'notification_channel_ids' => $alert['notification_channel_ids'] ?? [],
                'runbook_url' => $alert['runbook_url'] ?? '',
                'created_at_epoch_millis' => $alert['created_at_epoch_millis'],
                'updated_at_epoch_millis' => $alert['updated_at_epoch_millis'],
                'state' => $alert['state'],
                'source' => $alert['source'],
                'violation_id' => $alert['violation_id'],
                'policy_id' => $alert['policy_id'],
                'condition_name' => $alert['condition_name'],
                'condition_id' => $alert['condition_id'],
                'policy_name' => $alert['policy_name'],
                'policy_url' => $alert['policy_url'],
                'incident_url' => $alert['incident_url'] ?? '',
                'entity_alert_severity' => $alert['entity_alert_severity'] ?? '',
                'entity_alert_priority' => $alert['entity_alert_priority'] ?? '',
            ], $data['violations'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get New Relic deployments.
     *
     * @param int $applicationId Application ID
     * @param int $perPage       Number of deployments per page
     * @param int $page          Page number
     *
     * @return array<int, array{
     *     id: int,
     *     revision: string,
     *     changelog: string,
     *     description: string,
     *     user: string,
     *     timestamp: string,
     *     application_id: int,
     *     links: array{
     *         application: int,
     *     },
     * }>
     */
    public function getDeployments(
        int $applicationId,
        int $perPage = 50,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            $response = $this->httpClient->request('GET', "https://api.newrelic.com/{$this->apiVersion}/applications/{$applicationId}/deployments.json", [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($deployment) => [
                'id' => $deployment['id'],
                'revision' => $deployment['revision'],
                'changelog' => $deployment['changelog'],
                'description' => $deployment['description'],
                'user' => $deployment['user'],
                'timestamp' => $deployment['timestamp'],
                'application_id' => $deployment['application_id'],
                'links' => [
                    'application' => $deployment['links']['application'],
                ],
            ], $data['deployments'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get New Relic errors.
     *
     * @param int    $applicationId Application ID
     * @param string $from          Start time (ISO 8601)
     * @param string $to            End time (ISO 8601)
     * @param int    $perPage       Number of errors per page
     * @param int    $page          Page number
     * @param string $filter        Filter (all, browser, mobile)
     *
     * @return array<int, array{
     *     id: int,
     *     timestamp: int,
     *     error_class: string,
     *     message: string,
     *     count: int,
     *     user_agent: string,
     *     host: string,
     *     request_uri: string,
     *     request_method: string,
     *     port: int,
     *     path: string,
     *     stack_trace: array<int, string>,
     *     application_id: int,
     *     links: array{
     *         application: int,
     *     },
     * }>
     */
    public function getErrors(
        int $applicationId,
        string $from,
        string $to,
        int $perPage = 50,
        int $page = 1,
        string $filter = 'all',
    ): array {
        try {
            $params = [
                'from' => $from,
                'to' => $to,
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'filter[name]' => $filter,
            ];

            $response = $this->httpClient->request('GET', "https://api.newrelic.com/{$this->apiVersion}/applications/{$applicationId}/errors.json", [
                'headers' => [
                    'X-Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($error) => [
                'id' => $error['id'],
                'timestamp' => $error['timestamp'],
                'error_class' => $error['error_class'],
                'message' => $error['message'],
                'count' => $error['count'],
                'user_agent' => $error['user_agent'] ?? '',
                'host' => $error['host'] ?? '',
                'request_uri' => $error['request_uri'] ?? '',
                'request_method' => $error['request_method'] ?? '',
                'port' => $error['port'] ?? 0,
                'path' => $error['path'] ?? '',
                'stack_trace' => $error['stack_trace'] ?? [],
                'application_id' => $error['application_id'],
                'links' => [
                    'application' => $error['links']['application'],
                ],
            ], $data['errors'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }
}
