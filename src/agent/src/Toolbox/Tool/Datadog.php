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
#[AsTool('datadog_get_dashboards', 'Tool that gets Datadog dashboards')]
#[AsTool('datadog_get_metrics', 'Tool that gets Datadog metrics', method: 'getMetrics')]
#[AsTool('datadog_get_logs', 'Tool that gets Datadog logs', method: 'getLogs')]
#[AsTool('datadog_get_alerts', 'Tool that gets Datadog alerts', method: 'getAlerts')]
#[AsTool('datadog_get_monitors', 'Tool that gets Datadog monitors', method: 'getMonitors')]
#[AsTool('datadog_get_events', 'Tool that gets Datadog events', method: 'getEvents')]
final readonly class Datadog
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        #[\SensitiveParameter] private string $applicationKey,
        private string $site = 'datadoghq.com',
        private array $options = [],
    ) {
    }

    /**
     * Get Datadog dashboards.
     *
     * @param string $query     Search query
     * @param int    $perPage   Number of dashboards per page
     * @param int    $page      Page number
     * @param string $order     Order by field (created, modified, name)
     * @param string $direction Order direction (asc, desc)
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     author_handle: string,
     *     author_name: string,
     *     url: string,
     *     is_read_only: bool,
     *     is_favorite: bool,
     *     is_shared: bool,
     *     tags: array<int, string>,
     *     created_at: string,
     *     modified_at: string,
     *     layout_type: string,
     *     widgets: array<int, array{
     *         id: int,
     *         definition: array<string, mixed>,
     *         layout: array{x: int, y: int, width: int, height: int},
     *     }>,
     *     notify_list: array<int, string>,
     *     template_variables: array<int, array{
     *         name: string,
     *         prefix: string,
     *         available_values: array<int, string>,
     *         default: string,
     *     }>,
     * }>
     */
    public function __invoke(
        string $query = '',
        int $perPage = 50,
        int $page = 0,
        string $order = 'created',
        string $direction = 'desc',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 0),
                'order' => $order,
                'direction' => $direction,
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "https://api.{$this->site}/api/v1/dashboard", [
                'headers' => [
                    'DD-API-KEY' => $this->apiKey,
                    'DD-APPLICATION-KEY' => $this->applicationKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return [];
            }

            return array_map(fn ($dashboard) => [
                'id' => $dashboard['id'],
                'title' => $dashboard['title'],
                'description' => $dashboard['description'],
                'author_handle' => $dashboard['author_handle'],
                'author_name' => $dashboard['author_name'],
                'url' => $dashboard['url'],
                'is_read_only' => $dashboard['is_read_only'] ?? false,
                'is_favorite' => $dashboard['is_favorite'] ?? false,
                'is_shared' => $dashboard['is_shared'] ?? false,
                'tags' => $dashboard['tags'] ?? [],
                'created_at' => $dashboard['created_at'],
                'modified_at' => $dashboard['modified_at'],
                'layout_type' => $dashboard['layout_type'],
                'widgets' => array_map(fn ($widget) => [
                    'id' => $widget['id'],
                    'definition' => $widget['definition'],
                    'layout' => [
                        'x' => $widget['layout']['x'],
                        'y' => $widget['layout']['y'],
                        'width' => $widget['layout']['width'],
                        'height' => $widget['layout']['height'],
                    ],
                ], $dashboard['widgets'] ?? []),
                'notify_list' => $dashboard['notify_list'] ?? [],
                'template_variables' => array_map(fn ($variable) => [
                    'name' => $variable['name'],
                    'prefix' => $variable['prefix'],
                    'available_values' => $variable['available_values'] ?? [],
                    'default' => $variable['default'] ?? '',
                ], $dashboard['template_variables'] ?? []),
            ], $data['dashboards'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Datadog metrics.
     *
     * @param string $from        Start time (ISO 8601 or Unix timestamp)
     * @param string $to          End time (ISO 8601 or Unix timestamp)
     * @param string $query       Metric query
     * @param string $aggregation Aggregation method (avg, sum, min, max, count)
     * @param string $interval    Time interval (1m, 5m, 10m, 1h, etc.)
     *
     * @return array{
     *     series: array<int, array{
     *         metric: string,
     *         display_name: string,
     *         unit: string,
     *         pointlist: array<int, array{0: int, 1: float}>,
     *         scope: string,
     *         tag_set: array<int, string>,
     *     }>,
     *     from_date: int,
     *     to_date: int,
     *     query: string,
     *     message: string,
     *     res_type: string,
     *     resp_version: int,
     *     status: string,
     * }|string
     */
    public function getMetrics(
        string $from,
        string $to,
        string $query,
        string $aggregation = 'avg',
        string $interval = '1m',
    ): array|string {
        try {
            $params = [
                'from' => $from,
                'to' => $to,
                'query' => $query,
            ];

            $response = $this->httpClient->request('GET', "https://api.{$this->site}/api/v1/query", [
                'headers' => [
                    'DD-API-KEY' => $this->apiKey,
                    'DD-APPLICATION-KEY' => $this->applicationKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting metrics: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'series' => array_map(fn ($series) => [
                    'metric' => $series['metric'],
                    'display_name' => $series['display_name'],
                    'unit' => $series['unit'] ?? '',
                    'pointlist' => $series['pointlist'] ?? [],
                    'scope' => $series['scope'] ?? '',
                    'tag_set' => $series['tag_set'] ?? [],
                ], $data['series'] ?? []),
                'from_date' => $data['from_date'],
                'to_date' => $data['to_date'],
                'query' => $data['query'],
                'message' => $data['message'] ?? '',
                'res_type' => $data['res_type'],
                'resp_version' => $data['resp_version'],
                'status' => $data['status'],
            ];
        } catch (\Exception $e) {
            return 'Error getting metrics: '.$e->getMessage();
        }
    }

    /**
     * Get Datadog logs.
     *
     * @param string $query Log search query
     * @param string $from  Start time (ISO 8601 or Unix timestamp)
     * @param string $to    End time (ISO 8601 or Unix timestamp)
     * @param int    $limit Number of logs to retrieve
     * @param string $sort  Sort order (timestamp, -timestamp)
     * @param string $index Log index name
     *
     * @return array{
     *     logs: array<int, array{
     *         id: string,
     *         content: array{
     *             message: string,
     *             timestamp: string,
     *             host: string,
     *             service: string,
     *             source: string,
     *             status: string,
     *             tags: array<int, string>,
     *             attributes: array<string, mixed>,
     *         },
     *         date: string,
     *         host: string,
     *         source: string,
     *         service: string,
     *         status: string,
     *         tags: array<int, string>,
     *         message: string,
     *         attributes: array<string, mixed>,
     *     }>,
     *     nextLogId: string|null,
     *     status: string,
     * }|string
     */
    public function getLogs(
        string $query,
        string $from,
        string $to,
        int $limit = 1000,
        string $sort = '-timestamp',
        string $index = 'main',
    ): array|string {
        try {
            $payload = [
                'query' => $query,
                'from' => $from,
                'to' => $to,
                'limit' => min(max($limit, 1), 1000),
                'sort' => $sort,
                'index' => $index,
            ];

            $response = $this->httpClient->request('POST', "https://api.{$this->site}/api/v1/logs-queries/list", [
                'headers' => [
                    'DD-API-KEY' => $this->apiKey,
                    'DD-APPLICATION-KEY' => $this->applicationKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting logs: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'logs' => array_map(fn ($log) => [
                    'id' => $log['id'],
                    'content' => [
                        'message' => $log['content']['message'] ?? '',
                        'timestamp' => $log['content']['timestamp'] ?? '',
                        'host' => $log['content']['host'] ?? '',
                        'service' => $log['content']['service'] ?? '',
                        'source' => $log['content']['source'] ?? '',
                        'status' => $log['content']['status'] ?? '',
                        'tags' => $log['content']['tags'] ?? [],
                        'attributes' => $log['content']['attributes'] ?? [],
                    ],
                    'date' => $log['date'],
                    'host' => $log['host'],
                    'source' => $log['source'],
                    'service' => $log['service'],
                    'status' => $log['status'],
                    'tags' => $log['tags'] ?? [],
                    'message' => $log['message'],
                    'attributes' => $log['attributes'] ?? [],
                ], $data['logs'] ?? []),
                'nextLogId' => $data['nextLogId'] ?? null,
                'status' => $data['status'],
            ];
        } catch (\Exception $e) {
            return 'Error getting logs: '.$e->getMessage();
        }
    }

    /**
     * Get Datadog alerts.
     *
     * @param int    $perPage   Number of alerts per page
     * @param int    $page      Page number
     * @param string $query     Search query
     * @param string $order     Order by field (created, modified, name)
     * @param string $direction Order direction (asc, desc)
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     message: string,
     *     query: string,
     *     type: string,
     *     priority: string,
     *     tags: array<int, string>,
     *     options: array<string, mixed>,
     *     state: array{
     *         groups: array<string, mixed>,
     *         template_variables: array<string, mixed>,
     *         name: string,
     *         message: string,
     *         query: string,
     *         type: string,
     *         priority: string,
     *         tags: array<int, string>,
     *         options: array<string, mixed>,
     *         restricted_roles: array<int, string>,
     *         notify_audit: bool,
     *         no_data_timeframe: int|null,
     *         new_host_delay: int,
     *         new_group_delay: int,
     *         require_full_window: bool,
     *         notify_no_data: bool,
     *         renotify_interval: int|null,
     *         escalation_message: string|null,
     *         evaluation_delay: int,
     *         locked: bool,
     *         include_tags: bool,
     *         threshold_windows: array<string, mixed>,
     *         thresholds: array<string, mixed>,
     *         created_at: int,
     *         created_by: array{id: int, handle: string, email: string},
     *         modified_at: int,
     *         modified_by: array{id: int, handle: string, email: string},
     *         overall_state_modified: string,
     *         overall_state: string,
     *         org_id: int,
     *         restricted_roles: array<int, string>,
     *     },
     *     created: string,
     *     modified: string,
     *     restricted_roles: array<int, string>,
     *     deleted: string|null,
     *     creator: array{id: int, handle: string, email: string, name: string},
     *     multi: bool,
     * }>
     */
    public function getAlerts(
        int $perPage = 50,
        int $page = 0,
        string $query = '',
        string $order = 'created',
        string $direction = 'desc',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 0),
                'order' => $order,
                'direction' => $direction,
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "https://api.{$this->site}/api/v1/monitor", [
                'headers' => [
                    'DD-API-KEY' => $this->apiKey,
                    'DD-APPLICATION-KEY' => $this->applicationKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return [];
            }

            return array_map(fn ($alert) => [
                'id' => $alert['id'],
                'name' => $alert['name'],
                'message' => $alert['message'],
                'query' => $alert['query'],
                'type' => $alert['type'],
                'priority' => $alert['priority'] ?? 'normal',
                'tags' => $alert['tags'] ?? [],
                'options' => $alert['options'] ?? [],
                'state' => [
                    'groups' => $alert['state']['groups'] ?? [],
                    'template_variables' => $alert['state']['template_variables'] ?? [],
                    'name' => $alert['state']['name'],
                    'message' => $alert['state']['message'],
                    'query' => $alert['state']['query'],
                    'type' => $alert['state']['type'],
                    'priority' => $alert['state']['priority'] ?? 'normal',
                    'tags' => $alert['state']['tags'] ?? [],
                    'options' => $alert['state']['options'] ?? [],
                    'restricted_roles' => $alert['state']['restricted_roles'] ?? [],
                    'notify_audit' => $alert['state']['notify_audit'] ?? false,
                    'no_data_timeframe' => $alert['state']['no_data_timeframe'],
                    'new_host_delay' => $alert['state']['new_host_delay'] ?? 300,
                    'new_group_delay' => $alert['state']['new_group_delay'] ?? 300,
                    'require_full_window' => $alert['state']['require_full_window'] ?? false,
                    'notify_no_data' => $alert['state']['notify_no_data'] ?? false,
                    'renotify_interval' => $alert['state']['renotify_interval'],
                    'escalation_message' => $alert['state']['escalation_message'],
                    'evaluation_delay' => $alert['state']['evaluation_delay'] ?? 0,
                    'locked' => $alert['state']['locked'] ?? false,
                    'include_tags' => $alert['state']['include_tags'] ?? true,
                    'threshold_windows' => $alert['state']['threshold_windows'] ?? [],
                    'thresholds' => $alert['state']['thresholds'] ?? [],
                    'created_at' => $alert['state']['created_at'],
                    'created_by' => [
                        'id' => $alert['state']['created_by']['id'],
                        'handle' => $alert['state']['created_by']['handle'],
                        'email' => $alert['state']['created_by']['email'],
                    ],
                    'modified_at' => $alert['state']['modified_at'],
                    'modified_by' => [
                        'id' => $alert['state']['modified_by']['id'],
                        'handle' => $alert['state']['modified_by']['handle'],
                        'email' => $alert['state']['modified_by']['email'],
                    ],
                    'overall_state_modified' => $alert['state']['overall_state_modified'],
                    'overall_state' => $alert['state']['overall_state'],
                    'org_id' => $alert['state']['org_id'],
                ],
                'created' => $alert['created'],
                'modified' => $alert['modified'],
                'restricted_roles' => $alert['restricted_roles'] ?? [],
                'deleted' => $alert['deleted'],
                'creator' => [
                    'id' => $alert['creator']['id'],
                    'handle' => $alert['creator']['handle'],
                    'email' => $alert['creator']['email'],
                    'name' => $alert['creator']['name'],
                ],
                'multi' => $alert['multi'] ?? false,
            ], $data['monitors'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Datadog monitors.
     *
     * @param int    $perPage   Number of monitors per page
     * @param int    $page      Page number
     * @param string $query     Search query
     * @param string $order     Order by field (created, modified, name)
     * @param string $direction Order direction (asc, desc)
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     message: string,
     *     query: string,
     *     type: string,
     *     priority: string,
     *     tags: array<int, string>,
     *     options: array<string, mixed>,
     *     state: array{
     *         groups: array<string, mixed>,
     *         template_variables: array<string, mixed>,
     *         name: string,
     *         message: string,
     *         query: string,
     *         type: string,
     *         priority: string,
     *         tags: array<int, string>,
     *         options: array<string, mixed>,
     *         restricted_roles: array<int, string>,
     *         notify_audit: bool,
     *         no_data_timeframe: int|null,
     *         new_host_delay: int,
     *         new_group_delay: int,
     *         require_full_window: bool,
     *         notify_no_data: bool,
     *         renotify_interval: int|null,
     *         escalation_message: string|null,
     *         evaluation_delay: int,
     *         locked: bool,
     *         include_tags: bool,
     *         threshold_windows: array<string, mixed>,
     *         thresholds: array<string, mixed>,
     *         created_at: int,
     *         created_by: array{id: int, handle: string, email: string},
     *         modified_at: int,
     *         modified_by: array{id: int, handle: string, email: string},
     *         overall_state_modified: string,
     *         overall_state: string,
     *         org_id: int,
     *     },
     *     created: string,
     *     modified: string,
     *     restricted_roles: array<int, string>,
     *     deleted: string|null,
     *     creator: array{id: int, handle: string, email: string, name: string},
     *     multi: bool,
     * }>
     */
    public function getMonitors(
        int $perPage = 50,
        int $page = 0,
        string $query = '',
        string $order = 'created',
        string $direction = 'desc',
    ): array {
        // This method is essentially the same as getAlerts since Datadog uses monitors for alerts
        return $this->getAlerts($perPage, $page, $query, $order, $direction);
    }

    /**
     * Get Datadog events.
     *
     * @param string $from        Start time (ISO 8601 or Unix timestamp)
     * @param string $to          End time (ISO 8601 or Unix timestamp)
     * @param string $query       Event search query
     * @param int    $limit       Number of events to retrieve
     * @param string $sort        Sort order (timestamp, -timestamp, priority, -priority)
     * @param string $aggregation Aggregation method (count, cardinality, pc75, pc90, pc95, pc98, pc99, sum, min, max, avg)
     *
     * @return array{
     *     events: array<int, array{
     *         id: int,
     *         title: string,
     *         text: string,
     *         date_happened: int,
     *         priority: string,
     *         source: string,
     *         tags: array<int, string>,
     *         alert_type: string,
     *         aggregation_key: string,
     *         handle: string,
     *         url: string,
     *         is_aggregate: bool,
     *         can_delete: bool,
     *         can_edit: bool,
     *         device_name: string,
     *         related_event_id: int|null,
     *         host: string,
     *         resource: string,
     *         event_type: string,
     *         children: array<string, mixed>,
     *         comments: array<int, array{
     *             id: int,
     *             message: string,
     *             handle: string,
     *             created: string,
     *         }>,
     *     }>,
     *     status: string,
     * }|string
     */
    public function getEvents(
        string $from,
        string $to,
        string $query = '',
        int $limit = 100,
        string $sort = '-timestamp',
        string $aggregation = 'count',
    ): array|string {
        try {
            $params = [
                'from' => $from,
                'to' => $to,
                'limit' => min(max($limit, 1), 1000),
                'sort' => $sort,
                'aggregation' => $aggregation,
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "https://api.{$this->site}/api/v1/events", [
                'headers' => [
                    'DD-API-KEY' => $this->apiKey,
                    'DD-APPLICATION-KEY' => $this->applicationKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting events: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'events' => array_map(fn ($event) => [
                    'id' => $event['id'],
                    'title' => $event['title'],
                    'text' => $event['text'],
                    'date_happened' => $event['date_happened'],
                    'priority' => $event['priority'] ?? 'normal',
                    'source' => $event['source'],
                    'tags' => $event['tags'] ?? [],
                    'alert_type' => $event['alert_type'] ?? 'info',
                    'aggregation_key' => $event['aggregation_key'] ?? '',
                    'handle' => $event['handle'] ?? '',
                    'url' => $event['url'] ?? '',
                    'is_aggregate' => $event['is_aggregate'] ?? false,
                    'can_delete' => $event['can_delete'] ?? false,
                    'can_edit' => $event['can_edit'] ?? false,
                    'device_name' => $event['device_name'] ?? '',
                    'related_event_id' => $event['related_event_id'],
                    'host' => $event['host'] ?? '',
                    'resource' => $event['resource'] ?? '',
                    'event_type' => $event['event_type'] ?? '',
                    'children' => $event['children'] ?? [],
                    'comments' => array_map(fn ($comment) => [
                        'id' => $comment['id'],
                        'message' => $comment['message'],
                        'handle' => $comment['handle'],
                        'created' => $comment['created'],
                    ], $event['comments'] ?? []),
                ], $data['events'] ?? []),
                'status' => $data['status'] ?? 'ok',
            ];
        } catch (\Exception $e) {
            return 'Error getting events: '.$e->getMessage();
        }
    }
}
