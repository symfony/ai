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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('vault_read_secret', 'Tool that reads secrets from Vault')]
#[AsTool('vault_write_secret', 'Tool that writes secrets to Vault', method: 'writeSecret')]
#[AsTool('vault_delete_secret', 'Tool that deletes secrets from Vault', method: 'deleteSecret')]
#[AsTool('vault_list_secrets', 'Tool that lists secrets in Vault', method: 'listSecrets')]
#[AsTool('vault_auth_token', 'Tool that authenticates with Vault token', method: 'authToken')]
#[AsTool('vault_auth_userpass', 'Tool that authenticates with username/password', method: 'authUserpass')]
final readonly class Vault
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'http://localhost:8200',
        #[\SensitiveParameter] private string $token = '',
        private string $apiVersion = 'v1',
        private array $options = [],
    ) {
    }

    /**
     * Read secret from Vault.
     *
     * @param string $path    Secret path
     * @param string $version Secret version (KV v2)
     *
     * @return array{
     *     data: array<string, mixed>,
     *     metadata: array{
     *         created_time: string,
     *         custom_metadata: array<string, string>,
     *         deletion_time: string,
     *         destroyed: bool,
     *         version: int,
     *     },
     *     lease_duration: int,
     *     lease_id: string,
     *     renewable: bool,
     *     request_id: string,
     *     warnings: array<int, string>,
     *     wrap_info: array<string, mixed>|null,
     * }|string
     */
    public function __invoke(
        string $path,
        string $version = '',
    ): array|string {
        try {
            $params = [];

            if ($version) {
                $params['version'] = $version;
            }

            $headers = [
                'Content-Type' => 'application/json',
                'X-Vault-Request' => 'true',
            ];

            if ($this->token) {
                $headers['X-Vault-Token'] = $this->token;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/{$this->apiVersion}/{$path}", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error reading secret: '.implode(', ', $data['errors']);
            }

            return [
                'data' => $data['data']['data'] ?? $data['data'] ?? [],
                'metadata' => [
                    'created_time' => $data['data']['metadata']['created_time'] ?? '',
                    'custom_metadata' => $data['data']['metadata']['custom_metadata'] ?? [],
                    'deletion_time' => $data['data']['metadata']['deletion_time'] ?? '',
                    'destroyed' => $data['data']['metadata']['destroyed'] ?? false,
                    'version' => $data['data']['metadata']['version'] ?? 1,
                ],
                'lease_duration' => $data['lease_duration'] ?? 0,
                'lease_id' => $data['lease_id'] ?? '',
                'renewable' => $data['renewable'] ?? false,
                'request_id' => $data['request_id'] ?? '',
                'warnings' => $data['warnings'] ?? [],
                'wrap_info' => $data['wrap_info'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error reading secret: '.$e->getMessage();
        }
    }

    /**
     * Write secret to Vault.
     *
     * @param string                $path           Secret path
     * @param array<string, mixed>  $data           Secret data
     * @param array<string, string> $customMetadata Custom metadata
     *
     * @return array{
     *     success: bool,
     *     version: int,
     *     created_time: string,
     *     request_id: string,
     *     error: string,
     * }|string
     */
    public function writeSecret(
        string $path,
        array $data = [],
        array $customMetadata = [],
    ): array|string {
        try {
            $body = [
                'data' => $data,
            ];

            if (!empty($customMetadata)) {
                $body['options'] = [
                    'cas' => 0,
                ];
                $body['metadata'] = $customMetadata;
            }

            $headers = [
                'Content-Type' => 'application/json',
                'X-Vault-Request' => 'true',
            ];

            if ($this->token) {
                $headers['X-Vault-Token'] = $this->token;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/{$this->apiVersion}/{$path}", [
                'headers' => $headers,
                'json' => $body,
            ]);

            $responseData = $response->toArray();

            if (isset($responseData['errors'])) {
                return [
                    'success' => false,
                    'version' => 0,
                    'created_time' => '',
                    'request_id' => '',
                    'error' => implode(', ', $responseData['errors']),
                ];
            }

            return [
                'success' => true,
                'version' => $responseData['data']['version'] ?? 1,
                'created_time' => $responseData['data']['created_time'] ?? '',
                'request_id' => $responseData['request_id'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'version' => 0,
                'created_time' => '',
                'request_id' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete secret from Vault.
     *
     * @param string $path     Secret path
     * @param string $versions Versions to delete (comma-separated)
     *
     * @return array{
     *     success: bool,
     *     request_id: string,
     *     error: string,
     * }|string
     */
    public function deleteSecret(
        string $path,
        string $versions = '',
    ): array|string {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'X-Vault-Request' => 'true',
            ];

            if ($this->token) {
                $headers['X-Vault-Token'] = $this->token;
            }

            $body = [];
            if ($versions) {
                $body['versions'] = array_map('intval', explode(',', $versions));
            }

            $method = $versions ? 'POST' : 'DELETE';
            $url = $versions ? "{$this->baseUrl}/{$this->apiVersion}/{$path}/delete" : "{$this->baseUrl}/{$this->apiVersion}/{$path}";

            $response = $this->httpClient->request($method, $url, [
                'headers' => $headers,
                'json' => $body,
            ]);

            $responseData = $response->toArray();

            if (isset($responseData['errors'])) {
                return [
                    'success' => false,
                    'request_id' => '',
                    'error' => implode(', ', $responseData['errors']),
                ];
            }

            return [
                'success' => true,
                'request_id' => $responseData['request_id'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'request_id' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List secrets in Vault.
     *
     * @param string $path Secret path
     *
     * @return array{
     *     keys: array<int, string>,
     *     request_id: string,
     * }|string
     */
    public function listSecrets(string $path): array|string
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'X-Vault-Request' => 'true',
            ];

            if ($this->token) {
                $headers['X-Vault-Token'] = $this->token;
            }

            $response = $this->httpClient->request('LIST', "{$this->baseUrl}/{$this->apiVersion}/{$path}", [
                'headers' => $headers,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return 'Error listing secrets: '.implode(', ', $data['errors']);
            }

            return [
                'keys' => $data['data']['keys'] ?? [],
                'request_id' => $data['request_id'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error listing secrets: '.$e->getMessage();
        }
    }

    /**
     * Authenticate with Vault using token.
     *
     * @param string $token Vault token
     *
     * @return array{
     *     success: bool,
     *     client_token: string,
     *     accessor: string,
     *     policies: array<int, string>,
     *     token_policies: array<int, string>,
     *     metadata: array<string, mixed>,
     *     lease_duration: int,
     *     renewable: bool,
     *     entity_id: string,
     *     token_type: string,
     *     orphan: bool,
     *     error: string,
     * }|string
     */
    public function authToken(string $token): array|string
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'X-Vault-Token' => $token,
            ];

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/{$this->apiVersion}/auth/token/lookup-self", [
                'headers' => $headers,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return [
                    'success' => false,
                    'client_token' => '',
                    'accessor' => '',
                    'policies' => [],
                    'token_policies' => [],
                    'metadata' => [],
                    'lease_duration' => 0,
                    'renewable' => false,
                    'entity_id' => '',
                    'token_type' => '',
                    'orphan' => false,
                    'error' => implode(', ', $data['errors']),
                ];
            }

            return [
                'success' => true,
                'client_token' => $data['data']['id'] ?? '',
                'accessor' => $data['data']['accessor'] ?? '',
                'policies' => $data['data']['policies'] ?? [],
                'token_policies' => $data['data']['token_policies'] ?? [],
                'metadata' => $data['data']['meta'] ?? [],
                'lease_duration' => $data['data']['ttl'] ?? 0,
                'renewable' => $data['data']['renewable'] ?? false,
                'entity_id' => $data['data']['entity_id'] ?? '',
                'token_type' => $data['data']['type'] ?? '',
                'orphan' => $data['data']['orphan'] ?? false,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'client_token' => '',
                'accessor' => '',
                'policies' => [],
                'token_policies' => [],
                'metadata' => [],
                'lease_duration' => 0,
                'renewable' => false,
                'entity_id' => '',
                'token_type' => '',
                'orphan' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Authenticate with Vault using username/password.
     *
     * @param string $username  Username
     * @param string $password  Password
     * @param string $mountPath Auth mount path
     *
     * @return array{
     *     success: bool,
     *     client_token: string,
     *     accessor: string,
     *     policies: array<int, string>,
     *     token_policies: array<int, string>,
     *     metadata: array<string, mixed>,
     *     lease_duration: int,
     *     renewable: bool,
     *     entity_id: string,
     *     token_type: string,
     *     orphan: bool,
     *     error: string,
     * }|string
     */
    public function authUserpass(
        string $username,
        string $password,
        string $mountPath = 'userpass',
    ): array|string {
        try {
            $body = [
                'password' => $password,
            ];

            $headers = [
                'Content-Type' => 'application/json',
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/{$this->apiVersion}/auth/{$mountPath}/login/{$username}", [
                'headers' => $headers,
                'json' => $body,
            ]);

            $data = $response->toArray();

            if (isset($data['errors'])) {
                return [
                    'success' => false,
                    'client_token' => '',
                    'accessor' => '',
                    'policies' => [],
                    'token_policies' => [],
                    'metadata' => [],
                    'lease_duration' => 0,
                    'renewable' => false,
                    'entity_id' => '',
                    'token_type' => '',
                    'orphan' => false,
                    'error' => implode(', ', $data['errors']),
                ];
            }

            return [
                'success' => true,
                'client_token' => $data['auth']['client_token'] ?? '',
                'accessor' => $data['auth']['accessor'] ?? '',
                'policies' => $data['auth']['policies'] ?? [],
                'token_policies' => $data['auth']['token_policies'] ?? [],
                'metadata' => $data['auth']['metadata'] ?? [],
                'lease_duration' => $data['auth']['lease_duration'] ?? 0,
                'renewable' => $data['auth']['renewable'] ?? false,
                'entity_id' => $data['auth']['entity_id'] ?? '',
                'token_type' => $data['auth']['token_type'] ?? '',
                'orphan' => $data['auth']['orphan'] ?? false,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'client_token' => '',
                'accessor' => '',
                'policies' => [],
                'token_policies' => [],
                'metadata' => [],
                'lease_duration' => 0,
                'renewable' => false,
                'entity_id' => '',
                'token_type' => '',
                'orphan' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
