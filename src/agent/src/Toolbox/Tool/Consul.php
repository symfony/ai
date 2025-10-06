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
#[AsTool('consul_get_services', 'Tool that gets Consul services')]
#[AsTool('consul_get_nodes', 'Tool that gets Consul nodes', method: 'getNodes')]
#[AsTool('consul_get_key', 'Tool that gets Consul key-value', method: 'getKey')]
#[AsTool('consul_set_key', 'Tool that sets Consul key-value', method: 'setKey')]
#[AsTool('consul_delete_key', 'Tool that deletes Consul key-value', method: 'deleteKey')]
#[AsTool('consul_get_health', 'Tool that gets Consul health checks', method: 'getHealth')]
final readonly class Consul
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'http://localhost:8500',
        private string $datacenter = '',
        private array $options = [],
    ) {
    }

    /**
     * Get Consul services.
     *
     * @param string $service  Service name filter
     * @param string $tag      Tag filter
     * @param string $near     Near node
     * @param string $nodeMeta Node metadata filter
     *
     * @return array<int, array{
     *     id: string,
     *     node: string,
     *     address: string,
     *     datacenter: string,
     *     taggedAddresses: array<string, string>,
     *     nodeMeta: array<string, string>,
     *     serviceId: string,
     *     serviceName: string,
     *     serviceTags: array<int, string>,
     *     serviceAddress: string,
     *     servicePort: int,
     *     serviceMeta: array<string, string>,
     *     serviceWeights: array{
     *         passing: int,
     *         warning: int,
     *     },
     *     serviceEnableTagOverride: bool,
     *     createIndex: int,
     *     modifyIndex: int,
     * }>
     */
    public function __invoke(
        string $service = '',
        string $tag = '',
        string $near = '',
        string $nodeMeta = '',
    ): array {
        try {
            $params = [];

            if ($service) {
                $params['filter'] = "Service == \"{$service}\"";
            }
            if ($tag) {
                $params['tag'] = $tag;
            }
            if ($near) {
                $params['near'] = $near;
            }
            if ($nodeMeta) {
                $params['node-meta'] = $nodeMeta;
            }
            if ($this->datacenter) {
                $params['dc'] = $this->datacenter;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/health/service/{$service}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($item) => [
                'id' => $item['Service']['ID'],
                'node' => $item['Node']['Node'],
                'address' => $item['Node']['Address'],
                'datacenter' => $item['Node']['Datacenter'],
                'taggedAddresses' => $item['Node']['TaggedAddresses'] ?? [],
                'nodeMeta' => $item['Node']['Meta'] ?? [],
                'serviceId' => $item['Service']['ID'],
                'serviceName' => $item['Service']['Service'],
                'serviceTags' => $item['Service']['Tags'] ?? [],
                'serviceAddress' => $item['Service']['Address'] ?? '',
                'servicePort' => $item['Service']['Port'],
                'serviceMeta' => $item['Service']['Meta'] ?? [],
                'serviceWeights' => [
                    'passing' => $item['Service']['Weights']['Passing'],
                    'warning' => $item['Service']['Weights']['Warning'],
                ],
                'serviceEnableTagOverride' => $item['Service']['EnableTagOverride'] ?? false,
                'createIndex' => $item['Service']['CreateIndex'],
                'modifyIndex' => $item['Service']['ModifyIndex'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Consul nodes.
     *
     * @param string $near     Near node
     * @param string $nodeMeta Node metadata filter
     *
     * @return array<int, array{
     *     id: string,
     *     node: string,
     *     address: string,
     *     datacenter: string,
     *     taggedAddresses: array<string, string>,
     *     meta: array<string, string>,
     *     createIndex: int,
     *     modifyIndex: int,
     * }>
     */
    public function getNodes(
        string $near = '',
        string $nodeMeta = '',
    ): array {
        try {
            $params = [];

            if ($near) {
                $params['near'] = $near;
            }
            if ($nodeMeta) {
                $params['node-meta'] = $nodeMeta;
            }
            if ($this->datacenter) {
                $params['dc'] = $this->datacenter;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/catalog/nodes", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($node) => [
                'id' => $node['ID'],
                'node' => $node['Node'],
                'address' => $node['Address'],
                'datacenter' => $node['Datacenter'],
                'taggedAddresses' => $node['TaggedAddresses'] ?? [],
                'meta' => $node['Meta'] ?? [],
                'createIndex' => $node['CreateIndex'],
                'modifyIndex' => $node['ModifyIndex'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Consul key-value.
     *
     * @param string $key       Key path
     * @param string $recurse   Recursive query
     * @param string $separator Key separator
     *
     * @return array{
     *     key: string,
     *     value: string,
     *     flags: int,
     *     createIndex: int,
     *     modifyIndex: int,
     *     lockIndex: int,
     * }|array<int, array{
     *     key: string,
     *     value: string,
     *     flags: int,
     *     createIndex: int,
     *     modifyIndex: int,
     *     lockIndex: int,
     * }>|string
     */
    public function getKey(
        string $key,
        string $recurse = 'false',
        string $separator = '',
    ): array|string {
        try {
            $params = [];

            if ('true' === $recurse) {
                $params['recurse'] = 'true';
            }
            if ($separator) {
                $params['separator'] = $separator;
            }
            if ($this->datacenter) {
                $params['dc'] = $this->datacenter;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/v1/kv/{$key}", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if ('true' === $recurse) {
                return array_map(fn ($item) => [
                    'key' => $item['Key'],
                    'value' => base64_decode($item['Value'] ?? ''),
                    'flags' => $item['Flags'],
                    'createIndex' => $item['CreateIndex'],
                    'modifyIndex' => $item['ModifyIndex'],
                    'lockIndex' => $item['LockIndex'],
                ], $data);
            }

            if (empty($data)) {
                return 'Key not found';
            }

            return [
                'key' => $data[0]['Key'],
                'value' => base64_decode($data[0]['Value'] ?? ''),
                'flags' => $data[0]['Flags'],
                'createIndex' => $data[0]['CreateIndex'],
                'modifyIndex' => $data[0]['ModifyIndex'],
                'lockIndex' => $data[0]['LockIndex'],
            ];
        } catch (\Exception $e) {
            return 'Error getting key: '.$e->getMessage();
        }
    }

    /**
     * Set Consul key-value.
     *
     * @param string $key     Key path
     * @param string $value   Value to set
     * @param int    $flags   Flags
     * @param int    $cas     Check-and-set index
     * @param string $acquire Session to acquire
     * @param string $release Session to release
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function setKey(
        string $key,
        string $value,
        int $flags = 0,
        int $cas = 0,
        string $acquire = '',
        string $release = '',
    ): array|string {
        try {
            $params = [];

            if ($flags > 0) {
                $params['flags'] = $flags;
            }
            if ($cas > 0) {
                $params['cas'] = $cas;
            }
            if ($acquire) {
                $params['acquire'] = $acquire;
            }
            if ($release) {
                $params['release'] = $release;
            }
            if ($this->datacenter) {
                $params['dc'] = $this->datacenter;
            }

            $response = $this->httpClient->request('PUT', "{$this->baseUrl}/v1/kv/{$key}", [
                'query' => array_merge($this->options, $params),
                'body' => $value,
            ]);

            $success = 200 === $response->getStatusCode();
            $output = $response->getContent();

            return [
                'success' => $success,
                'output' => $output,
                'error' => $success ? '' : 'Failed to set key',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete Consul key-value.
     *
     * @param string $key     Key path
     * @param string $recurse Recursive delete
     * @param int    $cas     Check-and-set index
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function deleteKey(
        string $key,
        string $recurse = 'false',
        int $cas = 0,
    ): array|string {
        try {
            $params = [];

            if ('true' === $recurse) {
                $params['recurse'] = 'true';
            }
            if ($cas > 0) {
                $params['cas'] = $cas;
            }
            if ($this->datacenter) {
                $params['dc'] = $this->datacenter;
            }

            $response = $this->httpClient->request('DELETE', "{$this->baseUrl}/v1/kv/{$key}", [
                'query' => array_merge($this->options, $params),
            ]);

            $success = 200 === $response->getStatusCode();
            $output = $response->getContent();

            return [
                'success' => $success,
                'output' => $output,
                'error' => $success ? '' : 'Failed to delete key',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Consul health checks.
     *
     * @param string $service Service name
     * @param string $state   Health state filter (passing, warning, critical, maintenance)
     * @param string $near    Near node
     *
     * @return array<int, array{
     *     node: string,
     *     checkId: string,
     *     name: string,
     *     status: string,
     *     notes: string,
     *     output: string,
     *     serviceId: string,
     *     serviceName: string,
     *     serviceTags: array<int, string>,
     *     definition: array<string, mixed>,
     *     createIndex: int,
     *     modifyIndex: int,
     * }>
     */
    public function getHealth(
        string $service = '',
        string $state = '',
        string $near = '',
    ): array {
        try {
            $params = [];

            if ($state) {
                $params['state'] = $state;
            }
            if ($near) {
                $params['near'] = $near;
            }
            if ($this->datacenter) {
                $params['dc'] = $this->datacenter;
            }

            $url = $service
                ? "{$this->baseUrl}/v1/health/service/{$service}"
                : "{$this->baseUrl}/v1/health/state/{$state}";

            $response = $this->httpClient->request('GET', $url, [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if ($service) {
                $checks = [];
                foreach ($data as $item) {
                    foreach ($item['Checks'] ?? [] as $check) {
                        $checks[] = [
                            'node' => $check['Node'],
                            'checkId' => $check['CheckID'],
                            'name' => $check['Name'],
                            'status' => $check['Status'],
                            'notes' => $check['Notes'] ?? '',
                            'output' => $check['Output'] ?? '',
                            'serviceId' => $check['ServiceID'] ?? '',
                            'serviceName' => $check['ServiceName'] ?? '',
                            'serviceTags' => $check['ServiceTags'] ?? [],
                            'definition' => $check['Definition'] ?? [],
                            'createIndex' => $check['CreateIndex'],
                            'modifyIndex' => $check['ModifyIndex'],
                        ];
                    }
                }

                return $checks;
            }

            return array_map(fn ($check) => [
                'node' => $check['Node'],
                'checkId' => $check['CheckID'],
                'name' => $check['Name'],
                'status' => $check['Status'],
                'notes' => $check['Notes'] ?? '',
                'output' => $check['Output'] ?? '',
                'serviceId' => $check['ServiceID'] ?? '',
                'serviceName' => $check['ServiceName'] ?? '',
                'serviceTags' => $check['ServiceTags'] ?? [],
                'definition' => $check['Definition'] ?? [],
                'createIndex' => $check['CreateIndex'],
                'modifyIndex' => $check['ModifyIndex'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
