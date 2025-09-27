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
#[AsTool('grafana_get_dashboards', 'Tool that gets Grafana dashboards')]
#[AsTool('grafana_get_datasources', 'Tool that gets Grafana datasources', method: 'getDatasources')]
#[AsTool('grafana_get_alerts', 'Tool that gets Grafana alerts', method: 'getAlerts')]
#[AsTool('grafana_get_users', 'Tool that gets Grafana users', method: 'getUsers')]
#[AsTool('grafana_get_teams', 'Tool that gets Grafana teams', method: 'getTeams')]
#[AsTool('grafana_get_annotations', 'Tool that gets Grafana annotations', method: 'getAnnotations')]
final readonly class Grafana
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
     * Get Grafana dashboards.
     *
     * @param string $query    Search query
     * @param string $tag      Tag filter
     * @param string $type     Dashboard type (dash-db, dash-folder)
     * @param string $folderId Folder ID filter
     * @param string $starred  Starred filter (true, false)
     * @param int    $limit    Number of dashboards to retrieve
     * @param int    $page     Page number
     *
     * @return array<int, array{
     *     id: int,
     *     uid: string,
     *     title: string,
     *     url: string,
     *     type: string,
     *     tags: array<int, string>,
     *     isStarred: bool,
     *     folderId: int,
     *     folderUid: string,
     *     folderTitle: string,
     *     folderUrl: string,
     *     uri: string,
     *     url: string,
     *     slug: string,
     *     version: int,
     *     hasAcl: bool,
     *     canEdit: bool,
     *     canAdmin: bool,
     *     canSave: bool,
     *     canStar: bool,
     *     canDelete: bool,
     *     created: string,
     *     updated: string,
     *     updatedBy: string,
     *     updatedByAvatar: string,
     *     version: int,
     *     hasAcl: bool,
     *     isFolder: bool,
     *     parentId: int,
     *     parentUid: string,
     *     parentTitle: string,
     *     parentUrl: string,
     * }>
     */
    public function __invoke(
        string $query = '',
        string $tag = '',
        string $type = '',
        string $folderId = '',
        string $starred = '',
        int $limit = 100,
        int $page = 1,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 1000),
                'page' => max($page, 1),
            ];

            if ($query) {
                $params['query'] = $query;
            }
            if ($tag) {
                $params['tag'] = $tag;
            }
            if ($type) {
                $params['type'] = $type;
            }
            if ($folderId) {
                $params['folderId'] = $folderId;
            }
            if ($starred) {
                $params['starred'] = $starred;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/search", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($dashboard) => [
                'id' => $dashboard['id'],
                'uid' => $dashboard['uid'],
                'title' => $dashboard['title'],
                'url' => $dashboard['url'],
                'type' => $dashboard['type'],
                'tags' => $dashboard['tags'] ?? [],
                'isStarred' => $dashboard['isStarred'] ?? false,
                'folderId' => $dashboard['folderId'] ?? 0,
                'folderUid' => $dashboard['folderUid'] ?? '',
                'folderTitle' => $dashboard['folderTitle'] ?? '',
                'folderUrl' => $dashboard['folderUrl'] ?? '',
                'uri' => $dashboard['uri'] ?? '',
                'slug' => $dashboard['slug'] ?? '',
                'version' => $dashboard['version'] ?? 1,
                'hasAcl' => $dashboard['hasAcl'] ?? false,
                'canEdit' => $dashboard['canEdit'] ?? false,
                'canAdmin' => $dashboard['canAdmin'] ?? false,
                'canSave' => $dashboard['canSave'] ?? false,
                'canStar' => $dashboard['canStar'] ?? false,
                'canDelete' => $dashboard['canDelete'] ?? false,
                'created' => $dashboard['created'] ?? '',
                'updated' => $dashboard['updated'] ?? '',
                'updatedBy' => $dashboard['updatedBy'] ?? '',
                'updatedByAvatar' => $dashboard['updatedByAvatar'] ?? '',
                'isFolder' => $dashboard['isFolder'] ?? false,
                'parentId' => $dashboard['parentId'] ?? 0,
                'parentUid' => $dashboard['parentUid'] ?? '',
                'parentTitle' => $dashboard['parentTitle'] ?? '',
                'parentUrl' => $dashboard['parentUrl'] ?? '',
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Grafana datasources.
     *
     * @return array<int, array{
     *     id: int,
     *     uid: string,
     *     orgId: int,
     *     name: string,
     *     type: string,
     *     typeName: string,
     *     typeLogoUrl: string,
     *     access: string,
     *     url: string,
     *     password: string,
     *     user: string,
     *     database: string,
     *     basicAuth: bool,
     *     basicAuthUser: string,
     *     basicAuthPassword: string,
     *     withCredentials: bool,
     *     isDefault: bool,
     *     jsonData: array<string, mixed>,
     *     secureJsonData: array<string, mixed>,
     *     secureJsonFields: array<string, mixed>,
     *     readOnly: bool,
     *     version: int,
     * }>
     */
    public function getDatasources(): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/datasources", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($datasource) => [
                'id' => $datasource['id'],
                'uid' => $datasource['uid'],
                'orgId' => $datasource['orgId'],
                'name' => $datasource['name'],
                'type' => $datasource['type'],
                'typeName' => $datasource['typeName'],
                'typeLogoUrl' => $datasource['typeLogoUrl'],
                'access' => $datasource['access'],
                'url' => $datasource['url'],
                'password' => $datasource['password'] ?? '',
                'user' => $datasource['user'] ?? '',
                'database' => $datasource['database'] ?? '',
                'basicAuth' => $datasource['basicAuth'] ?? false,
                'basicAuthUser' => $datasource['basicAuthUser'] ?? '',
                'basicAuthPassword' => $datasource['basicAuthPassword'] ?? '',
                'withCredentials' => $datasource['withCredentials'] ?? false,
                'isDefault' => $datasource['isDefault'] ?? false,
                'jsonData' => $datasource['jsonData'] ?? [],
                'secureJsonData' => $datasource['secureJsonData'] ?? [],
                'secureJsonFields' => $datasource['secureJsonFields'] ?? [],
                'readOnly' => $datasource['readOnly'] ?? false,
                'version' => $datasource['version'] ?? 1,
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Grafana alerts.
     *
     * @param string $query       Search query
     * @param string $state       Alert state (alerting, ok, no_data, pending)
     * @param string $folderId    Folder ID filter
     * @param string $dashboardId Dashboard ID filter
     * @param string $panelId     Panel ID filter
     * @param int    $limit       Number of alerts to retrieve
     * @param int    $page        Page number
     *
     * @return array<int, array{
     *     id: int,
     *     uid: string,
     *     title: string,
     *     state: string,
     *     newStateDate: string,
     *     prevStateDate: string,
     *     newState: string,
     *     prevState: string,
     *     text: string,
     *     data: array<string, mixed>,
     *     executionError: string,
     *     evalData: array<string, mixed>,
     *     evalDate: string,
     *     dashboardId: int,
     *     dashboardUid: string,
     *     dashboardSlug: string,
     *     dashboardTitle: string,
     *     panelId: int,
     *     panelTitle: string,
     *     orgId: int,
     *     ruleId: int,
     *     ruleName: string,
     *     ruleUrl: string,
     *     ruleState: string,
     *     ruleHealth: string,
     *     ruleType: string,
     *     ruleGroupName: string,
     *     ruleGroupIndex: int,
     *     ruleGroupUid: string,
     *     ruleGroupFolderUid: string,
     *     ruleGroupFolderTitle: string,
     *     ruleGroupFolderUrl: string,
     *     ruleGroupUrl: string,
     *     ruleUrl: string,
     *     annotations: array<string, string>,
     *     labels: array<string, string>,
     *     values: array<string, mixed>,
     *     valueString: string,
     *     imageUrl: string,
     *     imagePublicUrl: string,
     *     imageOnEmbedUrl: string,
     *     imagePublicOnEmbedUrl: string,
     *     needsAck: bool,
     *     shouldRemoveImage: bool,
     *     isRegion: bool,
     *     url: string,
     * }>
     */
    public function getAlerts(
        string $query = '',
        string $state = '',
        string $folderId = '',
        string $dashboardId = '',
        string $panelId = '',
        int $limit = 100,
        int $page = 1,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 1000),
                'page' => max($page, 1),
            ];

            if ($query) {
                $params['query'] = $query;
            }
            if ($state) {
                $params['state'] = $state;
            }
            if ($folderId) {
                $params['folderId'] = $folderId;
            }
            if ($dashboardId) {
                $params['dashboardId'] = $dashboardId;
            }
            if ($panelId) {
                $params['panelId'] = $panelId;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/alerts", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
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
                'uid' => $alert['uid'],
                'title' => $alert['title'],
                'state' => $alert['state'],
                'newStateDate' => $alert['newStateDate'],
                'prevStateDate' => $alert['prevStateDate'],
                'newState' => $alert['newState'],
                'prevState' => $alert['prevState'],
                'text' => $alert['text'],
                'data' => $alert['data'] ?? [],
                'executionError' => $alert['executionError'] ?? '',
                'evalData' => $alert['evalData'] ?? [],
                'evalDate' => $alert['evalDate'] ?? '',
                'dashboardId' => $alert['dashboardId'],
                'dashboardUid' => $alert['dashboardUid'],
                'dashboardSlug' => $alert['dashboardSlug'],
                'dashboardTitle' => $alert['dashboardTitle'],
                'panelId' => $alert['panelId'],
                'panelTitle' => $alert['panelTitle'],
                'orgId' => $alert['orgId'],
                'ruleId' => $alert['ruleId'],
                'ruleName' => $alert['ruleName'],
                'ruleUrl' => $alert['ruleUrl'],
                'ruleState' => $alert['ruleState'],
                'ruleHealth' => $alert['ruleHealth'],
                'ruleType' => $alert['ruleType'],
                'ruleGroupName' => $alert['ruleGroupName'],
                'ruleGroupIndex' => $alert['ruleGroupIndex'],
                'ruleGroupUid' => $alert['ruleGroupUid'],
                'ruleGroupFolderUid' => $alert['ruleGroupFolderUid'],
                'ruleGroupFolderTitle' => $alert['ruleGroupFolderTitle'],
                'ruleGroupFolderUrl' => $alert['ruleGroupFolderUrl'],
                'ruleGroupUrl' => $alert['ruleGroupUrl'],
                'annotations' => $alert['annotations'] ?? [],
                'labels' => $alert['labels'] ?? [],
                'values' => $alert['values'] ?? [],
                'valueString' => $alert['valueString'] ?? '',
                'imageUrl' => $alert['imageUrl'] ?? '',
                'imagePublicUrl' => $alert['imagePublicUrl'] ?? '',
                'imageOnEmbedUrl' => $alert['imageOnEmbedUrl'] ?? '',
                'imagePublicOnEmbedUrl' => $alert['imagePublicOnEmbedUrl'] ?? '',
                'needsAck' => $alert['needsAck'] ?? false,
                'shouldRemoveImage' => $alert['shouldRemoveImage'] ?? false,
                'isRegion' => $alert['isRegion'] ?? false,
                'url' => $alert['url'] ?? '',
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Grafana users.
     *
     * @param int    $perPage Number of users per page
     * @param int    $page    Page number
     * @param string $query   Search query
     *
     * @return array<int, array{
     *     id: int,
     *     email: string,
     *     name: string,
     *     login: string,
     *     theme: string,
     *     orgId: int,
     *     isGrafanaAdmin: bool,
     *     isDisabled: bool,
     *     isExternal: bool,
     *     updatedAt: string,
     *     createdAt: string,
     *     lastSeenAt: string,
     *     lastSeenAtAge: string,
     *     authLabels: array<int, string>,
     *     isDisabled: bool,
     *     isExternal: bool,
     *     helpFlags1: int,
     *     hasSeenAnnouncement: bool,
     *     teams: array<int, array{
     *         id: int,
     *         orgId: int,
     *         name: string,
     *         email: string,
     *         avatarUrl: string,
     *         memberCount: int,
     *         permission: int,
     *         accessControl: array<string, bool>,
     *         hasAcl: bool,
     *         url: string,
     *         slug: string,
     *         created: string,
     *         updated: string,
     *     }>,
     *     orgs: array<int, array{
     *         orgId: int,
     *         name: string,
     *         role: string,
     *     }>,
     * }>
     */
    public function getUsers(
        int $perPage = 50,
        int $page = 1,
        string $query = '',
    ): array {
        try {
            $params = [
                'perpage' => min(max($perPage, 1), 1000),
                'page' => max($page, 1),
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/users", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($user) => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'login' => $user['login'],
                'theme' => $user['theme'] ?? '',
                'orgId' => $user['orgId'],
                'isGrafanaAdmin' => $user['isGrafanaAdmin'] ?? false,
                'isDisabled' => $user['isDisabled'] ?? false,
                'isExternal' => $user['isExternal'] ?? false,
                'updatedAt' => $user['updatedAt'],
                'createdAt' => $user['createdAt'],
                'lastSeenAt' => $user['lastSeenAt'] ?? '',
                'lastSeenAtAge' => $user['lastSeenAtAge'] ?? '',
                'authLabels' => $user['authLabels'] ?? [],
                'helpFlags1' => $user['helpFlags1'] ?? 0,
                'hasSeenAnnouncement' => $user['hasSeenAnnouncement'] ?? false,
                'teams' => array_map(fn ($team) => [
                    'id' => $team['id'],
                    'orgId' => $team['orgId'],
                    'name' => $team['name'],
                    'email' => $team['email'],
                    'avatarUrl' => $team['avatarUrl'],
                    'memberCount' => $team['memberCount'],
                    'permission' => $team['permission'],
                    'accessControl' => $team['accessControl'] ?? [],
                    'hasAcl' => $team['hasAcl'] ?? false,
                    'url' => $team['url'],
                    'slug' => $team['slug'],
                    'created' => $team['created'],
                    'updated' => $team['updated'],
                ], $user['teams'] ?? []),
                'orgs' => array_map(fn ($org) => [
                    'orgId' => $org['orgId'],
                    'name' => $org['name'],
                    'role' => $org['role'],
                ], $user['orgs'] ?? []),
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Grafana teams.
     *
     * @param int    $perPage Number of teams per page
     * @param int    $page    Page number
     * @param string $query   Search query
     *
     * @return array<int, array{
     *     id: int,
     *     orgId: int,
     *     name: string,
     *     email: string,
     *     avatarUrl: string,
     *     memberCount: int,
     *     permission: int,
     *     accessControl: array<string, bool>,
     *     hasAcl: bool,
     *     url: string,
     *     slug: string,
     *     created: string,
     *     updated: string,
     * }>
     */
    public function getTeams(
        int $perPage = 50,
        int $page = 1,
        string $query = '',
    ): array {
        try {
            $params = [
                'perpage' => min(max($perPage, 1), 1000),
                'page' => max($page, 1),
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/teams/search", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($team) => [
                'id' => $team['id'],
                'orgId' => $team['orgId'],
                'name' => $team['name'],
                'email' => $team['email'],
                'avatarUrl' => $team['avatarUrl'],
                'memberCount' => $team['memberCount'],
                'permission' => $team['permission'],
                'accessControl' => $team['accessControl'] ?? [],
                'hasAcl' => $team['hasAcl'] ?? false,
                'url' => $team['url'],
                'slug' => $team['slug'],
                'created' => $team['created'],
                'updated' => $team['updated'],
            ], $data['teams'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Grafana annotations.
     *
     * @param string $from        Start time (Unix timestamp)
     * @param string $to          End time (Unix timestamp)
     * @param int    $limit       Number of annotations to retrieve
     * @param string $alertId     Alert ID filter
     * @param string $dashboardId Dashboard ID filter
     * @param string $panelId     Panel ID filter
     * @param string $userId      User ID filter
     * @param string $type        Annotation type (alert, annotation)
     * @param string $tags        Comma-separated tags
     *
     * @return array<int, array{
     *     id: int,
     *     alertId: int,
     *     alertName: string,
     *     dashboardId: int,
     *     dashboardUid: string,
     *     dashboardSlug: string,
     *     dashboardTitle: string,
     *     panelId: int,
     *     panelTitle: string,
     *     userId: int,
     *     userName: string,
     *     newState: string,
     *     prevState: string,
     *     time: int,
     *     timeEnd: int,
     *     text: string,
     *     tags: array<int, string>,
     *     login: string,
     *     email: string,
     *     avatarUrl: string,
     *     data: array<string, mixed>,
     *     regionId: int,
     *     type: string,
     *     title: string,
     *     description: string,
     *     created: int,
     *     updated: int,
     *     updatedBy: int,
     *     updatedByLogin: string,
     *     updatedByEmail: string,
     *     updatedByAvatar: string,
     *     isRegion: bool,
     *     url: string,
     * }>
     */
    public function getAnnotations(
        string $from,
        string $to,
        int $limit = 100,
        string $alertId = '',
        string $dashboardId = '',
        string $panelId = '',
        string $userId = '',
        string $type = '',
        string $tags = '',
    ): array {
        try {
            $params = [
                'from' => $from,
                'to' => $to,
                'limit' => min(max($limit, 1), 1000),
            ];

            if ($alertId) {
                $params['alertId'] = $alertId;
            }
            if ($dashboardId) {
                $params['dashboardId'] = $dashboardId;
            }
            if ($panelId) {
                $params['panelId'] = $panelId;
            }
            if ($userId) {
                $params['userId'] = $userId;
            }
            if ($type) {
                $params['type'] = $type;
            }
            if ($tags) {
                $params['tags'] = $tags;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/annotations", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($annotation) => [
                'id' => $annotation['id'],
                'alertId' => $annotation['alertId'] ?? 0,
                'alertName' => $annotation['alertName'] ?? '',
                'dashboardId' => $annotation['dashboardId'] ?? 0,
                'dashboardUid' => $annotation['dashboardUid'] ?? '',
                'dashboardSlug' => $annotation['dashboardSlug'] ?? '',
                'dashboardTitle' => $annotation['dashboardTitle'] ?? '',
                'panelId' => $annotation['panelId'] ?? 0,
                'panelTitle' => $annotation['panelTitle'] ?? '',
                'userId' => $annotation['userId'] ?? 0,
                'userName' => $annotation['userName'] ?? '',
                'newState' => $annotation['newState'] ?? '',
                'prevState' => $annotation['prevState'] ?? '',
                'time' => $annotation['time'],
                'timeEnd' => $annotation['timeEnd'] ?? 0,
                'text' => $annotation['text'],
                'tags' => $annotation['tags'] ?? [],
                'login' => $annotation['login'] ?? '',
                'email' => $annotation['email'] ?? '',
                'avatarUrl' => $annotation['avatarUrl'] ?? '',
                'data' => $annotation['data'] ?? [],
                'regionId' => $annotation['regionId'] ?? 0,
                'type' => $annotation['type'] ?? 'annotation',
                'title' => $annotation['title'] ?? '',
                'description' => $annotation['description'] ?? '',
                'created' => $annotation['created'] ?? 0,
                'updated' => $annotation['updated'] ?? 0,
                'updatedBy' => $annotation['updatedBy'] ?? 0,
                'updatedByLogin' => $annotation['updatedByLogin'] ?? '',
                'updatedByEmail' => $annotation['updatedByEmail'] ?? '',
                'updatedByAvatar' => $annotation['updatedByAvatar'] ?? '',
                'isRegion' => $annotation['isRegion'] ?? false,
                'url' => $annotation['url'] ?? '',
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
