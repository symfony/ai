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
#[AsTool('azure_vm_list', 'Tool that lists Azure virtual machines')]
#[AsTool('azure_storage_list_accounts', 'Tool that lists Azure storage accounts', method: 'listStorageAccounts')]
#[AsTool('azure_sql_list_servers', 'Tool that lists Azure SQL servers', method: 'listSqlServers')]
#[AsTool('azure_resource_groups_list', 'Tool that lists Azure resource groups', method: 'listResourceGroups')]
#[AsTool('azure_webapps_list', 'Tool that lists Azure web apps', method: 'listWebApps')]
#[AsTool('azure_functions_list', 'Tool that lists Azure Functions', method: 'listFunctions')]
final readonly class Azure
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken = '',
        private string $subscriptionId = '',
        private string $tenantId = '',
        private string $clientId = '',
        private string $apiVersion = '2021-03-01',
        private array $options = [],
    ) {
    }

    /**
     * List Azure virtual machines.
     *
     * @param string $resourceGroupName Resource group name (optional)
     * @param string $filter            OData filter expression
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     location: string,
     *     tags: array<string, string>,
     *     properties: array{
     *         vmId: string,
     *         hardwareProfile: array{
     *             vmSize: string,
     *         },
     *         storageProfile: array{
     *             imageReference: array{
     *                 publisher: string,
     *                 offer: string,
     *                 sku: string,
     *                 version: string,
     *             },
     *             osDisk: array{
     *                 osType: string,
     *                 name: string,
     *                 createOption: string,
     *                 diskSizeGB: int,
     *                 managedDisk: array{
     *                     id: string,
     *                     storageAccountType: string,
     *                 },
     *             },
     *         },
     *         osProfile: array{
     *             computerName: string,
     *             adminUsername: string,
     *         },
     *         networkProfile: array{
     *             networkInterfaces: array<int, array{
     *                 id: string,
     *             }>,
     *         },
     *         provisioningState: string,
     *     },
     * }>
     */
    public function __invoke(
        string $resourceGroupName = '',
        string $filter = '',
    ): array {
        try {
            $params = [
                'api-version' => $this->apiVersion,
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $url = $resourceGroupName
                ? "https://management.azure.com/subscriptions/{$this->subscriptionId}/resourceGroups/{$resourceGroupName}/providers/Microsoft.Compute/virtualMachines"
                : "https://management.azure.com/subscriptions/{$this->subscriptionId}/providers/Microsoft.Compute/virtualMachines";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($vm) => [
                'id' => $vm['id'],
                'name' => $vm['name'],
                'type' => $vm['type'],
                'location' => $vm['location'],
                'tags' => $vm['tags'] ?? [],
                'properties' => [
                    'vmId' => $vm['properties']['vmId'],
                    'hardwareProfile' => [
                        'vmSize' => $vm['properties']['hardwareProfile']['vmSize'],
                    ],
                    'storageProfile' => [
                        'imageReference' => [
                            'publisher' => $vm['properties']['storageProfile']['imageReference']['publisher'],
                            'offer' => $vm['properties']['storageProfile']['imageReference']['offer'],
                            'sku' => $vm['properties']['storageProfile']['imageReference']['sku'],
                            'version' => $vm['properties']['storageProfile']['imageReference']['version'],
                        ],
                        'osDisk' => [
                            'osType' => $vm['properties']['storageProfile']['osDisk']['osType'],
                            'name' => $vm['properties']['storageProfile']['osDisk']['name'],
                            'createOption' => $vm['properties']['storageProfile']['osDisk']['createOption'],
                            'diskSizeGB' => $vm['properties']['storageProfile']['osDisk']['diskSizeGB'],
                            'managedDisk' => [
                                'id' => $vm['properties']['storageProfile']['osDisk']['managedDisk']['id'],
                                'storageAccountType' => $vm['properties']['storageProfile']['osDisk']['managedDisk']['storageAccountType'],
                            ],
                        ],
                    ],
                    'osProfile' => [
                        'computerName' => $vm['properties']['osProfile']['computerName'],
                        'adminUsername' => $vm['properties']['osProfile']['adminUsername'],
                    ],
                    'networkProfile' => [
                        'networkInterfaces' => array_map(fn ($nic) => [
                            'id' => $nic['id'],
                        ], $vm['properties']['networkProfile']['networkInterfaces']),
                    ],
                    'provisioningState' => $vm['properties']['provisioningState'],
                ],
            ], $data['value'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Azure storage accounts.
     *
     * @param string $resourceGroupName Resource group name (optional)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     location: string,
     *     tags: array<string, string>,
     *     properties: array{
     *         provisioningState: string,
     *         primaryEndpoints: array{
     *             blob: string,
     *             queue: string,
     *             table: string,
     *             file: string,
     *         },
     *         primaryLocation: string,
     *         statusOfPrimary: string,
     *         creationTime: string,
     *         accountType: string,
     *     },
     * }>
     */
    public function listStorageAccounts(string $resourceGroupName = ''): array
    {
        try {
            $params = [
                'api-version' => '2021-06-01',
            ];

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $url = $resourceGroupName
                ? "https://management.azure.com/subscriptions/{$this->subscriptionId}/resourceGroups/{$resourceGroupName}/providers/Microsoft.Storage/storageAccounts"
                : "https://management.azure.com/subscriptions/{$this->subscriptionId}/providers/Microsoft.Storage/storageAccounts";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($account) => [
                'id' => $account['id'],
                'name' => $account['name'],
                'type' => $account['type'],
                'location' => $account['location'],
                'tags' => $account['tags'] ?? [],
                'properties' => [
                    'provisioningState' => $account['properties']['provisioningState'],
                    'primaryEndpoints' => [
                        'blob' => $account['properties']['primaryEndpoints']['blob'] ?? '',
                        'queue' => $account['properties']['primaryEndpoints']['queue'] ?? '',
                        'table' => $account['properties']['primaryEndpoints']['table'] ?? '',
                        'file' => $account['properties']['primaryEndpoints']['file'] ?? '',
                    ],
                    'primaryLocation' => $account['properties']['primaryLocation'],
                    'statusOfPrimary' => $account['properties']['statusOfPrimary'],
                    'creationTime' => $account['properties']['creationTime'],
                    'accountType' => $account['properties']['accountType'] ?? 'Standard_LRS',
                ],
            ], $data['value'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Azure SQL servers.
     *
     * @param string $resourceGroupName Resource group name (optional)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     location: string,
     *     tags: array<string, string>,
     *     properties: array{
     *         administratorLogin: string,
     *         version: string,
     *         state: string,
     *         fullyQualifiedDomainName: string,
     *     },
     * }>
     */
    public function listSqlServers(string $resourceGroupName = ''): array
    {
        try {
            $params = [
                'api-version' => '2021-02-01-preview',
            ];

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $url = $resourceGroupName
                ? "https://management.azure.com/subscriptions/{$this->subscriptionId}/resourceGroups/{$resourceGroupName}/providers/Microsoft.Sql/servers"
                : "https://management.azure.com/subscriptions/{$this->subscriptionId}/providers/Microsoft.Sql/servers";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($server) => [
                'id' => $server['id'],
                'name' => $server['name'],
                'type' => $server['type'],
                'location' => $server['location'],
                'tags' => $server['tags'] ?? [],
                'properties' => [
                    'administratorLogin' => $server['properties']['administratorLogin'],
                    'version' => $server['properties']['version'],
                    'state' => $server['properties']['state'],
                    'fullyQualifiedDomainName' => $server['properties']['fullyQualifiedDomainName'],
                ],
            ], $data['value'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Azure resource groups.
     *
     * @param string $filter OData filter expression
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     location: string,
     *     tags: array<string, string>,
     *     properties: array{
     *         provisioningState: string,
     *     },
     * }>
     */
    public function listResourceGroups(string $filter = ''): array
    {
        try {
            $params = [
                'api-version' => '2021-04-01',
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $response = $this->httpClient->request('GET', "https://management.azure.com/subscriptions/{$this->subscriptionId}/resourcegroups", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($rg) => [
                'id' => $rg['id'],
                'name' => $rg['name'],
                'type' => $rg['type'],
                'location' => $rg['location'],
                'tags' => $rg['tags'] ?? [],
                'properties' => [
                    'provisioningState' => $rg['properties']['provisioningState'],
                ],
            ], $data['value'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Azure web apps.
     *
     * @param string $resourceGroupName Resource group name (optional)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     location: string,
     *     tags: array<string, string>,
     *     properties: array{
     *         state: string,
     *         hostNames: array<int, string>,
     *         repositorySiteName: string,
     *         usageState: string,
     *         enabled: bool,
     *         enabledHostNames: array<int, string>,
     *         availabilityState: string,
     *         serverFarmId: string,
     *         reserved: bool,
     *         isXenon: bool,
     *         hyperV: bool,
     *         lastModifiedTimeUtc: string,
     *         siteConfig: array<string, mixed>,
     *         trafficManagerHostNames: array<int, string>,
     *         scmSiteAlsoStopped: bool,
     *         targetSwapSlot: string,
     *         hostingEnvironmentProfile: array<string, mixed>|null,
     *         clientAffinityEnabled: bool,
     *         clientCertEnabled: bool,
     *         clientCertMode: string,
     *         clientCertExclusionPaths: string,
     *         hostNamesDisabled: bool,
     *         customDomainVerificationId: string,
     *         outboundIpAddresses: string,
     *         possibleOutboundIpAddresses: string,
     *         containerSize: int,
     *         dailyMemoryTimeQuota: int,
     *         suspendedTill: string,
     *         maxNumberOfWorkers: int,
     *         cloningInfo: array<string, mixed>|null,
     *         resourceGroup: string,
     *         isDefaultContainer: bool,
     *         defaultHostName: string,
     *         slotSwapStatus: array<string, mixed>|null,
     *         httpsOnly: bool,
     *         redundancyMode: string,
     *         storageAccountRequired: bool,
     *         keyVaultReferenceIdentity: string,
     *         virtualNetworkSubnetId: string,
     *     },
     * }>
     */
    public function listWebApps(string $resourceGroupName = ''): array
    {
        try {
            $params = [
                'api-version' => '2021-02-01',
            ];

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $url = $resourceGroupName
                ? "https://management.azure.com/subscriptions/{$this->subscriptionId}/resourceGroups/{$resourceGroupName}/providers/Microsoft.Web/sites"
                : "https://management.azure.com/subscriptions/{$this->subscriptionId}/providers/Microsoft.Web/sites";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($app) => [
                'id' => $app['id'],
                'name' => $app['name'],
                'type' => $app['type'],
                'location' => $app['location'],
                'tags' => $app['tags'] ?? [],
                'properties' => [
                    'state' => $app['properties']['state'],
                    'hostNames' => $app['properties']['hostNames'],
                    'repositorySiteName' => $app['properties']['repositorySiteName'],
                    'usageState' => $app['properties']['usageState'],
                    'enabled' => $app['properties']['enabled'],
                    'enabledHostNames' => $app['properties']['enabledHostNames'],
                    'availabilityState' => $app['properties']['availabilityState'],
                    'serverFarmId' => $app['properties']['serverFarmId'],
                    'reserved' => $app['properties']['reserved'],
                    'isXenon' => $app['properties']['isXenon'],
                    'hyperV' => $app['properties']['hyperV'],
                    'lastModifiedTimeUtc' => $app['properties']['lastModifiedTimeUtc'],
                    'siteConfig' => $app['properties']['siteConfig'] ?? [],
                    'trafficManagerHostNames' => $app['properties']['trafficManagerHostNames'],
                    'scmSiteAlsoStopped' => $app['properties']['scmSiteAlsoStopped'],
                    'targetSwapSlot' => $app['properties']['targetSwapSlot'] ?? '',
                    'hostingEnvironmentProfile' => $app['properties']['hostingEnvironmentProfile'] ?? null,
                    'clientAffinityEnabled' => $app['properties']['clientAffinityEnabled'],
                    'clientCertEnabled' => $app['properties']['clientCertEnabled'],
                    'clientCertMode' => $app['properties']['clientCertMode'],
                    'clientCertExclusionPaths' => $app['properties']['clientCertExclusionPaths'] ?? '',
                    'hostNamesDisabled' => $app['properties']['hostNamesDisabled'],
                    'customDomainVerificationId' => $app['properties']['customDomainVerificationId'] ?? '',
                    'outboundIpAddresses' => $app['properties']['outboundIpAddresses'] ?? '',
                    'possibleOutboundIpAddresses' => $app['properties']['possibleOutboundIpAddresses'] ?? '',
                    'containerSize' => $app['properties']['containerSize'] ?? 0,
                    'dailyMemoryTimeQuota' => $app['properties']['dailyMemoryTimeQuota'] ?? 0,
                    'suspendedTill' => $app['properties']['suspendedTill'] ?? '',
                    'maxNumberOfWorkers' => $app['properties']['maxNumberOfWorkers'] ?? 0,
                    'cloningInfo' => $app['properties']['cloningInfo'] ?? null,
                    'resourceGroup' => $app['properties']['resourceGroup'],
                    'isDefaultContainer' => $app['properties']['isDefaultContainer'],
                    'defaultHostName' => $app['properties']['defaultHostName'],
                    'slotSwapStatus' => $app['properties']['slotSwapStatus'] ?? null,
                    'httpsOnly' => $app['properties']['httpsOnly'],
                    'redundancyMode' => $app['properties']['redundancyMode'] ?? 'None',
                    'storageAccountRequired' => $app['properties']['storageAccountRequired'] ?? false,
                    'keyVaultReferenceIdentity' => $app['properties']['keyVaultReferenceIdentity'] ?? '',
                    'virtualNetworkSubnetId' => $app['properties']['virtualNetworkSubnetId'] ?? '',
                ],
            ], $data['value'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Azure Functions.
     *
     * @param string $resourceGroupName Resource group name (optional)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: string,
     *     location: string,
     *     tags: array<string, string>,
     *     properties: array{
     *         state: string,
     *         hostNames: array<int, string>,
     *         repositorySiteName: string,
     *         usageState: string,
     *         enabled: bool,
     *         enabledHostNames: array<int, string>,
     *         availabilityState: string,
     *         serverFarmId: string,
     *         reserved: bool,
     *         isXenon: bool,
     *         hyperV: bool,
     *         lastModifiedTimeUtc: string,
     *         siteConfig: array<string, mixed>,
     *         trafficManagerHostNames: array<int, string>,
     *         scmSiteAlsoStopped: bool,
     *         targetSwapSlot: string,
     *         hostingEnvironmentProfile: array<string, mixed>|null,
     *         clientAffinityEnabled: bool,
     *         clientCertEnabled: bool,
     *         clientCertMode: string,
     *         clientCertExclusionPaths: string,
     *         hostNamesDisabled: bool,
     *         customDomainVerificationId: string,
     *         outboundIpAddresses: string,
     *         possibleOutboundIpAddresses: string,
     *         containerSize: int,
     *         dailyMemoryTimeQuota: int,
     *         suspendedTill: string,
     *         maxNumberOfWorkers: int,
     *         cloningInfo: array<string, mixed>|null,
     *         resourceGroup: string,
     *         isDefaultContainer: bool,
     *         defaultHostName: string,
     *         slotSwapStatus: array<string, mixed>|null,
     *         httpsOnly: bool,
     *         redundancyMode: string,
     *         storageAccountRequired: bool,
     *         keyVaultReferenceIdentity: string,
     *         virtualNetworkSubnetId: string,
     *     },
     * }>
     */
    public function listFunctions(string $resourceGroupName = ''): array
    {
        try {
            $params = [
                'api-version' => '2021-02-01',
            ];

            $headers = ['Content-Type' => 'application/json'];
            if ($this->accessToken) {
                $headers['Authorization'] = 'Bearer '.$this->accessToken;
            }

            $url = $resourceGroupName
                ? "https://management.azure.com/subscriptions/{$this->subscriptionId}/resourceGroups/{$resourceGroupName}/providers/Microsoft.Web/sites"
                : "https://management.azure.com/subscriptions/{$this->subscriptionId}/providers/Microsoft.Web/sites";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, array_merge($params, ['kind' => 'functionapp'])),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($function) => [
                'id' => $function['id'],
                'name' => $function['name'],
                'type' => $function['type'],
                'location' => $function['location'],
                'tags' => $function['tags'] ?? [],
                'properties' => [
                    'state' => $function['properties']['state'],
                    'hostNames' => $function['properties']['hostNames'],
                    'repositorySiteName' => $function['properties']['repositorySiteName'],
                    'usageState' => $function['properties']['usageState'],
                    'enabled' => $function['properties']['enabled'],
                    'enabledHostNames' => $function['properties']['enabledHostNames'],
                    'availabilityState' => $function['properties']['availabilityState'],
                    'serverFarmId' => $function['properties']['serverFarmId'],
                    'reserved' => $function['properties']['reserved'],
                    'isXenon' => $function['properties']['isXenon'],
                    'hyperV' => $function['properties']['hyperV'],
                    'lastModifiedTimeUtc' => $function['properties']['lastModifiedTimeUtc'],
                    'siteConfig' => $function['properties']['siteConfig'] ?? [],
                    'trafficManagerHostNames' => $function['properties']['trafficManagerHostNames'],
                    'scmSiteAlsoStopped' => $function['properties']['scmSiteAlsoStopped'],
                    'targetSwapSlot' => $function['properties']['targetSwapSlot'] ?? '',
                    'hostingEnvironmentProfile' => $function['properties']['hostingEnvironmentProfile'] ?? null,
                    'clientAffinityEnabled' => $function['properties']['clientAffinityEnabled'],
                    'clientCertEnabled' => $function['properties']['clientCertEnabled'],
                    'clientCertMode' => $function['properties']['clientCertMode'],
                    'clientCertExclusionPaths' => $function['properties']['clientCertExclusionPaths'] ?? '',
                    'hostNamesDisabled' => $function['properties']['hostNamesDisabled'],
                    'customDomainVerificationId' => $function['properties']['customDomainVerificationId'] ?? '',
                    'outboundIpAddresses' => $function['properties']['outboundIpAddresses'] ?? '',
                    'possibleOutboundIpAddresses' => $function['properties']['possibleOutboundIpAddresses'] ?? '',
                    'containerSize' => $function['properties']['containerSize'] ?? 0,
                    'dailyMemoryTimeQuota' => $function['properties']['dailyMemoryTimeQuota'] ?? 0,
                    'suspendedTill' => $function['properties']['suspendedTill'] ?? '',
                    'maxNumberOfWorkers' => $function['properties']['maxNumberOfWorkers'] ?? 0,
                    'cloningInfo' => $function['properties']['cloningInfo'] ?? null,
                    'resourceGroup' => $function['properties']['resourceGroup'],
                    'isDefaultContainer' => $function['properties']['isDefaultContainer'],
                    'defaultHostName' => $function['properties']['defaultHostName'],
                    'slotSwapStatus' => $function['properties']['slotSwapStatus'] ?? null,
                    'httpsOnly' => $function['properties']['httpsOnly'],
                    'redundancyMode' => $function['properties']['redundancyMode'] ?? 'None',
                    'storageAccountRequired' => $function['properties']['storageAccountRequired'] ?? false,
                    'keyVaultReferenceIdentity' => $function['properties']['keyVaultReferenceIdentity'] ?? '',
                    'virtualNetworkSubnetId' => $function['properties']['virtualNetworkSubnetId'] ?? '',
                ],
            ], $data['value'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }
}
