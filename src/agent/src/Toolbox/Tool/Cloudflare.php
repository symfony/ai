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
#[AsTool('cloudflare_get_zones', 'Tool that gets Cloudflare zones')]
#[AsTool('cloudflare_get_dns_records', 'Tool that gets Cloudflare DNS records', method: 'getDnsRecords')]
#[AsTool('cloudflare_create_dns_record', 'Tool that creates Cloudflare DNS records', method: 'createDnsRecord')]
#[AsTool('cloudflare_get_analytics', 'Tool that gets Cloudflare analytics', method: 'getAnalytics')]
#[AsTool('cloudflare_get_firewall_rules', 'Tool that gets Cloudflare firewall rules', method: 'getFirewallRules')]
#[AsTool('cloudflare_get_ssl_settings', 'Tool that gets Cloudflare SSL settings', method: 'getSslSettings')]
final readonly class Cloudflare
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiToken,
        private string $apiVersion = 'v4',
        private array $options = [],
    ) {
    }

    /**
     * Get Cloudflare zones.
     *
     * @param string $name      Zone name filter
     * @param string $status    Zone status filter (active, pending, initializing, moved, deleted, deactivated)
     * @param int    $perPage   Number of zones per page
     * @param int    $page      Page number
     * @param string $order     Order by field (name, status, account)
     * @param string $direction Order direction (asc, desc)
     * @param string $match     Match type (all, any)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     status: string,
     *     paused: bool,
     *     type: string,
     *     development_mode: int,
     *     name_servers: array<int, string>,
     *     original_name_servers: array<int, string>,
     *     original_registrar: string,
     *     original_dnshost: string,
     *     modified_on: string,
     *     created_on: string,
     *     activated_on: string,
     *     meta: array{
     *         step: int,
     *         custom_certificate_quota: int,
     *         page_rule_quota: int,
     *         phishing_detected: bool,
     *         multiple_railguns_allowed: bool,
     *     },
     *     owner: array{
     *         id: string,
     *         type: string,
     *         email: string,
     *     },
     *     account: array{
     *         id: string,
     *         name: string,
     *     },
     *     permissions: array<int, string>,
     *     plan: array{
     *         id: string,
     *         name: string,
     *         price: int,
     *         currency: string,
     *         frequency: string,
     *         is_subscribed: bool,
     *         can_subscribe: bool,
     *         legacy_id: string,
     *         legacy_discount: bool,
     *         externally_managed: bool,
     *     },
     * }>
     */
    public function __invoke(
        string $name = '',
        string $status = '',
        int $perPage = 50,
        int $page = 1,
        string $order = 'name',
        string $direction = 'asc',
        string $match = 'all',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'order' => $order,
                'direction' => $direction,
                'match' => $match,
            ];

            if ($name) {
                $params['name'] = $name;
            }
            if ($status) {
                $params['status'] = $status;
            }

            $response = $this->httpClient->request('GET', "https://api.cloudflare.com/client/{$this->apiVersion}/zones", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['success']) && !$data['success']) {
                return [];
            }

            return array_map(fn ($zone) => [
                'id' => $zone['id'],
                'name' => $zone['name'],
                'status' => $zone['status'],
                'paused' => $zone['paused'],
                'type' => $zone['type'],
                'development_mode' => $zone['development_mode'],
                'name_servers' => $zone['name_servers'],
                'original_name_servers' => $zone['original_name_servers'],
                'original_registrar' => $zone['original_registrar'],
                'original_dnshost' => $zone['original_dnshost'],
                'modified_on' => $zone['modified_on'],
                'created_on' => $zone['created_on'],
                'activated_on' => $zone['activated_on'],
                'meta' => [
                    'step' => $zone['meta']['step'],
                    'custom_certificate_quota' => $zone['meta']['custom_certificate_quota'],
                    'page_rule_quota' => $zone['meta']['page_rule_quota'],
                    'phishing_detected' => $zone['meta']['phishing_detected'],
                    'multiple_railguns_allowed' => $zone['meta']['multiple_railguns_allowed'],
                ],
                'owner' => [
                    'id' => $zone['owner']['id'],
                    'type' => $zone['owner']['type'],
                    'email' => $zone['owner']['email'],
                ],
                'account' => [
                    'id' => $zone['account']['id'],
                    'name' => $zone['account']['name'],
                ],
                'permissions' => $zone['permissions'],
                'plan' => [
                    'id' => $zone['plan']['id'],
                    'name' => $zone['plan']['name'],
                    'price' => $zone['plan']['price'],
                    'currency' => $zone['plan']['currency'],
                    'frequency' => $zone['plan']['frequency'],
                    'is_subscribed' => $zone['plan']['is_subscribed'],
                    'can_subscribe' => $zone['plan']['can_subscribe'],
                    'legacy_id' => $zone['plan']['legacy_id'],
                    'legacy_discount' => $zone['plan']['legacy_discount'],
                    'externally_managed' => $zone['plan']['externally_managed'],
                ],
            ], $data['result'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Cloudflare DNS records.
     *
     * @param string $zoneId    Zone ID
     * @param string $type      Record type (A, AAAA, CNAME, MX, TXT, etc.)
     * @param string $name      Record name
     * @param string $content   Record content
     * @param int    $perPage   Number of records per page
     * @param int    $page      Page number
     * @param string $order     Order by field (type, name, content, ttl, proxied)
     * @param string $direction Order direction (asc, desc)
     * @param string $match     Match type (all, any)
     *
     * @return array<int, array{
     *     id: string,
     *     zone_id: string,
     *     zone_name: string,
     *     name: string,
     *     type: string,
     *     content: string,
     *     proxiable: bool,
     *     proxied: bool,
     *     ttl: int,
     *     locked: bool,
     *     meta: array{
     *         auto_added: bool,
     *         managed_by_apps: bool,
     *         managed_by_argo_tunnel: bool,
     *         source: string,
     *     },
     *     comment: string,
     *     tags: array<int, string>,
     *     created_on: string,
     *     modified_on: string,
     *     priority: int|null,
     * }>
     */
    public function getDnsRecords(
        string $zoneId,
        string $type = '',
        string $name = '',
        string $content = '',
        int $perPage = 50,
        int $page = 1,
        string $order = 'type',
        string $direction = 'asc',
        string $match = 'all',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'order' => $order,
                'direction' => $direction,
                'match' => $match,
            ];

            if ($type) {
                $params['type'] = $type;
            }
            if ($name) {
                $params['name'] = $name;
            }
            if ($content) {
                $params['content'] = $content;
            }

            $response = $this->httpClient->request('GET', "https://api.cloudflare.com/client/{$this->apiVersion}/zones/{$zoneId}/dns_records", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['success']) && !$data['success']) {
                return [];
            }

            return array_map(fn ($record) => [
                'id' => $record['id'],
                'zone_id' => $record['zone_id'],
                'zone_name' => $record['zone_name'],
                'name' => $record['name'],
                'type' => $record['type'],
                'content' => $record['content'],
                'proxiable' => $record['proxiable'],
                'proxied' => $record['proxied'] ?? false,
                'ttl' => $record['ttl'],
                'locked' => $record['locked'],
                'meta' => [
                    'auto_added' => $record['meta']['auto_added'],
                    'managed_by_apps' => $record['meta']['managed_by_apps'],
                    'managed_by_argo_tunnel' => $record['meta']['managed_by_argo_tunnel'],
                    'source' => $record['meta']['source'],
                ],
                'comment' => $record['comment'] ?? '',
                'tags' => $record['tags'] ?? [],
                'created_on' => $record['created_on'],
                'modified_on' => $record['modified_on'],
                'priority' => $record['priority'],
            ], $data['result'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Cloudflare DNS record.
     *
     * @param string $zoneId   Zone ID
     * @param string $type     Record type (A, AAAA, CNAME, MX, TXT, etc.)
     * @param string $name     Record name
     * @param string $content  Record content
     * @param int    $ttl      TTL value (1 for auto, 120-86400 for manual)
     * @param bool   $proxied  Whether record is proxied through Cloudflare
     * @param int    $priority Priority (for MX records)
     * @param string $comment  Record comment
     *
     * @return array{
     *     id: string,
     *     zone_id: string,
     *     zone_name: string,
     *     name: string,
     *     type: string,
     *     content: string,
     *     proxiable: bool,
     *     proxied: bool,
     *     ttl: int,
     *     locked: bool,
     *     meta: array{
     *         auto_added: bool,
     *         managed_by_apps: bool,
     *         managed_by_argo_tunnel: bool,
     *         source: string,
     *     },
     *     comment: string,
     *     tags: array<int, string>,
     *     created_on: string,
     *     modified_on: string,
     *     priority: int|null,
     * }|string
     */
    public function createDnsRecord(
        string $zoneId,
        string $type,
        string $name,
        string $content,
        int $ttl = 1,
        bool $proxied = false,
        int $priority = 0,
        string $comment = '',
    ): array|string {
        try {
            $payload = [
                'type' => $type,
                'name' => $name,
                'content' => $content,
                'ttl' => $ttl,
                'proxied' => $proxied,
            ];

            if ($priority > 0) {
                $payload['priority'] = $priority;
            }
            if ($comment) {
                $payload['comment'] = $comment;
            }

            $response = $this->httpClient->request('POST', "https://api.cloudflare.com/client/{$this->apiVersion}/zones/{$zoneId}/dns_records", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['success']) && !$data['success']) {
                return 'Error creating DNS record: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $record = $data['result'];

            return [
                'id' => $record['id'],
                'zone_id' => $record['zone_id'],
                'zone_name' => $record['zone_name'],
                'name' => $record['name'],
                'type' => $record['type'],
                'content' => $record['content'],
                'proxiable' => $record['proxiable'],
                'proxied' => $record['proxied'] ?? false,
                'ttl' => $record['ttl'],
                'locked' => $record['locked'],
                'meta' => [
                    'auto_added' => $record['meta']['auto_added'],
                    'managed_by_apps' => $record['meta']['managed_by_apps'],
                    'managed_by_argo_tunnel' => $record['meta']['managed_by_argo_tunnel'],
                    'source' => $record['meta']['source'],
                ],
                'comment' => $record['comment'] ?? '',
                'tags' => $record['tags'] ?? [],
                'created_on' => $record['created_on'],
                'modified_on' => $record['modified_on'],
                'priority' => $record['priority'],
            ];
        } catch (\Exception $e) {
            return 'Error creating DNS record: '.$e->getMessage();
        }
    }

    /**
     * Get Cloudflare analytics.
     *
     * @param string $zoneId     Zone ID
     * @param string $since      Start date (ISO 8601)
     * @param string $until      End date (ISO 8601)
     * @param string $continuous Continuous data (true/false)
     *
     * @return array{
     *     totals: array{
     *         requests: array{
     *             all: int,
     *             cached: int,
     *             uncached: int,
     *         },
     *         bandwidth: array{
     *             all: int,
     *             cached: int,
     *             uncached: int,
     *         },
     *         threats: array{
     *             all: int,
     *             type: array<string, int>,
     *         },
     *         pageviews: array{
     *             all: int,
     *         },
     *         uniques: array{
     *             all: int,
     *         },
     *     },
     *     timeseries: array<int, array{
     *         since: string,
     *         until: string,
     *         requests: array{
     *             all: int,
     *             cached: int,
     *             uncached: int,
     *         },
     *         bandwidth: array{
     *             all: int,
     *             cached: int,
     *             uncached: int,
     *         },
     *         threats: array{
     *             all: int,
     *         },
     *         pageviews: array{
     *             all: int,
     *         },
     *         uniques: array{
     *             all: int,
     *         },
     *     }>,
     * }|string
     */
    public function getAnalytics(
        string $zoneId,
        string $since,
        string $until,
        string $continuous = 'false',
    ): array|string {
        try {
            $params = [
                'since' => $since,
                'until' => $until,
                'continuous' => $continuous,
            ];

            $response = $this->httpClient->request('GET', "https://api.cloudflare.com/client/{$this->apiVersion}/zones/{$zoneId}/analytics/dashboard", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['success']) && !$data['success']) {
                return 'Error getting analytics: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $result = $data['result'];

            return [
                'totals' => [
                    'requests' => [
                        'all' => $result['totals']['requests']['all'],
                        'cached' => $result['totals']['requests']['cached'],
                        'uncached' => $result['totals']['requests']['uncached'],
                    ],
                    'bandwidth' => [
                        'all' => $result['totals']['bandwidth']['all'],
                        'cached' => $result['totals']['bandwidth']['cached'],
                        'uncached' => $result['totals']['bandwidth']['uncached'],
                    ],
                    'threats' => [
                        'all' => $result['totals']['threats']['all'],
                        'type' => $result['totals']['threats']['type'] ?? [],
                    ],
                    'pageviews' => [
                        'all' => $result['totals']['pageviews']['all'],
                    ],
                    'uniques' => [
                        'all' => $result['totals']['uniques']['all'],
                    ],
                ],
                'timeseries' => array_map(fn ($point) => [
                    'since' => $point['since'],
                    'until' => $point['until'],
                    'requests' => [
                        'all' => $point['requests']['all'],
                        'cached' => $point['requests']['cached'],
                        'uncached' => $point['requests']['uncached'],
                    ],
                    'bandwidth' => [
                        'all' => $point['bandwidth']['all'],
                        'cached' => $point['bandwidth']['cached'],
                        'uncached' => $point['bandwidth']['uncached'],
                    ],
                    'threats' => [
                        'all' => $point['threats']['all'],
                    ],
                    'pageviews' => [
                        'all' => $point['pageviews']['all'],
                    ],
                    'uniques' => [
                        'all' => $point['uniques']['all'],
                    ],
                ], $result['timeseries'] ?? []),
            ];
        } catch (\Exception $e) {
            return 'Error getting analytics: '.$e->getMessage();
        }
    }

    /**
     * Get Cloudflare firewall rules.
     *
     * @param string $zoneId    Zone ID
     * @param int    $perPage   Number of rules per page
     * @param int    $page      Page number
     * @param string $order     Order by field (priority, description, action)
     * @param string $direction Order direction (asc, desc)
     *
     * @return array<int, array{
     *     id: string,
     *     paused: bool,
     *     description: string,
     *     action: string,
     *     priority: int,
     *     filter: array{
     *         id: string,
     *         paused: bool,
     *         description: string,
     *         expression: string,
     *         ref: string,
     *     },
     *     created_on: string,
     *     modified_on: string,
     * }>
     */
    public function getFirewallRules(
        string $zoneId,
        int $perPage = 50,
        int $page = 1,
        string $order = 'priority',
        string $direction = 'desc',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'order' => $order,
                'direction' => $direction,
            ];

            $response = $this->httpClient->request('GET', "https://api.cloudflare.com/client/{$this->apiVersion}/zones/{$zoneId}/firewall/rules", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['success']) && !$data['success']) {
                return [];
            }

            return array_map(fn ($rule) => [
                'id' => $rule['id'],
                'paused' => $rule['paused'],
                'description' => $rule['description'],
                'action' => $rule['action'],
                'priority' => $rule['priority'],
                'filter' => [
                    'id' => $rule['filter']['id'],
                    'paused' => $rule['filter']['paused'],
                    'description' => $rule['filter']['description'],
                    'expression' => $rule['filter']['expression'],
                    'ref' => $rule['filter']['ref'],
                ],
                'created_on' => $rule['created_on'],
                'modified_on' => $rule['modified_on'],
            ], $data['result'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Cloudflare SSL settings.
     *
     * @param string $zoneId Zone ID
     *
     * @return array{
     *     id: string,
     *     status: string,
     *     method: string,
     *     type: string,
     *     settings: array{
     *         min_tls_version: string,
     *         ciphers: array<int, string>,
     *         early_hints: string,
     *         tls_1_3: string,
     *         automatic_https_rewrites: string,
     *         opportunistic_encryption: string,
     *         minify: array{
     *             css: string,
     *             html: string,
     *             js: string,
     *         },
     *         mirage: string,
     *         polish: string,
     *         webp: string,
     *         brotli: string,
     *         rocket_loader: string,
     *         security_level: string,
     *         development_mode: int,
     *         security_headers: array{
     *             strict_transport_security: array{
     *                 enabled: bool,
     *                 max_age: int,
     *                 include_subdomains: bool,
     *                 nosniff: bool,
     *             },
     *         },
     *         edge_cache_ttl: int,
     *         browser_cache_ttl: int,
     *         always_online: string,
     *         cache_level: string,
     *     },
     *     created_on: string,
     *     modified_on: string,
     * }|string
     */
    public function getSslSettings(string $zoneId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.cloudflare.com/client/{$this->apiVersion}/zones/{$zoneId}/settings/ssl", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['success']) && !$data['success']) {
                return 'Error getting SSL settings: '.($data['errors'][0]['message'] ?? 'Unknown error');
            }

            $result = $data['result'];

            return [
                'id' => $result['id'],
                'status' => $result['status'],
                'method' => $result['method'],
                'type' => $result['type'],
                'settings' => [
                    'min_tls_version' => $result['settings']['min_tls_version'],
                    'ciphers' => $result['settings']['ciphers'],
                    'early_hints' => $result['settings']['early_hints'],
                    'tls_1_3' => $result['settings']['tls_1_3'],
                    'automatic_https_rewrites' => $result['settings']['automatic_https_rewrites'],
                    'opportunistic_encryption' => $result['settings']['opportunistic_encryption'],
                    'minify' => [
                        'css' => $result['settings']['minify']['css'],
                        'html' => $result['settings']['minify']['html'],
                        'js' => $result['settings']['minify']['js'],
                    ],
                    'mirage' => $result['settings']['mirage'],
                    'polish' => $result['settings']['polish'],
                    'webp' => $result['settings']['webp'],
                    'brotli' => $result['settings']['brotli'],
                    'rocket_loader' => $result['settings']['rocket_loader'],
                    'security_level' => $result['settings']['security_level'],
                    'development_mode' => $result['settings']['development_mode'],
                    'security_headers' => [
                        'strict_transport_security' => [
                            'enabled' => $result['settings']['security_headers']['strict_transport_security']['enabled'],
                            'max_age' => $result['settings']['security_headers']['strict_transport_security']['max_age'],
                            'include_subdomains' => $result['settings']['security_headers']['strict_transport_security']['include_subdomains'],
                            'nosniff' => $result['settings']['security_headers']['strict_transport_security']['nosniff'],
                        ],
                    ],
                    'edge_cache_ttl' => $result['settings']['edge_cache_ttl'],
                    'browser_cache_ttl' => $result['settings']['browser_cache_ttl'],
                    'always_online' => $result['settings']['always_online'],
                    'cache_level' => $result['settings']['cache_level'],
                ],
                'created_on' => $result['created_on'],
                'modified_on' => $result['modified_on'],
            ];
        } catch (\Exception $e) {
            return 'Error getting SSL settings: '.$e->getMessage();
        }
    }
}
