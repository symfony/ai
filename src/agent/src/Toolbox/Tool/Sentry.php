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
#[AsTool('sentry_get_projects', 'Tool that gets Sentry projects')]
#[AsTool('sentry_get_issues', 'Tool that gets Sentry issues', method: 'getIssues')]
#[AsTool('sentry_get_events', 'Tool that gets Sentry events', method: 'getEvents')]
#[AsTool('sentry_get_releases', 'Tool that gets Sentry releases', method: 'getReleases')]
#[AsTool('sentry_get_teams', 'Tool that gets Sentry teams', method: 'getTeams')]
#[AsTool('sentry_get_organizations', 'Tool that gets Sentry organizations', method: 'getOrganizations')]
final readonly class Sentry
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiToken,
        private string $organizationSlug,
        private string $apiVersion = '0',
        private array $options = [],
    ) {
    }

    /**
     * Get Sentry projects.
     *
     * @param string $name    Project name filter
     * @param int    $perPage Number of projects per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     platform: string,
     *     dateCreated: string,
     *     features: array<int, string>,
     *     firstEvent: string|null,
     *     hasAccess: bool,
     *     isBookmarked: bool,
     *     isInternal: bool,
     *     isMember: bool,
     *     isPublic: bool,
     *     team: array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *     },
     *     teams: array<int, array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *     }>,
     *     organization: array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *     },
     *     status: string,
     *     stats: array<int, array{0: string, 1: int}>,
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
                $params['query'] = $name;
            }

            $response = $this->httpClient->request('GET', "https://sentry.io/api/{$this->apiVersion}/organizations/{$this->organizationSlug}/projects/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['detail'])) {
                return [];
            }

            return array_map(fn ($project) => [
                'id' => $project['id'],
                'name' => $project['name'],
                'slug' => $project['slug'],
                'platform' => $project['platform'],
                'dateCreated' => $project['dateCreated'],
                'features' => $project['features'] ?? [],
                'firstEvent' => $project['firstEvent'],
                'hasAccess' => $project['hasAccess'] ?? true,
                'isBookmarked' => $project['isBookmarked'] ?? false,
                'isInternal' => $project['isInternal'] ?? false,
                'isMember' => $project['isMember'] ?? true,
                'isPublic' => $project['isPublic'] ?? false,
                'team' => [
                    'id' => $project['team']['id'],
                    'name' => $project['team']['name'],
                    'slug' => $project['team']['slug'],
                ],
                'teams' => array_map(fn ($team) => [
                    'id' => $team['id'],
                    'name' => $team['name'],
                    'slug' => $team['slug'],
                ], $project['teams'] ?? []),
                'organization' => [
                    'id' => $project['organization']['id'],
                    'name' => $project['organization']['name'],
                    'slug' => $project['organization']['slug'],
                ],
                'status' => $project['status'] ?? 'active',
                'stats' => $project['stats'] ?? [],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Sentry issues.
     *
     * @param string $projectSlug Project slug
     * @param string $status      Issue status (unresolved, resolved, ignored)
     * @param string $assigned    Assigned user filter
     * @param string $bookmarked  Bookmarked filter (true, false)
     * @param string $subscribed  Subscribed filter (true, false)
     * @param int    $perPage     Number of issues per page
     * @param int    $page        Page number
     * @param string $query       Search query
     *
     * @return array<int, array{
     *     id: string,
     *     shareId: string,
     *     shortId: string,
     *     title: string,
     *     culprit: string,
     *     permalink: string,
     *     logger: string|null,
     *     level: string,
     *     status: string,
     *     statusDetails: array<string, mixed>,
     *     isPublic: bool,
     *     platform: string,
     *     project: array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *         platform: string,
     *     },
     *     type: string,
     *     metadata: array{
     *         type: string,
     *         value: string,
     *         filename: string|null,
     *         function: string|null,
     *     },
     *     numComments: int,
     *     assignedTo: array{
     *         id: string,
     *         name: string,
     *         username: string,
     *         email: string,
     *         avatarUrl: string,
     *         isActive: bool,
     *         hasPasswordAuth: bool,
     *         isManaged: bool,
     *         dateJoined: string,
     *         lastLogin: string|null,
     *         isStaff: bool,
     *         isSuperuser: bool,
     *         isAuthenticated: bool,
     *         isAnonymous: bool,
     *         isActiveStaff: bool,
     *         isSuperuser: bool,
     *         flags: array<string, mixed>,
     *         identities: array<int, mixed>,
     *         emails: array<int, mixed>,
     *         avatar: array{
     *             avatarType: string,
     *             avatarUuid: string|null,
     *             avatarUrl: string,
     *         },
     *         has2fa: bool,
     *         lastActive: string,
     *         isSuperuser: bool,
     *         isStaff: bool,
     *         experiments: array<string, mixed>,
     *         permissions: array<int, string>,
     *     }|null,
     *     isBookmarked: bool,
     *     isSubscribed: bool,
     *     isUnhandled: bool,
     *     count: string,
     *     userCount: int,
     *     firstSeen: string,
     *     lastSeen: string,
     *     stats: array{
     *         '24h': array<int, array{0: string, 1: int}>,
     *         '14d': array<int, array{0: string, 1: int}>,
     *     },
     *     issueType: string,
     *     issueCategory: string,
     *     priority: string,
     *     priorityLockedAt: string|null,
     *     hasSeen: bool,
     *     userReportCount: int,
     * }>
     */
    public function getIssues(
        string $projectSlug,
        string $status = 'unresolved',
        string $assigned = '',
        string $bookmarked = '',
        string $subscribed = '',
        int $perPage = 50,
        int $page = 1,
        string $query = '',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'status' => $status,
            ];

            if ($assigned) {
                $params['assigned'] = $assigned;
            }
            if ($bookmarked) {
                $params['bookmarked'] = $bookmarked;
            }
            if ($subscribed) {
                $params['subscribed'] = $subscribed;
            }
            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "https://sentry.io/api/{$this->apiVersion}/projects/{$this->organizationSlug}/{$projectSlug}/issues/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['detail'])) {
                return [];
            }

            return array_map(fn ($issue) => [
                'id' => $issue['id'],
                'shareId' => $issue['shareId'],
                'shortId' => $issue['shortId'],
                'title' => $issue['title'],
                'culprit' => $issue['culprit'],
                'permalink' => $issue['permalink'],
                'logger' => $issue['logger'],
                'level' => $issue['level'],
                'status' => $issue['status'],
                'statusDetails' => $issue['statusDetails'] ?? [],
                'isPublic' => $issue['isPublic'] ?? false,
                'platform' => $issue['platform'],
                'project' => [
                    'id' => $issue['project']['id'],
                    'name' => $issue['project']['name'],
                    'slug' => $issue['project']['slug'],
                    'platform' => $issue['project']['platform'],
                ],
                'type' => $issue['type'],
                'metadata' => [
                    'type' => $issue['metadata']['type'],
                    'value' => $issue['metadata']['value'],
                    'filename' => $issue['metadata']['filename'],
                    'function' => $issue['metadata']['function'],
                ],
                'numComments' => $issue['numComments'] ?? 0,
                'assignedTo' => $issue['assignedTo'] ? [
                    'id' => $issue['assignedTo']['id'],
                    'name' => $issue['assignedTo']['name'],
                    'username' => $issue['assignedTo']['username'],
                    'email' => $issue['assignedTo']['email'],
                    'avatarUrl' => $issue['assignedTo']['avatarUrl'],
                    'isActive' => $issue['assignedTo']['isActive'],
                    'hasPasswordAuth' => $issue['assignedTo']['hasPasswordAuth'] ?? false,
                    'isManaged' => $issue['assignedTo']['isManaged'] ?? false,
                    'dateJoined' => $issue['assignedTo']['dateJoined'],
                    'lastLogin' => $issue['assignedTo']['lastLogin'],
                    'isStaff' => $issue['assignedTo']['isStaff'] ?? false,
                    'isSuperuser' => $issue['assignedTo']['isSuperuser'] ?? false,
                    'isAuthenticated' => $issue['assignedTo']['isAuthenticated'] ?? true,
                    'isAnonymous' => $issue['assignedTo']['isAnonymous'] ?? false,
                    'isActiveStaff' => $issue['assignedTo']['isActiveStaff'] ?? false,
                    'flags' => $issue['assignedTo']['flags'] ?? [],
                    'identities' => $issue['assignedTo']['identities'] ?? [],
                    'emails' => $issue['assignedTo']['emails'] ?? [],
                    'avatar' => [
                        'avatarType' => $issue['assignedTo']['avatar']['avatarType'],
                        'avatarUuid' => $issue['assignedTo']['avatar']['avatarUuid'],
                        'avatarUrl' => $issue['assignedTo']['avatar']['avatarUrl'],
                    ],
                    'has2fa' => $issue['assignedTo']['has2fa'] ?? false,
                    'lastActive' => $issue['assignedTo']['lastActive'],
                    'experiments' => $issue['assignedTo']['experiments'] ?? [],
                    'permissions' => $issue['assignedTo']['permissions'] ?? [],
                ] : null,
                'isBookmarked' => $issue['isBookmarked'] ?? false,
                'isSubscribed' => $issue['isSubscribed'] ?? false,
                'isUnhandled' => $issue['isUnhandled'] ?? false,
                'count' => $issue['count'],
                'userCount' => $issue['userCount'],
                'firstSeen' => $issue['firstSeen'],
                'lastSeen' => $issue['lastSeen'],
                'stats' => [
                    '24h' => $issue['stats']['24h'] ?? [],
                    '14d' => $issue['stats']['14d'] ?? [],
                ],
                'issueType' => $issue['issueType'] ?? 'error',
                'issueCategory' => $issue['issueCategory'] ?? 'error',
                'priority' => $issue['priority'] ?? 'medium',
                'priorityLockedAt' => $issue['priorityLockedAt'],
                'hasSeen' => $issue['hasSeen'] ?? false,
                'userReportCount' => $issue['userReportCount'] ?? 0,
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Sentry events.
     *
     * @param string $projectSlug Project slug
     * @param string $issueId     Issue ID
     * @param int    $perPage     Number of events per page
     * @param int    $page        Page number
     *
     * @return array<int, array{
     *     id: string,
     *     eventID: string,
     *     dist: string|null,
     *     message: string,
     *     title: string,
     *     culprit: string,
     *     dateCreated: string,
     *     dateReceived: string,
     *     platform: string,
     *     type: string,
     *     tags: array<int, array{key: string, value: string}>,
     *     user: array{
     *         id: string|null,
     *         username: string|null,
     *         email: string|null,
     *         ip_address: string|null,
     *         data: array<string, mixed>,
     *     }|null,
     *     contexts: array<string, mixed>,
     *     sdk: array{
     *         name: string,
     *         version: string,
     *         packages: array<int, array{name: string, version: string}>,
     *         integrations: array<int, string>,
     *     },
     *     groupID: string,
     *     fingerprints: array<int, string>,
     *     metadata: array{
     *         type: string,
     *         value: string,
     *         filename: string|null,
     *         function: string|null,
     *     },
     *     size: int,
     *     entries: array<int, array{
     *         type: string,
     *         data: array<string, mixed>,
     *     }>,
     *     packages: array<string, string>,
     *     release: array{
     *         version: string,
     *         shortVersion: string,
     *         versionInfo: array{
     *             version: array{
     *                 raw: string,
     *                 major: int,
     *                 minor: int,
     *                 patch: int,
     *                 pre: string|null,
     *                 buildCode: string|null,
     *                 buildNumber: int|null,
     *             },
     *         },
     *         projects: array<int, array{
     *             id: string,
     *             name: string,
     *             slug: string,
     *             newGroups: int,
     *             platform: string,
     *             platforms: array<int, string>,
     *         }>,
     *         dateCreated: string,
     *         dateReleased: string|null,
     *         dateStarted: string|null,
     *         dateFinished: string|null,
     *         data: array<string, mixed>,
     *         lastEvent: string|null,
     *         firstEvent: string|null,
     *         lastCommit: array{
     *             id: string,
     *             repository: array{
     *                 id: string,
     *                 name: string,
     *                 url: string,
     *                 provider: array{
     *                     id: string,
     *                     name: string,
     *                 },
     *             },
     *             shortId: string,
     *             title: string,
     *             authorName: string,
     *             authorEmail: string,
     *             message: string,
     *             dateCreated: string,
     *         }|null,
     *         newGroups: int,
     *         owner: array{
     *             id: string,
     *             name: string,
     *             type: string,
     *         }|null,
     *         ref: string|null,
     *         url: string|null,
     *         version: string,
     *         shortVersion: string,
     *         versionInfo: array{
     *             version: array{
     *                 raw: string,
     *                 major: int,
     *                 minor: int,
     *                 patch: int,
     *                 pre: string|null,
     *                 buildCode: string|null,
     *                 buildNumber: int|null,
     *             },
     *         },
     *         projects: array<int, array{
     *             id: string,
     *             name: string,
     *             slug: string,
     *             newGroups: int,
     *             platform: string,
     *             platforms: array<int, string>,
     *         }>,
     *         dateCreated: string,
     *         dateReleased: string|null,
     *         dateStarted: string|null,
     *         dateFinished: string|null,
     *         data: array<string, mixed>,
     *         lastEvent: string|null,
     *         firstEvent: string|null,
     *         lastCommit: array{
     *             id: string,
     *             repository: array{
     *                 id: string,
     *                 name: string,
     *                 url: string,
     *                 provider: array{
     *                     id: string,
     *                     name: string,
     *                 },
     *             },
     *             shortId: string,
     *             title: string,
     *             authorName: string,
     *             authorEmail: string,
     *             message: string,
     *             dateCreated: string,
     *         }|null,
     *         newGroups: int,
     *         owner: array{
     *             id: string,
     *             name: string,
     *             type: string,
     *         }|null,
     *         ref: string|null,
     *         url: string|null,
     *     }|null,
     * }>
     */
    public function getEvents(
        string $projectSlug,
        string $issueId,
        int $perPage = 50,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            $response = $this->httpClient->request('GET', "https://sentry.io/api/{$this->apiVersion}/projects/{$this->organizationSlug}/{$projectSlug}/issues/{$issueId}/events/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['detail'])) {
                return [];
            }

            return array_map(fn ($event) => [
                'id' => $event['id'],
                'eventID' => $event['eventID'],
                'dist' => $event['dist'],
                'message' => $event['message'],
                'title' => $event['title'],
                'culprit' => $event['culprit'],
                'dateCreated' => $event['dateCreated'],
                'dateReceived' => $event['dateReceived'],
                'platform' => $event['platform'],
                'type' => $event['type'],
                'tags' => array_map(fn ($tag) => [
                    'key' => $tag['key'],
                    'value' => $tag['value'],
                ], $event['tags'] ?? []),
                'user' => $event['user'] ? [
                    'id' => $event['user']['id'],
                    'username' => $event['user']['username'],
                    'email' => $event['user']['email'],
                    'ip_address' => $event['user']['ip_address'],
                    'data' => $event['user']['data'] ?? [],
                ] : null,
                'contexts' => $event['contexts'] ?? [],
                'sdk' => [
                    'name' => $event['sdk']['name'],
                    'version' => $event['sdk']['version'],
                    'packages' => array_map(fn ($package) => [
                        'name' => $package['name'],
                        'version' => $package['version'],
                    ], $event['sdk']['packages'] ?? []),
                    'integrations' => $event['sdk']['integrations'] ?? [],
                ],
                'groupID' => $event['groupID'],
                'fingerprints' => $event['fingerprints'] ?? [],
                'metadata' => [
                    'type' => $event['metadata']['type'],
                    'value' => $event['metadata']['value'],
                    'filename' => $event['metadata']['filename'],
                    'function' => $event['metadata']['function'],
                ],
                'size' => $event['size'],
                'entries' => array_map(fn ($entry) => [
                    'type' => $entry['type'],
                    'data' => $entry['data'],
                ], $event['entries'] ?? []),
                'packages' => $event['packages'] ?? [],
                'release' => $event['release'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Sentry releases.
     *
     * @param string $projectSlug Project slug
     * @param int    $perPage     Number of releases per page
     * @param int    $page        Page number
     * @param string $query       Search query
     *
     * @return array<int, array{
     *     version: string,
     *     shortVersion: string,
     *     versionInfo: array{
     *         version: array{
     *             raw: string,
     *             major: int,
     *             minor: int,
     *             patch: int,
     *             pre: string|null,
     *             buildCode: string|null,
     *             buildNumber: int|null,
     *         },
     *     },
     *     projects: array<int, array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *         newGroups: int,
     *         platform: string,
     *         platforms: array<int, string>,
     *     }>,
     *     dateCreated: string,
     *     dateReleased: string|null,
     *     dateStarted: string|null,
     *     dateFinished: string|null,
     *     data: array<string, mixed>,
     *     lastEvent: string|null,
     *     firstEvent: string|null,
     *     lastCommit: array{
     *         id: string,
     *         repository: array{
     *             id: string,
     *             name: string,
     *             url: string,
     *             provider: array{
     *                 id: string,
     *                 name: string,
     *             },
     *         },
     *         shortId: string,
     *         title: string,
     *         authorName: string,
     *         authorEmail: string,
     *         message: string,
     *         dateCreated: string,
     *     }|null,
     *     newGroups: int,
     *     owner: array{
     *         id: string,
     *         name: string,
     *         type: string,
     *     }|null,
     *     ref: string|null,
     *     url: string|null,
     * }>
     */
    public function getReleases(
        string $projectSlug,
        int $perPage = 50,
        int $page = 1,
        string $query = '',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "https://sentry.io/api/{$this->apiVersion}/projects/{$this->organizationSlug}/{$projectSlug}/releases/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['detail'])) {
                return [];
            }

            return array_map(fn ($release) => [
                'version' => $release['version'],
                'shortVersion' => $release['shortVersion'],
                'versionInfo' => [
                    'version' => [
                        'raw' => $release['versionInfo']['version']['raw'],
                        'major' => $release['versionInfo']['version']['major'],
                        'minor' => $release['versionInfo']['version']['minor'],
                        'patch' => $release['versionInfo']['version']['patch'],
                        'pre' => $release['versionInfo']['version']['pre'],
                        'buildCode' => $release['versionInfo']['version']['buildCode'],
                        'buildNumber' => $release['versionInfo']['version']['buildNumber'],
                    ],
                ],
                'projects' => array_map(fn ($project) => [
                    'id' => $project['id'],
                    'name' => $project['name'],
                    'slug' => $project['slug'],
                    'newGroups' => $project['newGroups'],
                    'platform' => $project['platform'],
                    'platforms' => $project['platforms'] ?? [],
                ], $release['projects'] ?? []),
                'dateCreated' => $release['dateCreated'],
                'dateReleased' => $release['dateReleased'],
                'dateStarted' => $release['dateStarted'],
                'dateFinished' => $release['dateFinished'],
                'data' => $release['data'] ?? [],
                'lastEvent' => $release['lastEvent'],
                'firstEvent' => $release['firstEvent'],
                'lastCommit' => $release['lastCommit'],
                'newGroups' => $release['newGroups'] ?? 0,
                'owner' => $release['owner'],
                'ref' => $release['ref'],
                'url' => $release['url'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Sentry teams.
     *
     * @param int    $perPage Number of teams per page
     * @param int    $page    Page number
     * @param string $query   Search query
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     isMember: bool,
     *     isPending: bool,
     *     memberCount: int,
     *     hasAccess: bool,
     *     isAccessGranted: bool,
     *     isMemberIdpProvisioned: bool,
     *     flags: array<string, mixed>,
     *     dateCreated: string,
     *     organization: array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *     },
     * }>
     */
    public function getTeams(
        int $perPage = 50,
        int $page = 1,
        string $query = '',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "https://sentry.io/api/{$this->apiVersion}/organizations/{$this->organizationSlug}/teams/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['detail'])) {
                return [];
            }

            return array_map(fn ($team) => [
                'id' => $team['id'],
                'name' => $team['name'],
                'slug' => $team['slug'],
                'isMember' => $team['isMember'] ?? false,
                'isPending' => $team['isPending'] ?? false,
                'memberCount' => $team['memberCount'] ?? 0,
                'hasAccess' => $team['hasAccess'] ?? true,
                'isAccessGranted' => $team['isAccessGranted'] ?? true,
                'isMemberIdpProvisioned' => $team['isMemberIdpProvisioned'] ?? false,
                'flags' => $team['flags'] ?? [],
                'dateCreated' => $team['dateCreated'],
                'organization' => [
                    'id' => $team['organization']['id'],
                    'name' => $team['organization']['name'],
                    'slug' => $team['organization']['slug'],
                ],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Sentry organizations.
     *
     * @param int    $perPage Number of organizations per page
     * @param int    $page    Page number
     * @param string $query   Search query
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     dateCreated: string,
     *     isEarlyAdopter: bool,
     *     require2FA: bool,
     *     avatar: array{
     *         avatarType: string,
     *         avatarUuid: string|null,
     *         avatarUrl: string,
     *     },
     *     features: array<int, string>,
     *     links: array<string, mixed>,
     *     isDefault: bool,
     *     isUnclaimed: bool,
     *     onboardingTasks: array<int, mixed>,
     *     experiments: array<string, mixed>,
     *     alertSettings: array<string, mixed>,
     *     scrubIPAddresses: bool,
     *     scrubData: bool,
     *     sensitiveFields: array<int, string>,
     *     safeFields: array<int, string>,
     *     storeCrashReports: bool,
     *     attachments: array{
     *         minidump: bool,
     *     },
     *     dataScrubber: bool,
     *     dataScrubberDefaults: bool,
     *     debugFiles: array{
     *         bundleId: string,
     *         checksums: array<int, string>,
     *         dateCreated: string,
     *         debugId: string,
     *         objectName: string,
     *         sha1: string,
     *         symbolType: string,
     *         uuid: string,
     *     }|null,
     *     scrapeJavaScript: bool,
     *     allowJoinRequests: bool,
     *     enhancedPrivacy: bool,
     *     isDynamicallySampled: bool,
     *     openMembership: bool,
     *     pendingAccessRequests: int,
     *     quota: array{
     *         maxRate: int,
     *         maxRateInterval: int,
     *         accountLimit: int,
     *         projectLimit: int,
     *     },
     *     trustedRelays: array<int, string>,
     *     orgRole: string,
     *     role: string,
     *     projects: array<int, mixed>,
     *     teams: array<int, mixed>,
     * }>
     */
    public function getOrganizations(
        int $perPage = 50,
        int $page = 1,
        string $query = '',
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            if ($query) {
                $params['query'] = $query;
            }

            $response = $this->httpClient->request('GET', "https://sentry.io/api/{$this->apiVersion}/organizations/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['detail'])) {
                return [];
            }

            return array_map(fn ($org) => [
                'id' => $org['id'],
                'name' => $org['name'],
                'slug' => $org['slug'],
                'dateCreated' => $org['dateCreated'],
                'isEarlyAdopter' => $org['isEarlyAdopter'] ?? false,
                'require2FA' => $org['require2FA'] ?? false,
                'avatar' => [
                    'avatarType' => $org['avatar']['avatarType'],
                    'avatarUuid' => $org['avatar']['avatarUuid'],
                    'avatarUrl' => $org['avatar']['avatarUrl'],
                ],
                'features' => $org['features'] ?? [],
                'links' => $org['links'] ?? [],
                'isDefault' => $org['isDefault'] ?? false,
                'isUnclaimed' => $org['isUnclaimed'] ?? false,
                'onboardingTasks' => $org['onboardingTasks'] ?? [],
                'experiments' => $org['experiments'] ?? [],
                'alertSettings' => $org['alertSettings'] ?? [],
                'scrubIPAddresses' => $org['scrubIPAddresses'] ?? false,
                'scrubData' => $org['scrubData'] ?? false,
                'sensitiveFields' => $org['sensitiveFields'] ?? [],
                'safeFields' => $org['safeFields'] ?? [],
                'storeCrashReports' => $org['storeCrashReports'] ?? false,
                'attachments' => [
                    'minidump' => $org['attachments']['minidump'] ?? false,
                ],
                'dataScrubber' => $org['dataScrubber'] ?? false,
                'dataScrubberDefaults' => $org['dataScrubberDefaults'] ?? false,
                'debugFiles' => $org['debugFiles'],
                'scrapeJavaScript' => $org['scrapeJavaScript'] ?? false,
                'allowJoinRequests' => $org['allowJoinRequests'] ?? false,
                'enhancedPrivacy' => $org['enhancedPrivacy'] ?? false,
                'isDynamicallySampled' => $org['isDynamicallySampled'] ?? false,
                'openMembership' => $org['openMembership'] ?? false,
                'pendingAccessRequests' => $org['pendingAccessRequests'] ?? 0,
                'quota' => [
                    'maxRate' => $org['quota']['maxRate'],
                    'maxRateInterval' => $org['quota']['maxRateInterval'],
                    'accountLimit' => $org['quota']['accountLimit'],
                    'projectLimit' => $org['quota']['projectLimit'],
                ],
                'trustedRelays' => $org['trustedRelays'] ?? [],
                'orgRole' => $org['orgRole'] ?? 'member',
                'role' => $org['role'] ?? 'member',
                'projects' => $org['projects'] ?? [],
                'teams' => $org['teams'] ?? [],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
