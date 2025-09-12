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
#[AsTool('jenkins_get_jobs', 'Tool that gets Jenkins jobs')]
#[AsTool('jenkins_get_builds', 'Tool that gets Jenkins builds', method: 'getBuilds')]
#[AsTool('jenkins_trigger_build', 'Tool that triggers Jenkins builds', method: 'triggerBuild')]
#[AsTool('jenkins_get_build_logs', 'Tool that gets Jenkins build logs', method: 'getBuildLogs')]
#[AsTool('jenkins_get_nodes', 'Tool that gets Jenkins nodes', method: 'getNodes')]
#[AsTool('jenkins_get_plugins', 'Tool that gets Jenkins plugins', method: 'getPlugins')]
final readonly class Jenkins
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $username,
        #[\SensitiveParameter] private string $apiToken,
        private string $baseUrl,
        private array $options = [],
    ) {
    }

    /**
     * Get Jenkins jobs.
     *
     * @param string $folder Folder path (optional)
     * @param int    $depth  Depth level (1 = jobs only, 2 = jobs + folders)
     *
     * @return array<int, array{
     *     _class: string,
     *     name: string,
     *     url: string,
     *     color: string,
     *     description: string,
     *     displayName: string,
     *     displayNameOrNull: string,
     *     fullDisplayName: string,
     *     fullName: string,
     *     buildable: bool,
     *     builds: array<int, array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }>,
     *     firstBuild: array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }|null,
     *     lastBuild: array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }|null,
     *     lastCompletedBuild: array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }|null,
     *     lastFailedBuild: array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }|null,
     *     lastStableBuild: array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }|null,
     *     lastSuccessfulBuild: array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }|null,
     *     lastUnstableBuild: array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }|null,
     *     lastUnsuccessfulBuild: array{
     *         _class: string,
     *         number: int,
     *         url: string,
     *     }|null,
     *     nextBuildNumber: int,
     *     inQueue: bool,
     *     keepDependencies: bool,
     *     concurrentBuild: bool,
     *     resumeBlocked: bool,
     *     disabled: bool,
     *     upstreamProjects: array<int, array{
     *         _class: string,
     *         name: string,
     *         url: string,
     *         color: string,
     *     }>,
     *     downstreamProjects: array<int, array{
     *         _class: string,
     *         name: string,
     *         url: string,
     *         color: string,
     *     }>,
     * }>
     */
    public function __invoke(
        string $folder = '',
        int $depth = 1,
    ): array {
        try {
            $params = [
                'depth' => $depth,
            ];

            $url = $folder
                ? "{$this->baseUrl}/job/{$folder}/api/json"
                : "{$this->baseUrl}/api/json";

            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->apiToken) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->apiToken);
            }

            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            $jobs = $folder ? [$data] : ($data['jobs'] ?? []);

            return array_map(fn ($job) => [
                '_class' => $job['_class'] ?? '',
                'name' => $job['name'] ?? '',
                'url' => $job['url'] ?? '',
                'color' => $job['color'] ?? 'notbuilt',
                'description' => $job['description'] ?? '',
                'displayName' => $job['displayName'] ?? '',
                'displayNameOrNull' => $job['displayNameOrNull'] ?? '',
                'fullDisplayName' => $job['fullDisplayName'] ?? '',
                'fullName' => $job['fullName'] ?? '',
                'buildable' => $job['buildable'] ?? false,
                'builds' => array_map(fn ($build) => [
                    '_class' => $build['_class'],
                    'number' => $build['number'],
                    'url' => $build['url'],
                ], $job['builds'] ?? []),
                'firstBuild' => $job['firstBuild'] ? [
                    '_class' => $job['firstBuild']['_class'],
                    'number' => $job['firstBuild']['number'],
                    'url' => $job['firstBuild']['url'],
                ] : null,
                'lastBuild' => $job['lastBuild'] ? [
                    '_class' => $job['lastBuild']['_class'],
                    'number' => $job['lastBuild']['number'],
                    'url' => $job['lastBuild']['url'],
                ] : null,
                'lastCompletedBuild' => $job['lastCompletedBuild'] ? [
                    '_class' => $job['lastCompletedBuild']['_class'],
                    'number' => $job['lastCompletedBuild']['number'],
                    'url' => $job['lastCompletedBuild']['url'],
                ] : null,
                'lastFailedBuild' => $job['lastFailedBuild'] ? [
                    '_class' => $job['lastFailedBuild']['_class'],
                    'number' => $job['lastFailedBuild']['number'],
                    'url' => $job['lastFailedBuild']['url'],
                ] : null,
                'lastStableBuild' => $job['lastStableBuild'] ? [
                    '_class' => $job['lastStableBuild']['_class'],
                    'number' => $job['lastStableBuild']['number'],
                    'url' => $job['lastStableBuild']['url'],
                ] : null,
                'lastSuccessfulBuild' => $job['lastSuccessfulBuild'] ? [
                    '_class' => $job['lastSuccessfulBuild']['_class'],
                    'number' => $job['lastSuccessfulBuild']['number'],
                    'url' => $job['lastSuccessfulBuild']['url'],
                ] : null,
                'lastUnstableBuild' => $job['lastUnstableBuild'] ? [
                    '_class' => $job['lastUnstableBuild']['_class'],
                    'number' => $job['lastUnstableBuild']['number'],
                    'url' => $job['lastUnstableBuild']['url'],
                ] : null,
                'lastUnsuccessfulBuild' => $job['lastUnsuccessfulBuild'] ? [
                    '_class' => $job['lastUnsuccessfulBuild']['_class'],
                    'number' => $job['lastUnsuccessfulBuild']['number'],
                    'url' => $job['lastUnsuccessfulBuild']['url'],
                ] : null,
                'nextBuildNumber' => $job['nextBuildNumber'] ?? 1,
                'inQueue' => $job['inQueue'] ?? false,
                'keepDependencies' => $job['keepDependencies'] ?? false,
                'concurrentBuild' => $job['concurrentBuild'] ?? false,
                'resumeBlocked' => $job['resumeBlocked'] ?? false,
                'disabled' => $job['disabled'] ?? false,
                'upstreamProjects' => array_map(fn ($project) => [
                    '_class' => $project['_class'],
                    'name' => $project['name'],
                    'url' => $project['url'],
                    'color' => $project['color'],
                ], $job['upstreamProjects'] ?? []),
                'downstreamProjects' => array_map(fn ($project) => [
                    '_class' => $project['_class'],
                    'name' => $project['name'],
                    'url' => $project['url'],
                    'color' => $project['color'],
                ], $job['downstreamProjects'] ?? []),
            ], $jobs);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Jenkins builds.
     *
     * @param string $jobName Job name
     * @param int    $limit   Number of builds to retrieve
     *
     * @return array<int, array{
     *     _class: string,
     *     number: int,
     *     url: string,
     *     displayName: string,
     *     fullDisplayName: string,
     *     description: string,
     *     result: string|null,
     *     duration: int,
     *     estimatedDuration: int,
     *     building: bool,
     *     timestamp: int,
     *     changeSet: array{
     *         _class: string,
     *         items: array<int, array{
     *             _class: string,
     *             affectedPaths: array<int, string>,
     *             commitId: string,
     *             timestamp: int,
     *             msg: string,
     *             author: array{
     *                 absoluteUrl: string,
     *                 fullName: string,
     *             },
     *             authorEmail: string,
     *             comment: string,
     *             date: string,
     *             id: string,
     *             msg: string,
     *             paths: array<int, array{
     *                 editType: string,
     *                 file: string,
     *             }>,
     *             revision: int,
     *             user: string,
     *         }>,
     *         kind: string,
     *         revisions: array<int, array{
     *             module: string,
     *             revision: int,
     *         }>,
     *     },
     *     actions: array<int, array<string, mixed>>,
     *     artifacts: array<int, array{
     *         displayPath: string,
     *         fileName: string,
     *         relativePath: string,
     *     }>,
     *     building: bool,
     *     description: string,
     *     displayName: string,
     *     duration: int,
     *     estimatedDuration: int,
     *     executor: string|null,
     *     fullDisplayName: string,
     *     id: string,
     *     keepLog: bool,
     *     number: int,
     *     queueId: int,
     *     result: string|null,
     *     timestamp: int,
     *     url: string,
     *     builtOn: string,
     *     changeSet: array{
     *         _class: string,
     *         items: array<int, mixed>,
     *         kind: string,
     *         revisions: array<int, mixed>,
     *     },
     *     culprits: array<int, array{
     *         absoluteUrl: string,
     *         fullName: string,
     *     }>,
     * }>
     */
    public function getBuilds(
        string $jobName,
        int $limit = 10,
    ): array {
        try {
            $params = [
                'tree' => 'builds[number,url,displayName,result,duration,building,timestamp,changeSet[*]]',
            ];

            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->apiToken) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->apiToken);
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/job/{$jobName}/api/json", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            $builds = \array_slice($data['builds'] ?? [], 0, $limit);

            return array_map(fn ($build) => [
                '_class' => $build['_class'] ?? '',
                'number' => $build['number'],
                'url' => $build['url'],
                'displayName' => $build['displayName'] ?? '',
                'fullDisplayName' => $build['fullDisplayName'] ?? '',
                'description' => $build['description'] ?? '',
                'result' => $build['result'],
                'duration' => $build['duration'] ?? 0,
                'estimatedDuration' => $build['estimatedDuration'] ?? 0,
                'building' => $build['building'] ?? false,
                'timestamp' => $build['timestamp'] ?? 0,
                'changeSet' => [
                    '_class' => $build['changeSet']['_class'] ?? '',
                    'items' => array_map(fn ($item) => [
                        '_class' => $item['_class'] ?? '',
                        'affectedPaths' => $item['affectedPaths'] ?? [],
                        'commitId' => $item['commitId'] ?? '',
                        'timestamp' => $item['timestamp'] ?? 0,
                        'msg' => $item['msg'] ?? '',
                        'author' => [
                            'absoluteUrl' => $item['author']['absoluteUrl'] ?? '',
                            'fullName' => $item['author']['fullName'] ?? '',
                        ],
                        'authorEmail' => $item['authorEmail'] ?? '',
                        'comment' => $item['comment'] ?? '',
                        'date' => $item['date'] ?? '',
                        'id' => $item['id'] ?? '',
                        'paths' => array_map(fn ($path) => [
                            'editType' => $path['editType'] ?? '',
                            'file' => $path['file'] ?? '',
                        ], $item['paths'] ?? []),
                        'revision' => $item['revision'] ?? 0,
                        'user' => $item['user'] ?? '',
                    ], $build['changeSet']['items'] ?? []),
                    'kind' => $build['changeSet']['kind'] ?? '',
                    'revisions' => array_map(fn ($revision) => [
                        'module' => $revision['module'] ?? '',
                        'revision' => $revision['revision'] ?? 0,
                    ], $build['changeSet']['revisions'] ?? []),
                ],
                'actions' => $build['actions'] ?? [],
                'artifacts' => array_map(fn ($artifact) => [
                    'displayPath' => $artifact['displayPath'] ?? '',
                    'fileName' => $artifact['fileName'] ?? '',
                    'relativePath' => $artifact['relativePath'] ?? '',
                ], $build['artifacts'] ?? []),
                'executor' => $build['executor'] ?? null,
                'id' => $build['id'] ?? '',
                'keepLog' => $build['keepLog'] ?? false,
                'queueId' => $build['queueId'] ?? 0,
                'builtOn' => $build['builtOn'] ?? '',
                'culprits' => array_map(fn ($culprit) => [
                    'absoluteUrl' => $culprit['absoluteUrl'] ?? '',
                    'fullName' => $culprit['fullName'] ?? '',
                ], $build['culprits'] ?? []),
            ], $builds);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Trigger Jenkins build.
     *
     * @param string                $jobName    Job name
     * @param array<string, string> $parameters Build parameters
     */
    public function triggerBuild(
        string $jobName,
        array $parameters = [],
    ): string {
        try {
            $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
            if ($this->username && $this->apiToken) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->apiToken);
            }

            $body = '';
            if (!empty($parameters)) {
                $body = http_build_query(['json' => json_encode(['parameter' => array_map(fn ($key, $value) => ['name' => $key, 'value' => $value], array_keys($parameters), $parameters)])]);
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/job/{$jobName}/build", [
                'headers' => $headers,
                'body' => $body,
            ]);

            if (201 === $response->getStatusCode() || 200 === $response->getStatusCode()) {
                return 'Build triggered successfully';
            }

            return 'Error triggering build: HTTP '.$response->getStatusCode();
        } catch (\Exception $e) {
            return 'Error triggering build: '.$e->getMessage();
        }
    }

    /**
     * Get Jenkins build logs.
     *
     * @param string $jobName     Job name
     * @param int    $buildNumber Build number
     * @param int    $start       Start line number
     * @param bool   $progressive Progressive output
     *
     * @return array{
     *     logs: string,
     *     truncated: bool,
     *     moreData: bool,
     * }|string
     */
    public function getBuildLogs(
        string $jobName,
        int $buildNumber,
        int $start = 0,
        bool $progressive = true,
    ): array|string {
        try {
            $params = [
                'start' => $start,
            ];

            if ($progressive) {
                $params['progressive'] = 'true';
            }

            $headers = ['Content-Type' => 'text/plain'];
            if ($this->username && $this->apiToken) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->apiToken);
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/job/{$jobName}/{$buildNumber}/consoleText", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            if (200 === $response->getStatusCode()) {
                $logs = $response->getContent();

                return [
                    'logs' => $logs,
                    'truncated' => false,
                    'moreData' => false,
                ];
            }

            return 'Error getting build logs: HTTP '.$response->getStatusCode();
        } catch (\Exception $e) {
            return 'Error getting build logs: '.$e->getMessage();
        }
    }

    /**
     * Get Jenkins nodes.
     *
     * @return array<int, array{
     *     _class: string,
     *     displayName: string,
     *     executors: array<int, array{
     *         currentExecutable: array{
     *             _class: string,
     *             number: int,
     *             url: string,
     *         }|null,
     *         idle: bool,
     *         likelyStuck: bool,
     *         number: int,
     *     }>,
     *     icon: string,
     *     iconClassName: string,
     *     idle: bool,
     *     jnlpAgent: bool,
     *     launchSupported: bool,
     *     manualLaunchAllowed: bool,
     *     monitorData: array{
     *         'hudson.node_monitors.ArchitectureMonitor': string,
     *         'hudson.node_monitors.ClockMonitor': array{
     *             diff: int,
     *         },
     *         'hudson.node_monitors.DiskSpaceMonitor': array{
     *             path: string,
     *             size: int,
     *             timestamp: int,
     *         },
     *         'hudson.node_monitors.ResponseTimeMonitor': array{
     *             average: int,
     *             timestamp: int,
     *         },
     *         'hudson.node_monitors.SwapSpaceMonitor': array{
     *             availablePhysicalMemory: int,
     *             availableSwapSpace: int,
     *             totalPhysicalMemory: int,
     *             totalSwapSpace: int,
     *         },
     *         'hudson.node_monitors.TemporarySpaceMonitor': array{
     *             path: string,
     *             size: int,
     *             timestamp: int,
     *         },
     *     },
     *     numExecutors: int,
     *     offline: bool,
     *     offlineCause: array{
     *         _class: string,
     *         description: string,
     *     }|null,
     *     offlineCauseReason: string,
     *     temporarilyOffline: bool,
     *     absoluteRemotePath: string,
     *     description: string,
     *     labelString: string,
     *     mode: string,
     *     nodeDescription: string,
     *     nodeName: string,
     *     nodeProperties: array{
     *         _class: string,
     *     },
     *     slaveAgentPort: int,
     *     url: string,
     * }>
     */
    public function getNodes(): array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->apiToken) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->apiToken);
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/computer/api/json", [
                'headers' => $headers,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($node) => [
                '_class' => $node['_class'] ?? '',
                'displayName' => $node['displayName'] ?? '',
                'executors' => array_map(fn ($executor) => [
                    'currentExecutable' => $executor['currentExecutable'] ? [
                        '_class' => $executor['currentExecutable']['_class'],
                        'number' => $executor['currentExecutable']['number'],
                        'url' => $executor['currentExecutable']['url'],
                    ] : null,
                    'idle' => $executor['idle'] ?? false,
                    'likelyStuck' => $executor['likelyStuck'] ?? false,
                    'number' => $executor['number'] ?? 0,
                ], $node['executors'] ?? []),
                'icon' => $node['icon'] ?? '',
                'iconClassName' => $node['iconClassName'] ?? '',
                'idle' => $node['idle'] ?? false,
                'jnlpAgent' => $node['jnlpAgent'] ?? false,
                'launchSupported' => $node['launchSupported'] ?? false,
                'manualLaunchAllowed' => $node['manualLaunchAllowed'] ?? false,
                'monitorData' => [
                    'hudson.node_monitors.ArchitectureMonitor' => $node['monitorData']['hudson.node_monitors.ArchitectureMonitor'] ?? '',
                    'hudson.node_monitors.ClockMonitor' => [
                        'diff' => $node['monitorData']['hudson.node_monitors.ClockMonitor']['diff'] ?? 0,
                    ],
                    'hudson.node_monitors.DiskSpaceMonitor' => [
                        'path' => $node['monitorData']['hudson.node_monitors.DiskSpaceMonitor']['path'] ?? '',
                        'size' => $node['monitorData']['hudson.node_monitors.DiskSpaceMonitor']['size'] ?? 0,
                        'timestamp' => $node['monitorData']['hudson.node_monitors.DiskSpaceMonitor']['timestamp'] ?? 0,
                    ],
                    'hudson.node_monitors.ResponseTimeMonitor' => [
                        'average' => $node['monitorData']['hudson.node_monitors.ResponseTimeMonitor']['average'] ?? 0,
                        'timestamp' => $node['monitorData']['hudson.node_monitors.ResponseTimeMonitor']['timestamp'] ?? 0,
                    ],
                    'hudson.node_monitors.SwapSpaceMonitor' => [
                        'availablePhysicalMemory' => $node['monitorData']['hudson.node_monitors.SwapSpaceMonitor']['availablePhysicalMemory'] ?? 0,
                        'availableSwapSpace' => $node['monitorData']['hudson.node_monitors.SwapSpaceMonitor']['availableSwapSpace'] ?? 0,
                        'totalPhysicalMemory' => $node['monitorData']['hudson.node_monitors.SwapSpaceMonitor']['totalPhysicalMemory'] ?? 0,
                        'totalSwapSpace' => $node['monitorData']['hudson.node_monitors.SwapSpaceMonitor']['totalSwapSpace'] ?? 0,
                    ],
                    'hudson.node_monitors.TemporarySpaceMonitor' => [
                        'path' => $node['monitorData']['hudson.node_monitors.TemporarySpaceMonitor']['path'] ?? '',
                        'size' => $node['monitorData']['hudson.node_monitors.TemporarySpaceMonitor']['size'] ?? 0,
                        'timestamp' => $node['monitorData']['hudson.node_monitors.TemporarySpaceMonitor']['timestamp'] ?? 0,
                    ],
                ],
                'numExecutors' => $node['numExecutors'] ?? 0,
                'offline' => $node['offline'] ?? false,
                'offlineCause' => $node['offlineCause'] ? [
                    '_class' => $node['offlineCause']['_class'],
                    'description' => $node['offlineCause']['description'],
                ] : null,
                'offlineCauseReason' => $node['offlineCauseReason'] ?? '',
                'temporarilyOffline' => $node['temporarilyOffline'] ?? false,
                'absoluteRemotePath' => $node['absoluteRemotePath'] ?? '',
                'description' => $node['description'] ?? '',
                'labelString' => $node['labelString'] ?? '',
                'mode' => $node['mode'] ?? 'NORMAL',
                'nodeDescription' => $node['nodeDescription'] ?? '',
                'nodeName' => $node['nodeName'] ?? '',
                'nodeProperties' => [
                    '_class' => $node['nodeProperties']['_class'] ?? '',
                ],
                'slaveAgentPort' => $node['slaveAgentPort'] ?? 0,
                'url' => $node['url'] ?? '',
            ], $data['computer'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Jenkins plugins.
     *
     * @return array<int, array{
     *     active: bool,
     *     backupVersion: string|null,
     *     bundled: bool,
     *     deleted: bool,
     *     dependencies: array<int, array{
     *         name: string,
     *         optional: bool,
     *         title: string,
     *         version: string,
     *     }>,
     *     downgradable: bool,
     *     enabled: bool,
     *     hasUpdate: bool,
     *     longName: string,
     *     pinned: bool,
     *     requiredDependency: bool,
     *     shortName: string,
     *     supportsDynamicLoad: string,
     *     url: string,
     *     version: string,
     * }>
     */
    public function getPlugins(): array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($this->username && $this->apiToken) {
                $headers['Authorization'] = 'Basic '.base64_encode($this->username.':'.$this->apiToken);
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/pluginManager/api/json", [
                'headers' => $headers,
                'query' => ['depth' => 1],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($plugin) => [
                'active' => $plugin['active'] ?? false,
                'backupVersion' => $plugin['backupVersion'] ?? null,
                'bundled' => $plugin['bundled'] ?? false,
                'deleted' => $plugin['deleted'] ?? false,
                'dependencies' => array_map(fn ($dep) => [
                    'name' => $dep['name'],
                    'optional' => $dep['optional'] ?? false,
                    'title' => $dep['title'] ?? '',
                    'version' => $dep['version'],
                ], $plugin['dependencies'] ?? []),
                'downgradable' => $plugin['downgradable'] ?? false,
                'enabled' => $plugin['enabled'] ?? false,
                'hasUpdate' => $plugin['hasUpdate'] ?? false,
                'longName' => $plugin['longName'] ?? '',
                'pinned' => $plugin['pinned'] ?? false,
                'requiredDependency' => $plugin['requiredDependency'] ?? false,
                'shortName' => $plugin['shortName'],
                'supportsDynamicLoad' => $plugin['supportsDynamicLoad'] ?? 'MAYBE',
                'url' => $plugin['url'] ?? '',
                'version' => $plugin['version'],
            ], $data['plugins'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }
}
