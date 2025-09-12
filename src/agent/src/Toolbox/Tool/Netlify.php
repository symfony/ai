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
#[AsTool('netlify_get_sites', 'Tool that gets Netlify sites')]
#[AsTool('netlify_create_site', 'Tool that creates Netlify sites', method: 'createSite')]
#[AsTool('netlify_get_deploys', 'Tool that gets Netlify deploys', method: 'getDeploys')]
#[AsTool('netlify_create_deploy', 'Tool that creates Netlify deploys', method: 'createDeploy')]
#[AsTool('netlify_get_dns_zones', 'Tool that gets Netlify DNS zones', method: 'getDnsZones')]
#[AsTool('netlify_get_forms', 'Tool that gets Netlify forms', method: 'getForms')]
final readonly class Netlify
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v1',
        private array $options = [],
    ) {
    }

    /**
     * Get Netlify sites.
     *
     * @param string $name    Site name filter
     * @param string $filter  Filter (all, owner, guest)
     * @param int    $perPage Number of sites per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     url: string,
     *     custom_domain: string|null,
     *     domain_aliases: array<int, string>,
     *     screenshot_url: string|null,
     *     created_at: string,
     *     updated_at: string,
     *     user_id: string,
     *     session_id: string|null,
     *     ssl: bool,
     *     force_ssl: bool,
     *     managed_dns: bool,
     *     deploy_url: string,
     *     state: string,
     *     admin_url: string,
     *     published_deploy: array{
     *         id: string,
     *         site_id: string,
     *         user_id: string,
     *         build_id: string|null,
     *         state: string,
     *         name: string,
     *         url: string,
     *         ssl_url: string,
     *         admin_url: string,
     *         deploy_url: string,
     *         deploy_ssl_url: string,
     *         screenshot_url: string|null,
     *         review_id: int|null,
     *         draft: bool,
     *         required: array<int, string>,
     *         required_functions: array<int, string>,
     *         error_message: string|null,
     *         branch: string,
     *         commit_ref: string|null,
     *         commit_url: string|null,
     *         skipped: bool,
     *         locked: bool,
     *         created_at: string,
     *         updated_at: string,
     *         published_at: string,
     *         title: string|null,
     *         context: string,
     *         deploy_time: int|null,
     *         links: array<string, string>,
     *     },
     *     build_settings: array{
     *         id: int,
     *         provider: string,
     *         deploy_key_id: string,
     *         repo_path: string,
     *         repo_branch: string,
     *         dir: string,
     *         functions_dir: string|null,
     *         cmd: string|null,
     *         allowed_branches: array<int, string>,
     *         public_repo: bool,
     *         private_logs: bool,
     *         repo_url: string,
     *         env: array<string, mixed>,
     *         installation_id: int|null,
     *         stop_builds: bool,
     *         created_at: string,
     *         updated_at: string,
     *     }|null,
     *     capabilities: array{
     *         large_media_enabled: bool,
     *         forms: array{
     *             enabled: bool,
     *             max_file_size: int,
     *             max_files: int,
     *             max_fields: int,
     *             max_submissions: int,
     *             spam_filter: array{enabled: bool},
     *         },
     *         functions: array{
     *             enabled: bool,
     *             max_invocations: int,
     *             max_execution_time: int,
     *         },
     *         split_testing: array{enabled: bool},
     *         identity: array{enabled: bool},
     *         large_media: array{enabled: bool},
     *         background_functions: array{enabled: bool},
     *         edge_functions: array{enabled: bool},
     *     },
     * }>
     */
    public function __invoke(
        string $name = '',
        string $filter = 'all',
        int $perPage = 50,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
                'filter' => $filter,
            ];

            if ($name) {
                $params['name'] = $name;
            }

            $response = $this->httpClient->request('GET', "https://api.netlify.com/api/{$this->apiVersion}/sites", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($site) => [
                'id' => $site['id'],
                'name' => $site['name'],
                'url' => $site['url'],
                'custom_domain' => $site['custom_domain'],
                'domain_aliases' => $site['domain_aliases'] ?? [],
                'screenshot_url' => $site['screenshot_url'],
                'created_at' => $site['created_at'],
                'updated_at' => $site['updated_at'],
                'user_id' => $site['user_id'],
                'session_id' => $site['session_id'],
                'ssl' => $site['ssl'] ?? false,
                'force_ssl' => $site['force_ssl'] ?? false,
                'managed_dns' => $site['managed_dns'] ?? false,
                'deploy_url' => $site['deploy_url'],
                'state' => $site['state'] ?? 'ready',
                'admin_url' => $site['admin_url'],
                'published_deploy' => $site['published_deploy'] ? [
                    'id' => $site['published_deploy']['id'],
                    'site_id' => $site['published_deploy']['site_id'],
                    'user_id' => $site['published_deploy']['user_id'],
                    'build_id' => $site['published_deploy']['build_id'],
                    'state' => $site['published_deploy']['state'],
                    'name' => $site['published_deploy']['name'],
                    'url' => $site['published_deploy']['url'],
                    'ssl_url' => $site['published_deploy']['ssl_url'],
                    'admin_url' => $site['published_deploy']['admin_url'],
                    'deploy_url' => $site['published_deploy']['deploy_url'],
                    'deploy_ssl_url' => $site['published_deploy']['deploy_ssl_url'],
                    'screenshot_url' => $site['published_deploy']['screenshot_url'],
                    'review_id' => $site['published_deploy']['review_id'],
                    'draft' => $site['published_deploy']['draft'] ?? false,
                    'required' => $site['published_deploy']['required'] ?? [],
                    'required_functions' => $site['published_deploy']['required_functions'] ?? [],
                    'error_message' => $site['published_deploy']['error_message'],
                    'branch' => $site['published_deploy']['branch'],
                    'commit_ref' => $site['published_deploy']['commit_ref'],
                    'commit_url' => $site['published_deploy']['commit_url'],
                    'skipped' => $site['published_deploy']['skipped'] ?? false,
                    'locked' => $site['published_deploy']['locked'] ?? false,
                    'created_at' => $site['published_deploy']['created_at'],
                    'updated_at' => $site['published_deploy']['updated_at'],
                    'published_at' => $site['published_deploy']['published_at'],
                    'title' => $site['published_deploy']['title'],
                    'context' => $site['published_deploy']['context'],
                    'deploy_time' => $site['published_deploy']['deploy_time'],
                    'links' => $site['published_deploy']['links'] ?? [],
                ] : null,
                'build_settings' => $site['build_settings'] ? [
                    'id' => $site['build_settings']['id'],
                    'provider' => $site['build_settings']['provider'],
                    'deploy_key_id' => $site['build_settings']['deploy_key_id'],
                    'repo_path' => $site['build_settings']['repo_path'],
                    'repo_branch' => $site['build_settings']['repo_branch'],
                    'dir' => $site['build_settings']['dir'],
                    'functions_dir' => $site['build_settings']['functions_dir'],
                    'cmd' => $site['build_settings']['cmd'],
                    'allowed_branches' => $site['build_settings']['allowed_branches'] ?? [],
                    'public_repo' => $site['build_settings']['public_repo'] ?? false,
                    'private_logs' => $site['build_settings']['private_logs'] ?? false,
                    'repo_url' => $site['build_settings']['repo_url'],
                    'env' => $site['build_settings']['env'] ?? [],
                    'installation_id' => $site['build_settings']['installation_id'],
                    'stop_builds' => $site['build_settings']['stop_builds'] ?? false,
                    'created_at' => $site['build_settings']['created_at'],
                    'updated_at' => $site['build_settings']['updated_at'],
                ] : null,
                'capabilities' => [
                    'large_media_enabled' => $site['capabilities']['large_media_enabled'] ?? false,
                    'forms' => [
                        'enabled' => $site['capabilities']['forms']['enabled'] ?? false,
                        'max_file_size' => $site['capabilities']['forms']['max_file_size'] ?? 10485760,
                        'max_files' => $site['capabilities']['forms']['max_files'] ?? 10,
                        'max_fields' => $site['capabilities']['forms']['max_fields'] ?? 100,
                        'max_submissions' => $site['capabilities']['forms']['max_submissions'] ?? 1000,
                        'spam_filter' => [
                            'enabled' => $site['capabilities']['forms']['spam_filter']['enabled'] ?? false,
                        ],
                    ],
                    'functions' => [
                        'enabled' => $site['capabilities']['functions']['enabled'] ?? false,
                        'max_invocations' => $site['capabilities']['functions']['max_invocations'] ?? 125000,
                        'max_execution_time' => $site['capabilities']['functions']['max_execution_time'] ?? 10,
                    ],
                    'split_testing' => ['enabled' => $site['capabilities']['split_testing']['enabled'] ?? false],
                    'identity' => ['enabled' => $site['capabilities']['identity']['enabled'] ?? false],
                    'large_media' => ['enabled' => $site['capabilities']['large_media']['enabled'] ?? false],
                    'background_functions' => ['enabled' => $site['capabilities']['background_functions']['enabled'] ?? false],
                    'edge_functions' => ['enabled' => $site['capabilities']['edge_functions']['enabled'] ?? false],
                ],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Netlify site.
     *
     * @param string $name         Site name
     * @param string $repoUrl      Git repository URL
     * @param string $branch       Git branch
     * @param string $dir          Build directory
     * @param string $cmd          Build command
     * @param string $functionsDir Functions directory
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     url: string,
     *     custom_domain: string|null,
     *     domain_aliases: array<int, string>,
     *     created_at: string,
     *     updated_at: string,
     *     user_id: string,
     *     ssl: bool,
     *     force_ssl: bool,
     *     managed_dns: bool,
     *     deploy_url: string,
     *     state: string,
     *     admin_url: string,
     *     build_settings: array{
     *         id: int,
     *         provider: string,
     *         repo_path: string,
     *         repo_branch: string,
     *         dir: string,
     *         functions_dir: string|null,
     *         cmd: string|null,
     *         allowed_branches: array<int, string>,
     *         public_repo: bool,
     *         private_logs: bool,
     *         repo_url: string,
     *         env: array<string, mixed>,
     *         stop_builds: bool,
     *         created_at: string,
     *         updated_at: string,
     *     },
     * }|string
     */
    public function createSite(
        string $name,
        string $repoUrl = '',
        string $branch = 'main',
        string $dir = '',
        string $cmd = '',
        string $functionsDir = '',
    ): array|string {
        try {
            $payload = [
                'name' => $name,
            ];

            if ($repoUrl) {
                $payload['build_settings'] = [
                    'repo_url' => $repoUrl,
                    'repo_branch' => $branch,
                    'dir' => $dir,
                    'cmd' => $cmd,
                    'functions_dir' => $functionsDir,
                ];
            }

            $response = $this->httpClient->request('POST', "https://api.netlify.com/api/{$this->apiVersion}/sites", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating site: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'url' => $data['url'],
                'custom_domain' => $data['custom_domain'],
                'domain_aliases' => $data['domain_aliases'] ?? [],
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at'],
                'user_id' => $data['user_id'],
                'ssl' => $data['ssl'] ?? false,
                'force_ssl' => $data['force_ssl'] ?? false,
                'managed_dns' => $data['managed_dns'] ?? false,
                'deploy_url' => $data['deploy_url'],
                'state' => $data['state'] ?? 'ready',
                'admin_url' => $data['admin_url'],
                'build_settings' => [
                    'id' => $data['build_settings']['id'],
                    'provider' => $data['build_settings']['provider'],
                    'repo_path' => $data['build_settings']['repo_path'],
                    'repo_branch' => $data['build_settings']['repo_branch'],
                    'dir' => $data['build_settings']['dir'],
                    'functions_dir' => $data['build_settings']['functions_dir'],
                    'cmd' => $data['build_settings']['cmd'],
                    'allowed_branches' => $data['build_settings']['allowed_branches'] ?? [],
                    'public_repo' => $data['build_settings']['public_repo'] ?? false,
                    'private_logs' => $data['build_settings']['private_logs'] ?? false,
                    'repo_url' => $data['build_settings']['repo_url'],
                    'env' => $data['build_settings']['env'] ?? [],
                    'stop_builds' => $data['build_settings']['stop_builds'] ?? false,
                    'created_at' => $data['build_settings']['created_at'],
                    'updated_at' => $data['build_settings']['updated_at'],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error creating site: '.$e->getMessage();
        }
    }

    /**
     * Get Netlify deploys.
     *
     * @param string $siteId  Site ID
     * @param int    $perPage Number of deploys per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: string,
     *     site_id: string,
     *     user_id: string,
     *     build_id: string|null,
     *     state: string,
     *     name: string,
     *     url: string,
     *     ssl_url: string,
     *     admin_url: string,
     *     deploy_url: string,
     *     deploy_ssl_url: string,
     *     screenshot_url: string|null,
     *     review_id: int|null,
     *     draft: bool,
     *     required: array<int, string>,
     *     required_functions: array<int, string>,
     *     error_message: string|null,
     *     branch: string,
     *     commit_ref: string|null,
     *     commit_url: string|null,
     *     skipped: bool,
     *     locked: bool,
     *     created_at: string,
     *     updated_at: string,
     *     published_at: string,
     *     title: string|null,
     *     context: string,
     *     deploy_time: int|null,
     *     links: array<string, string>,
     * }>
     */
    public function getDeploys(
        string $siteId,
        int $perPage = 50,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            $response = $this->httpClient->request('GET', "https://api.netlify.com/api/{$this->apiVersion}/sites/{$siteId}/deploys", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($deploy) => [
                'id' => $deploy['id'],
                'site_id' => $deploy['site_id'],
                'user_id' => $deploy['user_id'],
                'build_id' => $deploy['build_id'],
                'state' => $deploy['state'],
                'name' => $deploy['name'],
                'url' => $deploy['url'],
                'ssl_url' => $deploy['ssl_url'],
                'admin_url' => $deploy['admin_url'],
                'deploy_url' => $deploy['deploy_url'],
                'deploy_ssl_url' => $deploy['deploy_ssl_url'],
                'screenshot_url' => $deploy['screenshot_url'],
                'review_id' => $deploy['review_id'],
                'draft' => $deploy['draft'] ?? false,
                'required' => $deploy['required'] ?? [],
                'required_functions' => $deploy['required_functions'] ?? [],
                'error_message' => $deploy['error_message'],
                'branch' => $deploy['branch'],
                'commit_ref' => $deploy['commit_ref'],
                'commit_url' => $deploy['commit_url'],
                'skipped' => $deploy['skipped'] ?? false,
                'locked' => $deploy['locked'] ?? false,
                'created_at' => $deploy['created_at'],
                'updated_at' => $deploy['updated_at'],
                'published_at' => $deploy['published_at'],
                'title' => $deploy['title'],
                'context' => $deploy['context'],
                'deploy_time' => $deploy['deploy_time'],
                'links' => $deploy['links'] ?? [],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a Netlify deploy.
     *
     * @param string $siteId    Site ID
     * @param string $title     Deploy title
     * @param string $branch    Git branch
     * @param string $commitRef Commit reference
     * @param bool   $draft     Whether deploy is draft
     *
     * @return array{
     *     id: string,
     *     site_id: string,
     *     user_id: string,
     *     state: string,
     *     name: string,
     *     url: string,
     *     ssl_url: string,
     *     admin_url: string,
     *     deploy_url: string,
     *     deploy_ssl_url: string,
     *     draft: bool,
     *     branch: string,
     *     commit_ref: string|null,
     *     created_at: string,
     *     updated_at: string,
     *     published_at: string,
     *     title: string|null,
     *     context: string,
     *     links: array<string, string>,
     * }|string
     */
    public function createDeploy(
        string $siteId,
        string $title = '',
        string $branch = 'main',
        string $commitRef = '',
        bool $draft = false,
    ): array|string {
        try {
            $payload = [
                'branch' => $branch,
                'draft' => $draft,
            ];

            if ($title) {
                $payload['title'] = $title;
            }
            if ($commitRef) {
                $payload['commit_ref'] = $commitRef;
            }

            $response = $this->httpClient->request('POST', "https://api.netlify.com/api/{$this->apiVersion}/sites/{$siteId}/deploys", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating deploy: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'site_id' => $data['site_id'],
                'user_id' => $data['user_id'],
                'state' => $data['state'],
                'name' => $data['name'],
                'url' => $data['url'],
                'ssl_url' => $data['ssl_url'],
                'admin_url' => $data['admin_url'],
                'deploy_url' => $data['deploy_url'],
                'deploy_ssl_url' => $data['deploy_ssl_url'],
                'draft' => $data['draft'] ?? false,
                'branch' => $data['branch'],
                'commit_ref' => $data['commit_ref'],
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at'],
                'published_at' => $data['published_at'],
                'title' => $data['title'],
                'context' => $data['context'],
                'links' => $data['links'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error creating deploy: '.$e->getMessage();
        }
    }

    /**
     * Get Netlify DNS zones.
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     user_id: string,
     *     created_at: string,
     *     updated_at: string,
     *     dns_servers: array<int, string>,
     *     supported_record_types: array<int, string>,
     *     records: array<int, array{
     *         id: string,
     *         hostname: string,
     *         type: string,
     *         value: string,
     *         ttl: int,
     *         priority: int|null,
     *         dns_zone_id: string,
     *         created_at: string,
     *         updated_at: string,
     *     }>,
     * }>
     */
    public function getDnsZones(): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.netlify.com/api/{$this->apiVersion}/dns_zones", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($zone) => [
                'id' => $zone['id'],
                'name' => $zone['name'],
                'user_id' => $zone['user_id'],
                'created_at' => $zone['created_at'],
                'updated_at' => $zone['updated_at'],
                'dns_servers' => $zone['dns_servers'] ?? [],
                'supported_record_types' => $zone['supported_record_types'] ?? [],
                'records' => array_map(fn ($record) => [
                    'id' => $record['id'],
                    'hostname' => $record['hostname'],
                    'type' => $record['type'],
                    'value' => $record['value'],
                    'ttl' => $record['ttl'],
                    'priority' => $record['priority'],
                    'dns_zone_id' => $record['dns_zone_id'],
                    'created_at' => $record['created_at'],
                    'updated_at' => $record['updated_at'],
                ], $zone['records'] ?? []),
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Netlify forms.
     *
     * @param string $siteId  Site ID
     * @param int    $perPage Number of forms per page
     * @param int    $page    Page number
     *
     * @return array<int, array{
     *     id: string,
     *     site_id: string,
     *     name: string,
     *     submission_count: int,
     *     honeypot: bool,
     *     recaptcha: bool,
     *     recaptcha_secret_key: string|null,
     *     notification_email: string|null,
     *     notification_email_enabled: bool,
     *     notification_slack: string|null,
     *     notification_slack_enabled: bool,
     *     notification_webhook: string|null,
     *     notification_webhook_enabled: bool,
     *     notification_discord: string|null,
     *     notification_discord_enabled: bool,
     *     notification_github: string|null,
     *     notification_github_enabled: bool,
     *     notification_microsoft_teams: string|null,
     *     notification_microsoft_teams_enabled: bool,
     *     created_at: string,
     *     updated_at: string,
     *     fields: array<int, array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         placeholder: string,
     *         label: string,
     *         options: array<string, mixed>,
     *     }>,
     * }>
     */
    public function getForms(
        string $siteId,
        int $perPage = 50,
        int $page = 1,
    ): array {
        try {
            $params = [
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ];

            $response = $this->httpClient->request('GET', "https://api.netlify.com/api/{$this->apiVersion}/sites/{$siteId}/forms", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($form) => [
                'id' => $form['id'],
                'site_id' => $form['site_id'],
                'name' => $form['name'],
                'submission_count' => $form['submission_count'] ?? 0,
                'honeypot' => $form['honeypot'] ?? false,
                'recaptcha' => $form['recaptcha'] ?? false,
                'recaptcha_secret_key' => $form['recaptcha_secret_key'],
                'notification_email' => $form['notification_email'],
                'notification_email_enabled' => $form['notification_email_enabled'] ?? false,
                'notification_slack' => $form['notification_slack'],
                'notification_slack_enabled' => $form['notification_slack_enabled'] ?? false,
                'notification_webhook' => $form['notification_webhook'],
                'notification_webhook_enabled' => $form['notification_webhook_enabled'] ?? false,
                'notification_discord' => $form['notification_discord'],
                'notification_discord_enabled' => $form['notification_discord_enabled'] ?? false,
                'notification_github' => $form['notification_github'],
                'notification_github_enabled' => $form['notification_github_enabled'] ?? false,
                'notification_microsoft_teams' => $form['notification_microsoft_teams'],
                'notification_microsoft_teams_enabled' => $form['notification_microsoft_teams_enabled'] ?? false,
                'created_at' => $form['created_at'],
                'updated_at' => $form['updated_at'],
                'fields' => array_map(fn ($field) => [
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'required' => $field['required'] ?? false,
                    'placeholder' => $field['placeholder'] ?? '',
                    'label' => $field['label'] ?? '',
                    'options' => $field['options'] ?? [],
                ], $form['fields'] ?? []),
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
