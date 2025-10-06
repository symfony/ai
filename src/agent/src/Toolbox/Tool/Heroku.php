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
#[AsTool('heroku_get_apps', 'Tool that gets Heroku apps')]
#[AsTool('heroku_create_app', 'Tool that creates Heroku apps', method: 'createApp')]
#[AsTool('heroku_get_dynos', 'Tool that gets Heroku dynos', method: 'getDynos')]
#[AsTool('heroku_scale_dynos', 'Tool that scales Heroku dynos', method: 'scaleDynos')]
#[AsTool('heroku_get_addons', 'Tool that gets Heroku addons', method: 'getAddons')]
#[AsTool('heroku_get_logs', 'Tool that gets Heroku logs', method: 'getLogs')]
final readonly class Heroku
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $apiVersion = '3',
        private array $options = [],
    ) {
    }

    /**
     * Get Heroku apps.
     *
     * @param int $limit Number of apps to retrieve
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     stack: array{id: string, name: string},
     *     region: array{id: string, name: string},
     *     space: array{id: string, name: string}|null,
     *     internal_routing: bool,
     *     git_url: string,
     *     web_url: string,
     *     owner: array{id: string, email: string},
     *     organization: array{id: string, name: string}|null,
     *     team: array{id: string, name: string}|null,
     *     acm: bool,
     *     archived_at: string|null,
     *     buildpack_provided_description: string|null,
     *     build_stack: array{id: string, name: string},
     *     created_at: string,
     *     updated_at: string,
     *     released_at: string,
     *     repo_size: int|null,
     *     slug_size: int|null,
     *     dyno_size: int,
     *     repo_migrated_at: string|null,
     *     stack_migrated_at: string|null,
     * }>
     */
    public function __invoke(int $limit = 50): array
    {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            $response = $this->httpClient->request('GET', 'https://api.heroku.com/apps', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/vnd.heroku+json; version='.$this->apiVersion,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($app) => [
                'id' => $app['id'],
                'name' => $app['name'],
                'stack' => [
                    'id' => $app['stack']['id'],
                    'name' => $app['stack']['name'],
                ],
                'region' => [
                    'id' => $app['region']['id'],
                    'name' => $app['region']['name'],
                ],
                'space' => $app['space'] ? [
                    'id' => $app['space']['id'],
                    'name' => $app['space']['name'],
                ] : null,
                'internal_routing' => $app['internal_routing'] ?? false,
                'git_url' => $app['git_url'],
                'web_url' => $app['web_url'],
                'owner' => [
                    'id' => $app['owner']['id'],
                    'email' => $app['owner']['email'],
                ],
                'organization' => $app['organization'] ? [
                    'id' => $app['organization']['id'],
                    'name' => $app['organization']['name'],
                ] : null,
                'team' => $app['team'] ? [
                    'id' => $app['team']['id'],
                    'name' => $app['team']['name'],
                ] : null,
                'acm' => $app['acm'] ?? false,
                'archived_at' => $app['archived_at'],
                'buildpack_provided_description' => $app['buildpack_provided_description'],
                'build_stack' => [
                    'id' => $app['build_stack']['id'],
                    'name' => $app['build_stack']['name'],
                ],
                'created_at' => $app['created_at'],
                'updated_at' => $app['updated_at'],
                'released_at' => $app['released_at'],
                'repo_size' => $app['repo_size'],
                'slug_size' => $app['slug_size'],
                'dyno_size' => $app['dyno_size'] ?? 0,
                'repo_migrated_at' => $app['repo_migrated_at'],
                'stack_migrated_at' => $app['stack_migrated_at'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Heroku app.
     *
     * @param string $name           App name
     * @param string $region         Region (us, eu)
     * @param string $stack          Stack (heroku-18, heroku-20, heroku-22)
     * @param string $spaceId        Space ID (for private spaces)
     * @param string $organizationId Organization ID
     * @param string $teamId         Team ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     stack: array{id: string, name: string},
     *     region: array{id: string, name: string},
     *     git_url: string,
     *     web_url: string,
     *     owner: array{id: string, email: string},
     *     created_at: string,
     *     updated_at: string,
     *     released_at: string,
     * }|string
     */
    public function createApp(
        string $name,
        string $region = 'us',
        string $stack = 'heroku-22',
        string $spaceId = '',
        string $organizationId = '',
        string $teamId = '',
    ): array|string {
        try {
            $payload = [
                'name' => $name,
                'region' => $region,
                'stack' => $stack,
            ];

            if ($spaceId) {
                $payload['space'] = ['id' => $spaceId];
            }
            if ($organizationId) {
                $payload['organization'] = ['id' => $organizationId];
            }
            if ($teamId) {
                $payload['team'] = ['id' => $teamId];
            }

            $response = $this->httpClient->request('POST', 'https://api.heroku.com/apps', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/vnd.heroku+json; version='.$this->apiVersion,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (false === isset($data['id'])) {
                return 'Error creating app: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'stack' => [
                    'id' => $data['stack']['id'],
                    'name' => $data['stack']['name'],
                ],
                'region' => [
                    'id' => $data['region']['id'],
                    'name' => $data['region']['name'],
                ],
                'git_url' => $data['git_url'],
                'web_url' => $data['web_url'],
                'owner' => [
                    'id' => $data['owner']['id'],
                    'email' => $data['owner']['email'],
                ],
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at'],
                'released_at' => $data['released_at'],
            ];
        } catch (\Exception $e) {
            return 'Error creating app: '.$e->getMessage();
        }
    }

    /**
     * Get Heroku dynos.
     *
     * @param string $appId App ID
     * @param int    $limit Number of dynos to retrieve
     *
     * @return array<int, array{
     *     id: string,
     *     app: array{id: string, name: string},
     *     attach_url: string|null,
     *     command: string,
     *     created_at: string,
     *     name: string,
     *     release: array{id: string, version: int},
     *     size: string,
     *     state: string,
     *     type: string,
     *     updated_at: string,
     * }>
     */
    public function getDynos(string $appId, int $limit = 50): array
    {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            $response = $this->httpClient->request('GET', "https://api.heroku.com/apps/{$appId}/dynos", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/vnd.heroku+json; version='.$this->apiVersion,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($dyno) => [
                'id' => $dyno['id'],
                'app' => [
                    'id' => $dyno['app']['id'],
                    'name' => $dyno['app']['name'],
                ],
                'attach_url' => $dyno['attach_url'],
                'command' => $dyno['command'],
                'created_at' => $dyno['created_at'],
                'name' => $dyno['name'],
                'release' => [
                    'id' => $dyno['release']['id'],
                    'version' => $dyno['release']['version'],
                ],
                'size' => $dyno['size'],
                'state' => $dyno['state'],
                'type' => $dyno['type'],
                'updated_at' => $dyno['updated_at'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Scale Heroku dynos.
     *
     * @param string $appId    App ID
     * @param string $type     Dyno type (web, worker, etc.)
     * @param int    $quantity Number of dynos
     * @param string $size     Dyno size (basic, standard-1x, standard-2x, etc.)
     *
     * @return array{
     *     id: string,
     *     app: array{id: string, name: string},
     *     command: string,
     *     created_at: string,
     *     name: string,
     *     release: array{id: string, version: int},
     *     size: string,
     *     state: string,
     *     type: string,
     *     updated_at: string,
     * }|string
     */
    public function scaleDynos(
        string $appId,
        string $type,
        int $quantity,
        string $size = 'basic',
    ): array|string {
        try {
            $payload = [
                'updates' => [
                    [
                        'type' => $type,
                        'quantity' => $quantity,
                        'size' => $size,
                    ],
                ],
            ];

            $response = $this->httpClient->request('PATCH', "https://api.heroku.com/apps/{$appId}/formation", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/vnd.heroku+json; version='.$this->apiVersion,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (false === isset($data[0]['id'])) {
                return 'Error scaling dynos: '.($data['message'] ?? 'Unknown error');
            }

            $dyno = $data[0];

            return [
                'id' => $dyno['id'],
                'app' => [
                    'id' => $dyno['app']['id'],
                    'name' => $dyno['app']['name'],
                ],
                'command' => $dyno['command'],
                'created_at' => $dyno['created_at'],
                'name' => $dyno['name'],
                'release' => [
                    'id' => $dyno['release']['id'],
                    'version' => $dyno['release']['version'],
                ],
                'size' => $dyno['size'],
                'state' => $dyno['state'],
                'type' => $dyno['type'],
                'updated_at' => $dyno['updated_at'],
            ];
        } catch (\Exception $e) {
            return 'Error scaling dynos: '.$e->getMessage();
        }
    }

    /**
     * Get Heroku addons.
     *
     * @param string $appId App ID
     * @param int    $limit Number of addons to retrieve
     *
     * @return array<int, array{
     *     id: string,
     *     app: array{id: string, name: string},
     *     addon_service: array{
     *         id: string,
     *         name: string,
     *         human_name: string,
     *         description: string,
     *         state: string,
     *         price: array{cents: int, unit: string},
     *     },
     *     config_vars: array<string, string>,
     *     created_at: string,
     *     name: string,
     *     plan: array{
     *         id: string,
     *         name: string,
     *         price: array{cents: int, unit: string},
     *         state: string,
     *     },
     *     provider_id: string|null,
     *     state: string,
     *     updated_at: string,
     *     web_url: string|null,
     * }>
     */
    public function getAddons(string $appId, int $limit = 50): array
    {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            $response = $this->httpClient->request('GET', "https://api.heroku.com/apps/{$appId}/addons", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/vnd.heroku+json; version='.$this->apiVersion,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            return array_map(fn ($addon) => [
                'id' => $addon['id'],
                'app' => [
                    'id' => $addon['app']['id'],
                    'name' => $addon['app']['name'],
                ],
                'addon_service' => [
                    'id' => $addon['addon_service']['id'],
                    'name' => $addon['addon_service']['name'],
                    'human_name' => $addon['addon_service']['human_name'],
                    'description' => $addon['addon_service']['description'],
                    'state' => $addon['addon_service']['state'],
                    'price' => [
                        'cents' => $addon['addon_service']['price']['cents'],
                        'unit' => $addon['addon_service']['price']['unit'],
                    ],
                ],
                'config_vars' => $addon['config_vars'] ?? [],
                'created_at' => $addon['created_at'],
                'name' => $addon['name'],
                'plan' => [
                    'id' => $addon['plan']['id'],
                    'name' => $addon['plan']['name'],
                    'price' => [
                        'cents' => $addon['plan']['price']['cents'],
                        'unit' => $addon['plan']['price']['unit'],
                    ],
                    'state' => $addon['plan']['state'],
                ],
                'provider_id' => $addon['provider_id'],
                'state' => $addon['state'],
                'updated_at' => $addon['updated_at'],
                'web_url' => $addon['web_url'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Heroku logs.
     *
     * @param string $appId  App ID
     * @param int    $lines  Number of log lines to retrieve
     * @param string $source Log source (app, heroku, etc.)
     * @param string $dyno   Dyno name
     * @param string $tail   Whether to tail logs (true/false)
     *
     * @return array{
     *     logs: string,
     *     truncated: bool,
     * }|string
     */
    public function getLogs(
        string $appId,
        int $lines = 100,
        string $source = '',
        string $dyno = '',
        string $tail = 'false',
    ): array|string {
        try {
            $params = [
                'lines' => min(max($lines, 1), 1500),
                'tail' => $tail,
            ];

            if ($source) {
                $params['source'] = $source;
            }
            if ($dyno) {
                $params['dyno'] = $dyno;
            }

            $response = $this->httpClient->request('GET', "https://api.heroku.com/apps/{$appId}/log-sessions", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/vnd.heroku+json; version='.$this->apiVersion,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['logplex_url'])) {
                // Get logs from logplex URL
                $logResponse = $this->httpClient->request('GET', $data['logplex_url']);
                $logs = $logResponse->getContent();

                return [
                    'logs' => $logs,
                    'truncated' => false,
                ];
            }

            return 'Error getting logs: '.($data['message'] ?? 'Unknown error');
        } catch (\Exception $e) {
            return 'Error getting logs: '.$e->getMessage();
        }
    }
}
