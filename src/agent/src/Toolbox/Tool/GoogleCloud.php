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
#[AsTool('gcp_compute_list_instances', 'Tool that lists Google Cloud Compute instances')]
#[AsTool('gcp_storage_list_buckets', 'Tool that lists Google Cloud Storage buckets', method: 'listBuckets')]
#[AsTool('gcp_sql_list_instances', 'Tool that lists Google Cloud SQL instances', method: 'listSqlInstances')]
#[AsTool('gcp_app_engine_list_services', 'Tool that lists Google App Engine services', method: 'listAppEngineServices')]
#[AsTool('gcp_functions_list', 'Tool that lists Google Cloud Functions', method: 'listFunctions')]
#[AsTool('gcp_iam_list_service_accounts', 'Tool that lists Google Cloud IAM service accounts', method: 'listServiceAccounts')]
final readonly class GoogleCloud
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken = '',
        private string $projectId = '',
        private string $apiVersion = 'v1',
        private array $options = [],
    ) {
    }

    /**
     * List Google Cloud Compute instances.
     *
     * @param string $zone   Zone name (optional)
     * @param string $filter Filter expression
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     machineType: string,
     *     status: string,
     *     zone: string,
     *     creationTimestamp: string,
     *     networkInterfaces: array<int, array{
     *         network: string,
     *         networkIP: string,
     *         accessConfigs: array<int, array{
     *             natIP: string,
     *         }>,
     *     }>,
     *     tags: array{
     *         items: array<int, string>,
     *     },
     *     labels: array<string, string>,
     * }>
     */
    public function __invoke(
        string $zone = '',
        string $filter = '',
    ): array {
        try {
            $params = [];

            if ($filter) {
                $params['filter'] = $filter;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $url = $zone
                ? "https://compute.googleapis.com/compute/{$this->apiVersion}/projects/{$this->projectId}/zones/{$zone}/instances"
                : "https://compute.googleapis.com/compute/{$this->apiVersion}/projects/{$this->projectId}/aggregated/instances";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            $instances = [];
            if ($zone) {
                $instances = $data['items'] ?? [];
            } else {
                foreach ($data['items'] ?? [] as $zoneData) {
                    if (isset($zoneData['instances'])) {
                        $instances = array_merge($instances, $zoneData['instances']);
                    }
                }
            }

            return array_map(fn ($instance) => [
                'id' => $instance['id'],
                'name' => $instance['name'],
                'machineType' => basename($instance['machineType']),
                'status' => $instance['status'],
                'zone' => basename($instance['zone']),
                'creationTimestamp' => $instance['creationTimestamp'],
                'networkInterfaces' => array_map(fn ($nic) => [
                    'network' => basename($nic['network']),
                    'networkIP' => $nic['networkIP'],
                    'accessConfigs' => array_map(fn ($config) => [
                        'natIP' => $config['natIP'] ?? '',
                    ], $nic['accessConfigs'] ?? []),
                ], $instance['networkInterfaces'] ?? []),
                'tags' => [
                    'items' => $instance['tags']['items'] ?? [],
                ],
                'labels' => $instance['labels'] ?? [],
            ], $instances);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Google Cloud Storage buckets.
     *
     * @param string $project Project ID (optional)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     timeCreated: string,
     *     updated: string,
     *     location: string,
     *     storageClass: string,
     *     labels: array<string, string>,
     * }>
     */
    public function listBuckets(string $project = ''): array
    {
        try {
            $projectId = $project ?: $this->projectId;

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $response = $this->httpClient->request('GET', "https://storage.googleapis.com/storage/{$this->apiVersion}/b", [
                'headers' => $headers,
                'query' => array_merge($this->options, ['project' => $projectId]),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($bucket) => [
                'id' => $bucket['id'],
                'name' => $bucket['name'],
                'timeCreated' => $bucket['timeCreated'],
                'updated' => $bucket['updated'],
                'location' => $bucket['location'],
                'storageClass' => $bucket['storageClass'],
                'labels' => $bucket['labels'] ?? [],
            ], $data['items'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Google Cloud SQL instances.
     *
     * @param string $filter Filter expression
     *
     * @return array<int, array{
     *     name: string,
     *     instanceType: string,
     *     state: string,
     *     databaseVersion: string,
     *     region: string,
     *     ipAddresses: array<int, array{
     *         ipAddress: string,
     *         type: string,
     *     }>,
     *     settings: array{
     *         tier: string,
     *         dataDiskSizeGb: int,
     *         dataDiskType: string,
     *         ipConfiguration: array<string, mixed>,
     *     },
     *     labels: array<string, string>,
     * }>
     */
    public function listSqlInstances(string $filter = ''): array
    {
        try {
            $params = [];

            if ($filter) {
                $params['filter'] = $filter;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $response = $this->httpClient->request('GET', "https://sqladmin.googleapis.com/sql/{$this->apiVersion}/projects/{$this->projectId}/instances", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($instance) => [
                'name' => $instance['name'],
                'instanceType' => $instance['instanceType'],
                'state' => $instance['state'],
                'databaseVersion' => $instance['databaseVersion'],
                'region' => $instance['region'],
                'ipAddresses' => array_map(fn ($ip) => [
                    'ipAddress' => $ip['ipAddress'],
                    'type' => $ip['type'],
                ], $instance['ipAddresses'] ?? []),
                'settings' => [
                    'tier' => $instance['settings']['tier'],
                    'dataDiskSizeGb' => $instance['settings']['dataDiskSizeGb'],
                    'dataDiskType' => $instance['settings']['dataDiskType'],
                    'ipConfiguration' => $instance['settings']['ipConfiguration'] ?? [],
                ],
                'labels' => $instance['labels'] ?? [],
            ], $data['items'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Google App Engine services.
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     split: array{
     *         allocationPolicy: array<string, mixed>,
     *     },
     *     networkSettings: array<string, mixed>|null,
     *     labels: array<string, string>,
     * }>
     */
    public function listAppEngineServices(): array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $response = $this->httpClient->request('GET', "https://appengine.googleapis.com/{$this->apiVersion}/apps/{$this->projectId}/services", [
                'headers' => $headers,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($service) => [
                'id' => $service['id'],
                'name' => $service['name'],
                'split' => [
                    'allocationPolicy' => $service['split']['allocationPolicy'] ?? [],
                ],
                'networkSettings' => $service['networkSettings'] ?? null,
                'labels' => $service['labels'] ?? [],
            ], $data['services'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Google Cloud Functions.
     *
     * @param string $location Location filter
     *
     * @return array<int, array{
     *     name: string,
     *     description: string,
     *     status: string,
     *     entryPoint: string,
     *     runtime: string,
     *     timeout: string,
     *     availableMemoryMb: int,
     *     serviceAccountEmail: string,
     *     updateTime: string,
     *     versionId: string,
     *     labels: array<string, string>,
     *     sourceArchiveUrl: string,
     *     sourceUploadUrl: string,
     *     sourceRepository: array<string, mixed>|null,
     *     httpsTrigger: array<string, mixed>|null,
     *     eventTrigger: array<string, mixed>|null,
     * }>
     */
    public function listFunctions(string $location = ''): array
    {
        try {
            $params = [];

            if ($location) {
                $params['location'] = $location;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $response = $this->httpClient->request('GET', "https://cloudfunctions.googleapis.com/{$this->apiVersion}/projects/{$this->projectId}/locations/{$location}/functions", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($function) => [
                'name' => $function['name'],
                'description' => $function['description'] ?? '',
                'status' => $function['status'],
                'entryPoint' => $function['entryPoint'],
                'runtime' => $function['runtime'],
                'timeout' => $function['timeout'],
                'availableMemoryMb' => $function['availableMemoryMb'],
                'serviceAccountEmail' => $function['serviceAccountEmail'] ?? '',
                'updateTime' => $function['updateTime'],
                'versionId' => $function['versionId'] ?? '',
                'labels' => $function['labels'] ?? [],
                'sourceArchiveUrl' => $function['sourceArchiveUrl'] ?? '',
                'sourceUploadUrl' => $function['sourceUploadUrl'] ?? '',
                'sourceRepository' => $function['sourceRepository'] ?? null,
                'httpsTrigger' => $function['httpsTrigger'] ?? null,
                'eventTrigger' => $function['eventTrigger'] ?? null,
            ], $data['functions'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Google Cloud IAM service accounts.
     *
     * @param string $name Service account name prefix
     *
     * @return array<int, array{
     *     name: string,
     *     projectId: string,
     *     uniqueId: string,
     *     email: string,
     *     displayName: string,
     *     description: string,
     *     oauth2ClientId: string,
     *     disabled: bool,
     * }>
     */
    public function listServiceAccounts(string $name = ''): array
    {
        try {
            $params = [];

            if ($name) {
                $params['name'] = $name;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $response = $this->httpClient->request('GET', "https://iam.googleapis.com/{$this->apiVersion}/projects/{$this->projectId}/serviceAccounts", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($account) => [
                'name' => $account['name'],
                'projectId' => $account['projectId'],
                'uniqueId' => $account['uniqueId'],
                'email' => $account['email'],
                'displayName' => $account['displayName'] ?? '',
                'description' => $account['description'] ?? '',
                'oauth2ClientId' => $account['oauth2ClientId'] ?? '',
                'disabled' => $account['disabled'] ?? false,
            ], $data['accounts'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }
}
