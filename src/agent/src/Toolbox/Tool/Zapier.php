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
#[AsTool('zapier_trigger_zap', 'Tool that triggers Zapier zaps')]
#[AsTool('zapier_list_zaps', 'Tool that lists Zapier zaps', method: 'listZaps')]
#[AsTool('zapier_get_zap', 'Tool that gets Zapier zap details', method: 'getZap')]
#[AsTool('zapier_create_zap', 'Tool that creates Zapier zaps', method: 'createZap')]
#[AsTool('zapier_update_zap', 'Tool that updates Zapier zaps', method: 'updateZap')]
#[AsTool('zapier_delete_zap', 'Tool that deletes Zapier zaps', method: 'deleteZap')]
final readonly class Zapier
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $baseUrl = 'https://hooks.zapier.com/hooks/catch',
        private array $options = [],
    ) {
    }

    /**
     * Trigger Zapier zap.
     *
     * @param string               $webhookUrl Zapier webhook URL
     * @param array<string, mixed> $data       Data to send to zap
     *
     * @return array{
     *     success: bool,
     *     status: int,
     *     response: array<string, mixed>,
     *     error: string,
     * }
     */
    public function __invoke(
        string $webhookUrl,
        array $data = [],
    ): array {
        try {
            $response = $this->httpClient->request('POST', $webhookUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray();

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status' => $statusCode,
                'response' => $responseData,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 0,
                'response' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List Zapier zaps.
     *
     * @param string $status Zap status filter (active, paused, draft)
     * @param int    $limit  Number of zaps to return
     * @param int    $offset Offset for pagination
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     status: string,
     *     created: string,
     *     modified: string,
     *     url: string,
     *     trigger: array{
     *         type: string,
     *         app: string,
     *         title: string,
     *     },
     *     actions: array<int, array{
     *         type: string,
     *         app: string,
     *         title: string,
     *     }>,
     * }>
     */
    public function listZaps(
        string $status = '',
        int $limit = 50,
        int $offset = 0,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
                'offset' => max($offset, 0),
            ];

            if ($status) {
                $params['status'] = $status;
            }

            $response = $this->httpClient->request('GET', 'https://api.zapier.com/v2/zaps', [
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

            return array_map(fn ($zap) => [
                'id' => $zap['id'],
                'title' => $zap['title'],
                'description' => $zap['description'] ?? '',
                'status' => $zap['status'],
                'created' => $zap['created'],
                'modified' => $zap['modified'],
                'url' => $zap['url'],
                'trigger' => [
                    'type' => $zap['trigger']['type'],
                    'app' => $zap['trigger']['app'],
                    'title' => $zap['trigger']['title'],
                ],
                'actions' => array_map(fn ($action) => [
                    'type' => $action['type'],
                    'app' => $action['app'],
                    'title' => $action['title'],
                ], $zap['actions'] ?? []),
            ], $data['results'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Zapier zap details.
     *
     * @param string $zapId Zap ID
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     status: string,
     *     created: string,
     *     modified: string,
     *     url: string,
     *     trigger: array{
     *         type: string,
     *         app: string,
     *         title: string,
     *         fields: array<string, mixed>,
     *     },
     *     actions: array<int, array{
     *         type: string,
     *         app: string,
     *         title: string,
     *         fields: array<string, mixed>,
     *     }>,
     *     lastRun: string,
     *     runCount: int,
     * }|string
     */
    public function getZap(string $zapId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.zapier.com/v2/zaps/{$zapId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting zap: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'status' => $data['status'],
                'created' => $data['created'],
                'modified' => $data['modified'],
                'url' => $data['url'],
                'trigger' => [
                    'type' => $data['trigger']['type'],
                    'app' => $data['trigger']['app'],
                    'title' => $data['trigger']['title'],
                    'fields' => $data['trigger']['fields'] ?? [],
                ],
                'actions' => array_map(fn ($action) => [
                    'type' => $action['type'],
                    'app' => $action['app'],
                    'title' => $action['title'],
                    'fields' => $action['fields'] ?? [],
                ], $data['actions'] ?? []),
                'lastRun' => $data['lastRun'] ?? '',
                'runCount' => $data['runCount'] ?? 0,
            ];
        } catch (\Exception $e) {
            return 'Error getting zap: '.$e->getMessage();
        }
    }

    /**
     * Create Zapier zap.
     *
     * @param string                           $title       Zap title
     * @param string                           $description Zap description
     * @param array<string, mixed>             $trigger     Trigger configuration
     * @param array<int, array<string, mixed>> $actions     Actions configuration
     *
     * @return array{
     *     success: bool,
     *     zapId: string,
     *     url: string,
     *     error: string,
     * }
     */
    public function createZap(
        string $title,
        string $description = '',
        array $trigger = [],
        array $actions = [],
    ): array {
        try {
            $body = [
                'title' => $title,
                'description' => $description,
                'trigger' => $trigger,
                'actions' => $actions,
            ];

            $response = $this->httpClient->request('POST', 'https://api.zapier.com/v2/zaps', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [
                    'success' => false,
                    'zapId' => '',
                    'url' => '',
                    'error' => $data['error']['message'] ?? 'Unknown error',
                ];
            }

            return [
                'success' => true,
                'zapId' => $data['id'] ?? '',
                'url' => $data['url'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'zapId' => '',
                'url' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update Zapier zap.
     *
     * @param string $zapId       Zap ID
     * @param string $title       New zap title
     * @param string $description New zap description
     * @param string $status      New zap status
     *
     * @return array{
     *     success: bool,
     *     zapId: string,
     *     url: string,
     *     error: string,
     * }
     */
    public function updateZap(
        string $zapId,
        string $title = '',
        string $description = '',
        string $status = '',
    ): array {
        try {
            $body = [];

            if ($title) {
                $body['title'] = $title;
            }
            if ($description) {
                $body['description'] = $description;
            }
            if ($status) {
                $body['status'] = $status;
            }

            $response = $this->httpClient->request('PATCH', "https://api.zapier.com/v2/zaps/{$zapId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [
                    'success' => false,
                    'zapId' => '',
                    'url' => '',
                    'error' => $data['error']['message'] ?? 'Unknown error',
                ];
            }

            return [
                'success' => true,
                'zapId' => $data['id'] ?? $zapId,
                'url' => $data['url'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'zapId' => '',
                'url' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete Zapier zap.
     *
     * @param string $zapId Zap ID
     *
     * @return array{
     *     success: bool,
     *     error: string,
     * }
     */
    public function deleteZap(string $zapId): array
    {
        try {
            $response = $this->httpClient->request('DELETE', "https://api.zapier.com/v2/zaps/{$zapId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $data = $response->toArray();

                return [
                    'success' => false,
                    'error' => $data['error']['message'] ?? 'Failed to delete zap',
                ];
            }

            return [
                'success' => true,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
