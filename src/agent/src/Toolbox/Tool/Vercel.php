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
#[AsTool('vercel_get_deployments', 'Tool that gets Vercel deployments')]
#[AsTool('vercel_create_deployment', 'Tool that creates Vercel deployments', method: 'createDeployment')]
#[AsTool('vercel_get_projects', 'Tool that gets Vercel projects', method: 'getProjects')]
#[AsTool('vercel_create_project', 'Tool that creates Vercel projects', method: 'createProject')]
#[AsTool('vercel_get_domains', 'Tool that gets Vercel domains', method: 'getDomains')]
#[AsTool('vercel_get_team_members', 'Tool that gets Vercel team members', method: 'getTeamMembers')]
final readonly class Vercel
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v9',
        private array $options = [],
    ) {
    }

    /**
     * Get Vercel deployments.
     *
     * @param string $projectId Project ID to filter deployments
     * @param string $teamId    Team ID
     * @param int    $limit     Number of deployments to retrieve
     * @param string $since     Since timestamp
     * @param string $until     Until timestamp
     * @param string $state     Deployment state (BUILDING, ERROR, INITIALIZING, QUEUED, READY, CANCELED)
     * @param string $target    Deployment target (production, preview)
     *
     * @return array<int, array{
     *     uid: string,
     *     name: string,
     *     url: string,
     *     created: int,
     *     source: string,
     *     state: string,
     *     type: string,
     *     creator: array{uid: string, username: string, email: string},
     *     inspectorUrl: string,
     *     meta: array<string, mixed>,
     *     target: string,
     *     aliasAssigned: bool,
     *     alias: array<int, string>,
     *     aliasError: array<string, mixed>|null,
     *     aliasWarning: array<string, mixed>|null,
     *     gitSource: array{
     *         type: string,
     *         repo: string,
     *         ref: string,
     *         sha: string,
     *         repoId: int,
     *         org: string,
     *     }|null,
     *     projectId: string,
     *     teamId: string,
     *     build: array{env: array<string, string>},
     *     functions: array<string, mixed>|null,
     *     plan: string,
     *     regions: array<int, string>,
     *     readyState: string,
     *     readySubstate: string|null,
     *     checksState: string,
     *     checksConclusion: string,
     *     framework: string|null,
     *     gitCommitRef: string|null,
     *     gitCommitSha: string|null,
     *     gitCommitMessage: string|null,
     *     gitCommitAuthorLogin: string|null,
     *     gitCommitAuthorName: string|null,
     * }>
     */
    public function __invoke(
        string $projectId = '',
        string $teamId = '',
        int $limit = 20,
        string $since = '',
        string $until = '',
        string $state = '',
        string $target = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            if ($projectId) {
                $params['projectId'] = $projectId;
            }
            if ($teamId) {
                $params['teamId'] = $teamId;
            }
            if ($since) {
                $params['since'] = $since;
            }
            if ($until) {
                $params['until'] = $until;
            }
            if ($state) {
                $params['state'] = $state;
            }
            if ($target) {
                $params['target'] = $target;
            }

            $response = $this->httpClient->request('GET', "https://api.vercel.com/{$this->apiVersion}/deployments", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($deployment) => [
                'uid' => $deployment['uid'],
                'name' => $deployment['name'],
                'url' => $deployment['url'],
                'created' => $deployment['created'],
                'source' => $deployment['source'],
                'state' => $deployment['state'],
                'type' => $deployment['type'],
                'creator' => [
                    'uid' => $deployment['creator']['uid'],
                    'username' => $deployment['creator']['username'],
                    'email' => $deployment['creator']['email'],
                ],
                'inspectorUrl' => $deployment['inspectorUrl'],
                'meta' => $deployment['meta'] ?? [],
                'target' => $deployment['target'],
                'aliasAssigned' => $deployment['aliasAssigned'] ?? false,
                'alias' => $deployment['alias'] ?? [],
                'aliasError' => $deployment['aliasError'],
                'aliasWarning' => $deployment['aliasWarning'],
                'gitSource' => $deployment['gitSource'] ? [
                    'type' => $deployment['gitSource']['type'],
                    'repo' => $deployment['gitSource']['repo'],
                    'ref' => $deployment['gitSource']['ref'],
                    'sha' => $deployment['gitSource']['sha'],
                    'repoId' => $deployment['gitSource']['repoId'],
                    'org' => $deployment['gitSource']['org'],
                ] : null,
                'projectId' => $deployment['projectId'],
                'teamId' => $deployment['teamId'],
                'build' => [
                    'env' => $deployment['build']['env'] ?? [],
                ],
                'functions' => $deployment['functions'],
                'plan' => $deployment['plan'] ?? 'hobby',
                'regions' => $deployment['regions'] ?? [],
                'readyState' => $deployment['readyState'] ?? 'READY',
                'readySubstate' => $deployment['readySubstate'],
                'checksState' => $deployment['checksState'] ?? 'REGISTERED',
                'checksConclusion' => $deployment['checksConclusion'] ?? 'PENDING',
                'framework' => $deployment['framework'],
                'gitCommitRef' => $deployment['gitCommitRef'],
                'gitCommitSha' => $deployment['gitCommitSha'],
                'gitCommitMessage' => $deployment['gitCommitMessage'],
                'gitCommitAuthorLogin' => $deployment['gitCommitAuthorLogin'],
                'gitCommitAuthorName' => $deployment['gitCommitAuthorName'],
            ], $data['deployments'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Vercel deployment.
     *
     * @param string                $name      Deployment name
     * @param string                $gitSource Git source URL
     * @param string                $target    Deployment target (production, preview)
     * @param array<string, string> $env       Environment variables
     * @param string                $projectId Project ID
     * @param string                $teamId    Team ID
     * @param bool                  $withCache Use cache
     *
     * @return array{
     *     uid: string,
     *     name: string,
     *     url: string,
     *     created: int,
     *     source: string,
     *     state: string,
     *     type: string,
     *     creator: array{uid: string, username: string, email: string},
     *     inspectorUrl: string,
     *     target: string,
     *     projectId: string,
     *     teamId: string,
     *     build: array{env: array<string, string>},
     *     plan: string,
     *     readyState: string,
     *     framework: string|null,
     * }|string
     */
    public function createDeployment(
        string $name,
        string $gitSource,
        string $target = 'preview',
        array $env = [],
        string $projectId = '',
        string $teamId = '',
        bool $withCache = true,
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'gitSource' => [
                    'type' => 'github',
                    'repo' => $gitSource,
                    'ref' => 'main',
                ],
                'target' => $target,
                'env' => $env,
                'withCache' => $withCache,
            ];

            if ($projectId) {
                $payload['projectId'] = $projectId;
            }
            if ($teamId) {
                $payload['teamId'] = $teamId;
            }

            $response = $this->httpClient->request('POST', "https://api.vercel.com/{$this->apiVersion}/deployments", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating deployment: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'uid' => $data['uid'],
                'name' => $data['name'],
                'url' => $data['url'],
                'created' => $data['created'],
                'source' => $data['source'],
                'state' => $data['state'],
                'type' => $data['type'],
                'creator' => [
                    'uid' => $data['creator']['uid'],
                    'username' => $data['creator']['username'],
                    'email' => $data['creator']['email'],
                ],
                'inspectorUrl' => $data['inspectorUrl'],
                'target' => $data['target'],
                'projectId' => $data['projectId'],
                'teamId' => $data['teamId'],
                'build' => [
                    'env' => $data['build']['env'] ?? [],
                ],
                'plan' => $data['plan'] ?? 'hobby',
                'readyState' => $data['readyState'] ?? 'READY',
                'framework' => $data['framework'],
            ];
        } catch (\Exception $e) {
            return 'Error creating deployment: '.$e->getMessage();
        }
    }

    /**
     * Get Vercel projects.
     *
     * @param string $teamId Team ID
     * @param int    $limit  Number of projects to retrieve
     * @param string $search Search term
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     accountId: string,
     *     createdAt: int,
     *     updatedAt: int,
     *     alias: array<int, string>,
     *     latestDeployments: array<int, array{
     *         uid: string,
     *         name: string,
     *         url: string,
     *         created: int,
     *         state: string,
     *         type: string,
     *         target: string,
     *         inspectorUrl: string,
     *     }>,
     *     targets: array<string, mixed>,
     *     link: array{type: string, repo: string, repoId: int}|null,
     *     latestDeploymentStatus: string,
     *     gitRepository: array{type: string, repo: string, repoId: int, org: string, path: string}|null,
     *     framework: string|null,
     *     nodeVersion: string,
     *     installCommand: string|null,
     *     buildCommand: string|null,
     *     outputDirectory: string|null,
     *     publicSource: bool,
     *     rootDirectory: string|null,
     * }>
     */
    public function getProjects(
        string $teamId = '',
        int $limit = 20,
        string $search = '',
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            if ($teamId) {
                $params['teamId'] = $teamId;
            }
            if ($search) {
                $params['search'] = $search;
            }

            $response = $this->httpClient->request('GET', "https://api.vercel.com/{$this->apiVersion}/projects", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($project) => [
                'id' => $project['id'],
                'name' => $project['name'],
                'accountId' => $project['accountId'],
                'createdAt' => $project['createdAt'],
                'updatedAt' => $project['updatedAt'],
                'alias' => $project['alias'] ?? [],
                'latestDeployments' => array_map(fn ($deployment) => [
                    'uid' => $deployment['uid'],
                    'name' => $deployment['name'],
                    'url' => $deployment['url'],
                    'created' => $deployment['created'],
                    'state' => $deployment['state'],
                    'type' => $deployment['type'],
                    'target' => $deployment['target'],
                    'inspectorUrl' => $deployment['inspectorUrl'],
                ], $project['latestDeployments'] ?? []),
                'targets' => $project['targets'] ?? [],
                'link' => $project['link'] ? [
                    'type' => $project['link']['type'],
                    'repo' => $project['link']['repo'],
                    'repoId' => $project['link']['repoId'],
                ] : null,
                'latestDeploymentStatus' => $project['latestDeploymentStatus'] ?? 'READY',
                'gitRepository' => $project['gitRepository'] ? [
                    'type' => $project['gitRepository']['type'],
                    'repo' => $project['gitRepository']['repo'],
                    'repoId' => $project['gitRepository']['repoId'],
                    'org' => $project['gitRepository']['org'],
                    'path' => $project['gitRepository']['path'],
                ] : null,
                'framework' => $project['framework'],
                'nodeVersion' => $project['nodeVersion'] ?? '18.x',
                'installCommand' => $project['installCommand'],
                'buildCommand' => $project['buildCommand'],
                'outputDirectory' => $project['outputDirectory'],
                'publicSource' => $project['publicSource'] ?? false,
                'rootDirectory' => $project['rootDirectory'],
            ], $data['projects'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Vercel project.
     *
     * @param string $name            Project name
     * @param string $gitRepository   Git repository URL
     * @param string $framework       Framework (nextjs, nuxtjs, svelte, vue, etc.)
     * @param string $teamId          Team ID
     * @param string $rootDirectory   Root directory
     * @param string $installCommand  Install command
     * @param string $buildCommand    Build command
     * @param string $outputDirectory Output directory
     * @param string $nodeVersion     Node.js version
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     accountId: string,
     *     createdAt: int,
     *     updatedAt: int,
     *     alias: array<int, string>,
     *     targets: array<string, mixed>,
     *     link: array{type: string, repo: string, repoId: int},
     *     latestDeploymentStatus: string,
     *     gitRepository: array{type: string, repo: string, repoId: int, org: string, path: string},
     *     framework: string,
     *     nodeVersion: string,
     *     installCommand: string|null,
     *     buildCommand: string|null,
     *     outputDirectory: string|null,
     *     publicSource: bool,
     *     rootDirectory: string|null,
     * }|string
     */
    public function createProject(
        string $name,
        string $gitRepository,
        string $framework = 'nextjs',
        string $teamId = '',
        string $rootDirectory = '',
        string $installCommand = '',
        string $buildCommand = '',
        string $outputDirectory = '',
        string $nodeVersion = '18.x',
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'gitRepository' => [
                    'type' => 'github',
                    'repo' => $gitRepository,
                ],
                'framework' => $framework,
                'nodeVersion' => $nodeVersion,
                'publicSource' => false,
            ];

            if ($teamId) {
                $payload['teamId'] = $teamId;
            }
            if ($rootDirectory) {
                $payload['rootDirectory'] = $rootDirectory;
            }
            if ($installCommand) {
                $payload['installCommand'] = $installCommand;
            }
            if ($buildCommand) {
                $payload['buildCommand'] = $buildCommand;
            }
            if ($outputDirectory) {
                $payload['outputDirectory'] = $outputDirectory;
            }

            $response = $this->httpClient->request('POST', "https://api.vercel.com/{$this->apiVersion}/projects", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating project: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'accountId' => $data['accountId'],
                'createdAt' => $data['createdAt'],
                'updatedAt' => $data['updatedAt'],
                'alias' => $data['alias'] ?? [],
                'targets' => $data['targets'] ?? [],
                'link' => [
                    'type' => $data['link']['type'],
                    'repo' => $data['link']['repo'],
                    'repoId' => $data['link']['repoId'],
                ],
                'latestDeploymentStatus' => $data['latestDeploymentStatus'] ?? 'READY',
                'gitRepository' => [
                    'type' => $data['gitRepository']['type'],
                    'repo' => $data['gitRepository']['repo'],
                    'repoId' => $data['gitRepository']['repoId'],
                    'org' => $data['gitRepository']['org'],
                    'path' => $data['gitRepository']['path'],
                ],
                'framework' => $data['framework'],
                'nodeVersion' => $data['nodeVersion'],
                'installCommand' => $data['installCommand'],
                'buildCommand' => $data['buildCommand'],
                'outputDirectory' => $data['outputDirectory'],
                'publicSource' => $data['publicSource'] ?? false,
                'rootDirectory' => $data['rootDirectory'],
            ];
        } catch (\Exception $e) {
            return 'Error creating project: '.$e->getMessage();
        }
    }

    /**
     * Get Vercel domains.
     *
     * @param string $teamId Team ID
     * @param int    $limit  Number of domains to retrieve
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     serviceType: string,
     *     verified: bool,
     *     cname: string,
     *     intendedNameservers: array<int, string>,
     *     nameservers: array<int, string>,
     *     creator: array{id: string, email: string, username: string},
     *     createdAt: int,
     *     updatedAt: int,
     *     expiresAt: int|null,
     *     boughtAt: int|null,
     *     transferredAt: int|null,
     *     orderedAt: int|null,
     *     renewalPrice: int|null,
     *     sslStatus: string|null,
     *     aliases: array<int, string>,
     *     projectId: string|null,
     *     target: string|null,
     *     redirect: string|null,
     *     mxRecords: array<int, mixed>,
     *     txtRecords: array<int, mixed>,
     *     aRecords: array<int, mixed>,
     *     aaaaRecords: array<int, mixed>,
     *     cnameRecords: array<int, mixed>,
     * }>
     */
    public function getDomains(
        string $teamId = '',
        int $limit = 20,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            if ($teamId) {
                $params['teamId'] = $teamId;
            }

            $response = $this->httpClient->request('GET', "https://api.vercel.com/{$this->apiVersion}/domains", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($domain) => [
                'id' => $domain['id'],
                'name' => $domain['name'],
                'serviceType' => $domain['serviceType'],
                'verified' => $domain['verified'] ?? false,
                'cname' => $domain['cname'] ?? '',
                'intendedNameservers' => $domain['intendedNameservers'] ?? [],
                'nameservers' => $domain['nameservers'] ?? [],
                'creator' => [
                    'id' => $domain['creator']['id'],
                    'email' => $domain['creator']['email'],
                    'username' => $domain['creator']['username'],
                ],
                'createdAt' => $domain['createdAt'],
                'updatedAt' => $domain['updatedAt'],
                'expiresAt' => $domain['expiresAt'],
                'boughtAt' => $domain['boughtAt'],
                'transferredAt' => $domain['transferredAt'],
                'orderedAt' => $domain['orderedAt'],
                'renewalPrice' => $domain['renewalPrice'],
                'sslStatus' => $domain['sslStatus'],
                'aliases' => $domain['aliases'] ?? [],
                'projectId' => $domain['projectId'],
                'target' => $domain['target'],
                'redirect' => $domain['redirect'],
                'mxRecords' => $domain['mxRecords'] ?? [],
                'txtRecords' => $domain['txtRecords'] ?? [],
                'aRecords' => $domain['aRecords'] ?? [],
                'aaaaRecords' => $domain['aaaaRecords'] ?? [],
                'cnameRecords' => $domain['cnameRecords'] ?? [],
            ], $data['domains'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Vercel team members.
     *
     * @param string $teamId Team ID
     * @param int    $limit  Number of members to retrieve
     *
     * @return array<int, array{
     *     uid: string,
     *     email: string,
     *     username: string,
     *     name: string,
     *     avatar: string,
     *     role: string,
     *     billingRole: string,
     *     joinedAt: int,
     *     limited: bool,
     *     restricted: bool,
     * }>
     */
    public function getTeamMembers(
        string $teamId,
        int $limit = 20,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            $response = $this->httpClient->request('GET', "https://api.vercel.com/{$this->apiVersion}/teams/{$teamId}/members", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($member) => [
                'uid' => $member['uid'],
                'email' => $member['email'],
                'username' => $member['username'],
                'name' => $member['name'],
                'avatar' => $member['avatar'],
                'role' => $member['role'],
                'billingRole' => $member['billingRole'],
                'joinedAt' => $member['joinedAt'],
                'limited' => $member['limited'] ?? false,
                'restricted' => $member['restricted'] ?? false,
            ], $data['members'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }
}
