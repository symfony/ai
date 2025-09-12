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
#[AsTool('powerbi_get_workspaces', 'Tool that gets Power BI workspaces')]
#[AsTool('powerbi_get_datasets', 'Tool that gets Power BI datasets', method: 'getDatasets')]
#[AsTool('powerbi_get_reports', 'Tool that gets Power BI reports', method: 'getReports')]
#[AsTool('powerbi_get_dashboards', 'Tool that gets Power BI dashboards', method: 'getDashboards')]
#[AsTool('powerbi_refresh_dataset', 'Tool that refreshes Power BI datasets', method: 'refreshDataset')]
#[AsTool('powerbi_create_dataset', 'Tool that creates Power BI datasets', method: 'createDataset')]
#[AsTool('powerbi_execute_query', 'Tool that executes Power BI queries', method: 'executeQuery')]
#[AsTool('powerbi_get_embed_token', 'Tool that gets Power BI embed tokens', method: 'getEmbedToken')]
final readonly class PowerBi
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $accessToken,
        private string $baseUrl = 'https://api.powerbi.com/v1.0/myorg',
        private array $options = [],
    ) {
    }

    /**
     * Get Power BI workspaces.
     *
     * @param string $filter OData filter expression
     * @param int    $top    Number of workspaces to return
     * @param int    $skip   Number of workspaces to skip
     *
     * @return array{
     *     success: bool,
     *     workspaces: array<int, array{
     *         id: string,
     *         name: string,
     *         isReadOnly: bool,
     *         isOnDedicatedCapacity: bool,
     *         capacityId: string,
     *         description: string,
     *         type: string,
     *         state: string,
     *         isOrphaned: bool,
     *         users: array<int, array{
     *             displayName: string,
     *             emailAddress: string,
     *             identifier: string,
     *             graphId: string,
     *             principalType: string,
     *             accessRight: string,
     *         }>,
     *         reports: array<int, array{
     *             id: string,
     *             name: string,
     *             webUrl: string,
     *             embedUrl: string,
     *             datasetId: string,
     *             reportType: string,
     *         }>,
     *         dashboards: array<int, array{
     *             id: string,
     *             displayName: string,
     *             webUrl: string,
     *             embedUrl: string,
     *             isReadOnly: bool,
     *         }>,
     *         datasets: array<int, array{
     *             id: string,
     *             name: string,
     *             webUrl: string,
     *             isRefreshable: bool,
     *             isEffectiveIdentityRequired: bool,
     *             isEffectiveIdentityRolesRequired: bool,
     *             isOnPremGatewayRequired: bool,
     *             targetStorageMode: string,
     *             createReportEmbedURL: string,
     *             qnaEmbedURL: string,
     *             addRowsAPIEnabled: bool,
     *             configuredBy: string,
     *             isRefreshable: bool,
     *             isEffectiveIdentityRequired: bool,
     *             isEffectiveIdentityRolesRequired: bool,
     *             isOnPremGatewayRequired: bool,
     *             targetStorageMode: string,
     *             createReportEmbedURL: string,
     *             qnaEmbedURL: string,
     *             addRowsAPIEnabled: bool,
     *             configuredBy: string,
     *         }>,
     *     }>,
     *     error: string,
     * }
     */
    public function __invoke(
        string $filter = '',
        int $top = 100,
        int $skip = 0,
    ): array {
        try {
            $params = [
                '$top' => max(1, min($top, 5000)),
                '$skip' => max(0, $skip),
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/groups", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'workspaces' => array_map(fn ($workspace) => [
                    'id' => $workspace['id'],
                    'name' => $workspace['name'],
                    'isReadOnly' => $workspace['isReadOnly'] ?? false,
                    'isOnDedicatedCapacity' => $workspace['isOnDedicatedCapacity'] ?? false,
                    'capacityId' => $workspace['capacityId'] ?? '',
                    'description' => $workspace['description'] ?? '',
                    'type' => $workspace['type'] ?? '',
                    'state' => $workspace['state'] ?? '',
                    'isOrphaned' => $workspace['isOrphaned'] ?? false,
                    'users' => array_map(fn ($user) => [
                        'displayName' => $user['displayName'],
                        'emailAddress' => $user['emailAddress'],
                        'identifier' => $user['identifier'],
                        'graphId' => $user['graphId'],
                        'principalType' => $user['principalType'],
                        'accessRight' => $user['accessRight'],
                    ], $workspace['users'] ?? []),
                    'reports' => array_map(fn ($report) => [
                        'id' => $report['id'],
                        'name' => $report['name'],
                        'webUrl' => $report['webUrl'],
                        'embedUrl' => $report['embedUrl'],
                        'datasetId' => $report['datasetId'],
                        'reportType' => $report['reportType'],
                    ], $workspace['reports'] ?? []),
                    'dashboards' => array_map(fn ($dashboard) => [
                        'id' => $dashboard['id'],
                        'displayName' => $dashboard['displayName'],
                        'webUrl' => $dashboard['webUrl'],
                        'embedUrl' => $dashboard['embedUrl'],
                        'isReadOnly' => $dashboard['isReadOnly'] ?? false,
                    ], $workspace['dashboards'] ?? []),
                    'datasets' => array_map(fn ($dataset) => [
                        'id' => $dataset['id'],
                        'name' => $dataset['name'],
                        'webUrl' => $dataset['webUrl'],
                        'isRefreshable' => $dataset['isRefreshable'] ?? false,
                        'isEffectiveIdentityRequired' => $dataset['isEffectiveIdentityRequired'] ?? false,
                        'isEffectiveIdentityRolesRequired' => $dataset['isEffectiveIdentityRolesRequired'] ?? false,
                        'isOnPremGatewayRequired' => $dataset['isOnPremGatewayRequired'] ?? false,
                        'targetStorageMode' => $dataset['targetStorageMode'] ?? '',
                        'createReportEmbedURL' => $dataset['createReportEmbedURL'] ?? '',
                        'qnaEmbedURL' => $dataset['qnaEmbedURL'] ?? '',
                        'addRowsAPIEnabled' => $dataset['addRowsAPIEnabled'] ?? false,
                        'configuredBy' => $dataset['configuredBy'] ?? '',
                    ], $workspace['datasets'] ?? []),
                ], $data['value'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'workspaces' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Power BI datasets.
     *
     * @param string $groupId Workspace ID
     * @param string $filter  OData filter expression
     * @param int    $top     Number of datasets to return
     * @param int    $skip    Number of datasets to skip
     *
     * @return array{
     *     success: bool,
     *     datasets: array<int, array{
     *         id: string,
     *         name: string,
     *         webUrl: string,
     *         isRefreshable: bool,
     *         isEffectiveIdentityRequired: bool,
     *         isEffectiveIdentityRolesRequired: bool,
     *         isOnPremGatewayRequired: bool,
     *         targetStorageMode: string,
     *         createReportEmbedURL: string,
     *         qnaEmbedURL: string,
     *         addRowsAPIEnabled: bool,
     *         configuredBy: string,
     *         defaultRetentionPolicy: string,
     *         tables: array<int, array{
     *             name: string,
     *             columns: array<int, array{
     *                 name: string,
     *                 dataType: string,
     *                 columnType: string,
     *                 formatString: string,
     *                 isHidden: bool,
     *             }>,
     *             measures: array<int, array{
     *                 name: string,
     *                 expression: string,
     *                 formatString: string,
     *             }>,
     *         }>,
     *     }>,
     *     error: string,
     * }
     */
    public function getDatasets(
        string $groupId = '',
        string $filter = '',
        int $top = 100,
        int $skip = 0,
    ): array {
        try {
            $params = [
                '$top' => max(1, min($top, 5000)),
                '$skip' => max(0, $skip),
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $url = $groupId ? "{$this->baseUrl}/groups/{$groupId}/datasets" : "{$this->baseUrl}/datasets";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'datasets' => array_map(fn ($dataset) => [
                    'id' => $dataset['id'],
                    'name' => $dataset['name'],
                    'webUrl' => $dataset['webUrl'],
                    'isRefreshable' => $dataset['isRefreshable'] ?? false,
                    'isEffectiveIdentityRequired' => $dataset['isEffectiveIdentityRequired'] ?? false,
                    'isEffectiveIdentityRolesRequired' => $dataset['isEffectiveIdentityRolesRequired'] ?? false,
                    'isOnPremGatewayRequired' => $dataset['isOnPremGatewayRequired'] ?? false,
                    'targetStorageMode' => $dataset['targetStorageMode'] ?? '',
                    'createReportEmbedURL' => $dataset['createReportEmbedURL'] ?? '',
                    'qnaEmbedURL' => $dataset['qnaEmbedURL'] ?? '',
                    'addRowsAPIEnabled' => $dataset['addRowsAPIEnabled'] ?? false,
                    'configuredBy' => $dataset['configuredBy'] ?? '',
                    'defaultRetentionPolicy' => $dataset['defaultRetentionPolicy'] ?? '',
                    'tables' => array_map(fn ($table) => [
                        'name' => $table['name'],
                        'columns' => array_map(fn ($column) => [
                            'name' => $column['name'],
                            'dataType' => $column['dataType'],
                            'columnType' => $column['columnType'] ?? '',
                            'formatString' => $column['formatString'] ?? '',
                            'isHidden' => $column['isHidden'] ?? false,
                        ], $table['columns'] ?? []),
                        'measures' => array_map(fn ($measure) => [
                            'name' => $measure['name'],
                            'expression' => $measure['expression'],
                            'formatString' => $measure['formatString'] ?? '',
                        ], $table['measures'] ?? []),
                    ], $dataset['tables'] ?? []),
                ], $data['value'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'datasets' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Power BI reports.
     *
     * @param string $groupId Workspace ID
     * @param string $filter  OData filter expression
     * @param int    $top     Number of reports to return
     * @param int    $skip    Number of reports to skip
     *
     * @return array{
     *     success: bool,
     *     reports: array<int, array{
     *         id: string,
     *         name: string,
     *         webUrl: string,
     *         embedUrl: string,
     *         datasetId: string,
     *         reportType: string,
     *         isOwnedByMe: bool,
     *         isPublished: bool,
     *         appId: string,
     *         description: string,
     *         modifiedBy: string,
     *         modifiedDateTime: string,
     *         createdBy: string,
     *         createdDateTime: string,
     *     }>,
     *     error: string,
     * }
     */
    public function getReports(
        string $groupId = '',
        string $filter = '',
        int $top = 100,
        int $skip = 0,
    ): array {
        try {
            $params = [
                '$top' => max(1, min($top, 5000)),
                '$skip' => max(0, $skip),
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $url = $groupId ? "{$this->baseUrl}/groups/{$groupId}/reports" : "{$this->baseUrl}/reports";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'reports' => array_map(fn ($report) => [
                    'id' => $report['id'],
                    'name' => $report['name'],
                    'webUrl' => $report['webUrl'],
                    'embedUrl' => $report['embedUrl'],
                    'datasetId' => $report['datasetId'],
                    'reportType' => $report['reportType'],
                    'isOwnedByMe' => $report['isOwnedByMe'] ?? false,
                    'isPublished' => $report['isPublished'] ?? false,
                    'appId' => $report['appId'] ?? '',
                    'description' => $report['description'] ?? '',
                    'modifiedBy' => $report['modifiedBy'] ?? '',
                    'modifiedDateTime' => $report['modifiedDateTime'] ?? '',
                    'createdBy' => $report['createdBy'] ?? '',
                    'createdDateTime' => $report['createdDateTime'] ?? '',
                ], $data['value'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'reports' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Power BI dashboards.
     *
     * @param string $groupId Workspace ID
     * @param string $filter  OData filter expression
     * @param int    $top     Number of dashboards to return
     * @param int    $skip    Number of dashboards to skip
     *
     * @return array{
     *     success: bool,
     *     dashboards: array<int, array{
     *         id: string,
     *         displayName: string,
     *         webUrl: string,
     *         embedUrl: string,
     *         isReadOnly: bool,
     *         isOnDedicatedCapacity: bool,
     *         capacityId: string,
     *         appId: string,
     *         description: string,
     *         modifiedBy: string,
     *         modifiedDateTime: string,
     *         createdBy: string,
     *         createdDateTime: string,
     *         users: array<int, array{
     *             displayName: string,
     *             emailAddress: string,
     *             identifier: string,
     *             graphId: string,
     *             principalType: string,
     *             accessRight: string,
     *         }>,
     *         tiles: array<int, array{
     *             id: string,
     *             title: string,
     *             rowSpan: int,
     *             colSpan: int,
     *             embedUrl: string,
     *             embedData: string,
     *             reportId: string,
     *             datasetId: string,
     *         }>,
     *     }>,
     *     error: string,
     * }
     */
    public function getDashboards(
        string $groupId = '',
        string $filter = '',
        int $top = 100,
        int $skip = 0,
    ): array {
        try {
            $params = [
                '$top' => max(1, min($top, 5000)),
                '$skip' => max(0, $skip),
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $url = $groupId ? "{$this->baseUrl}/groups/{$groupId}/dashboards" : "{$this->baseUrl}/dashboards";

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'dashboards' => array_map(fn ($dashboard) => [
                    'id' => $dashboard['id'],
                    'displayName' => $dashboard['displayName'],
                    'webUrl' => $dashboard['webUrl'],
                    'embedUrl' => $dashboard['embedUrl'],
                    'isReadOnly' => $dashboard['isReadOnly'] ?? false,
                    'isOnDedicatedCapacity' => $dashboard['isOnDedicatedCapacity'] ?? false,
                    'capacityId' => $dashboard['capacityId'] ?? '',
                    'appId' => $dashboard['appId'] ?? '',
                    'description' => $dashboard['description'] ?? '',
                    'modifiedBy' => $dashboard['modifiedBy'] ?? '',
                    'modifiedDateTime' => $dashboard['modifiedDateTime'] ?? '',
                    'createdBy' => $dashboard['createdBy'] ?? '',
                    'createdDateTime' => $dashboard['createdDateTime'] ?? '',
                    'users' => array_map(fn ($user) => [
                        'displayName' => $user['displayName'],
                        'emailAddress' => $user['emailAddress'],
                        'identifier' => $user['identifier'],
                        'graphId' => $user['graphId'],
                        'principalType' => $user['principalType'],
                        'accessRight' => $user['accessRight'],
                    ], $dashboard['users'] ?? []),
                    'tiles' => array_map(fn ($tile) => [
                        'id' => $tile['id'],
                        'title' => $tile['title'],
                        'rowSpan' => $tile['rowSpan'],
                        'colSpan' => $tile['colSpan'],
                        'embedUrl' => $tile['embedUrl'],
                        'embedData' => $tile['embedData'] ?? '',
                        'reportId' => $tile['reportId'] ?? '',
                        'datasetId' => $tile['datasetId'] ?? '',
                    ], $dashboard['tiles'] ?? []),
                ], $data['value'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'dashboards' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh Power BI dataset.
     *
     * @param string $datasetId    Dataset ID
     * @param string $groupId      Workspace ID
     * @param string $notifyOption Notification option (MailOnFailure, MailOnCompletion, NoNotification)
     *
     * @return array{
     *     success: bool,
     *     refreshId: string,
     *     requestId: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function refreshDataset(
        string $datasetId,
        string $groupId = '',
        string $notifyOption = 'NoNotification',
    ): array {
        try {
            $requestData = [
                'notifyOption' => $notifyOption,
            ];

            $url = $groupId ? "{$this->baseUrl}/groups/{$groupId}/datasets/{$datasetId}/refreshes" : "{$this->baseUrl}/datasets/{$datasetId}/refreshes";

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'refreshId' => $data['id'] ?? '',
                'requestId' => $data['requestId'] ?? '',
                'message' => 'Dataset refresh initiated successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'refreshId' => '',
                'requestId' => '',
                'message' => 'Failed to initiate dataset refresh',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Power BI dataset.
     *
     * @param string $name Dataset name
     * @param array<int, array{
     *     name: string,
     *     columns: array<int, array{
     *         name: string,
     *         dataType: string,
     *     }>,
     *     measures: array<int, array{
     *         name: string,
     *         expression: string,
     *     }>,
     * }> $tables Dataset tables
     * @param string $groupId     Workspace ID
     * @param string $defaultMode Default mode (Push, Streaming, AsOnPrem)
     *
     * @return array{
     *     success: bool,
     *     datasetId: string,
     *     name: string,
     *     webUrl: string,
     *     isRefreshable: bool,
     *     addRowsAPIEnabled: bool,
     *     configuredBy: string,
     *     message: string,
     *     error: string,
     * }
     */
    public function createDataset(
        string $name,
        array $tables,
        string $groupId = '',
        string $defaultMode = 'Push',
    ): array {
        try {
            $requestData = [
                'name' => $name,
                'tables' => $tables,
                'defaultMode' => $defaultMode,
            ];

            $url = $groupId ? "{$this->baseUrl}/groups/{$groupId}/datasets" : "{$this->baseUrl}/datasets";

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'datasetId' => $data['id'],
                'name' => $data['name'],
                'webUrl' => $data['webUrl'],
                'isRefreshable' => $data['isRefreshable'] ?? false,
                'addRowsAPIEnabled' => $data['addRowsAPIEnabled'] ?? false,
                'configuredBy' => $data['configuredBy'] ?? '',
                'message' => 'Dataset created successfully',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'datasetId' => '',
                'name' => $name,
                'webUrl' => '',
                'isRefreshable' => false,
                'addRowsAPIEnabled' => false,
                'configuredBy' => '',
                'message' => 'Failed to create dataset',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute Power BI query.
     *
     * @param string $datasetId Dataset ID
     * @param string $query     DAX query
     * @param string $groupId   Workspace ID
     *
     * @return array{
     *     success: bool,
     *     results: array<int, array<string, mixed>>,
     *     columns: array<int, array{
     *         name: string,
     *         dataType: string,
     *     }>,
     *     error: string,
     * }
     */
    public function executeQuery(
        string $datasetId,
        string $query,
        string $groupId = '',
    ): array {
        try {
            $requestData = [
                'query' => $query,
            ];

            $url = $groupId ? "{$this->baseUrl}/groups/{$groupId}/datasets/{$datasetId}/executeQueries" : "{$this->baseUrl}/datasets/{$datasetId}/executeQueries";

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'results' => $data['results'] ?? [],
                'columns' => array_map(fn ($column) => [
                    'name' => $column['name'],
                    'dataType' => $column['dataType'],
                ], $data['columns'] ?? []),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'results' => [],
                'columns' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Power BI embed token.
     *
     * @param string               $reportId          Report ID
     * @param string               $datasetId         Dataset ID
     * @param string               $groupId           Workspace ID
     * @param array<string, mixed> $identities        User identities
     * @param array<string, mixed> $effectiveIdentity Effective identity
     *
     * @return array{
     *     success: bool,
     *     token: string,
     *     tokenId: string,
     *         expiration: string,
     *     accessLevel: string,
     *     error: string,
     * }
     */
    public function getEmbedToken(
        string $reportId,
        string $datasetId,
        string $groupId = '',
        array $identities = [],
        array $effectiveIdentity = [],
    ): array {
        try {
            $requestData = [
                'reports' => [
                    [
                        'reportId' => $reportId,
                        'datasetId' => $datasetId,
                    ],
                ],
            ];

            if (!empty($identities)) {
                $requestData['identities'] = $identities;
            }

            if (!empty($effectiveIdentity)) {
                $requestData['effectiveIdentity'] = $effectiveIdentity;
            }

            $url = $groupId ? "{$this->baseUrl}/groups/{$groupId}/reports/{$reportId}/GenerateToken" : "{$this->baseUrl}/reports/{$reportId}/GenerateToken";

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();

            return [
                'success' => true,
                'token' => $data['token'] ?? '',
                'tokenId' => $data['tokenId'] ?? '',
                'expiration' => $data['expiration'] ?? '',
                'accessLevel' => $data['accessLevel'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'token' => '',
                'tokenId' => '',
                'expiration' => '',
                'accessLevel' => '',
                'error' => $e->getMessage(),
            ];
        }
    }
}
