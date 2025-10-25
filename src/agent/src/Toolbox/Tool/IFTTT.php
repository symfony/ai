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
#[AsTool('ifttt_trigger_webhook', 'Tool that triggers IFTTT webhooks')]
#[AsTool('ifttt_create_applet', 'Tool that creates IFTTT applets', method: 'createApplet')]
#[AsTool('ifttt_list_applets', 'Tool that lists IFTTT applets', method: 'listApplets')]
#[AsTool('ifttt_get_applet', 'Tool that gets IFTTT applet details', method: 'getApplet')]
#[AsTool('ifttt_update_applet', 'Tool that updates IFTTT applets', method: 'updateApplet')]
#[AsTool('ifttt_delete_applet', 'Tool that deletes IFTTT applets', method: 'deleteApplet')]
final readonly class IFTTT
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $baseUrl = 'https://ifttt.com/maker_webhooks',
        private array $options = [],
    ) {
    }

    /**
     * Trigger IFTTT webhook.
     *
     * @param string               $eventName Webhook event name
     * @param array<string, mixed> $values    Values to send to webhook
     *
     * @return array{
     *     success: bool,
     *     status: int,
     *     response: array<string, mixed>,
     *     error: string,
     * }
     */
    public function __invoke(
        string $eventName,
        array $values = [],
    ): array {
        try {
            $body = [
                'value1' => $values['value1'] ?? '',
                'value2' => $values['value2'] ?? '',
                'value3' => $values['value3'] ?? '',
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/trigger/{$eventName}/with/key/{$this->apiKey}", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
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
     * Create IFTTT applet.
     *
     * @param string               $name          Applet name
     * @param string               $trigger       Trigger service and event
     * @param string               $action        Action service and event
     * @param array<string, mixed> $triggerFields Trigger field values
     * @param array<string, mixed> $actionFields  Action field values
     *
     * @return array{
     *     success: bool,
     *     appletId: string,
     *     url: string,
     *     error: string,
     * }
     */
    public function createApplet(
        string $name,
        string $trigger,
        string $action,
        array $triggerFields = [],
        array $actionFields = [],
    ): array {
        try {
            $body = [
                'name' => $name,
                'trigger' => $trigger,
                'action' => $action,
                'triggerFields' => $triggerFields,
                'actionFields' => $actionFields,
            ];

            $response = $this->httpClient->request('POST', 'https://ifttt.com/api/v1/applets', [
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
                    'appletId' => '',
                    'url' => '',
                    'error' => $data['error']['message'] ?? 'Unknown error',
                ];
            }

            return [
                'success' => true,
                'appletId' => $data['id'] ?? '',
                'url' => $data['url'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'appletId' => '',
                'url' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List IFTTT applets.
     *
     * @param int $limit  Number of applets to return
     * @param int $offset Offset for pagination
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     status: string,
     *     trigger: array{
     *         service: string,
     *         event: string,
     *     },
     *     action: array{
     *         service: string,
     *         event: string,
     *     },
     *     created: string,
     *     updated: string,
     *     url: string,
     * }>
     */
    public function listApplets(
        int $limit = 50,
        int $offset = 0,
    ): array {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100),
                'offset' => max($offset, 0),
            ];

            $response = $this->httpClient->request('GET', 'https://ifttt.com/api/v1/applets', [
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

            return array_map(fn ($applet) => [
                'id' => $applet['id'],
                'name' => $applet['name'],
                'status' => $applet['status'],
                'trigger' => [
                    'service' => $applet['trigger']['service'],
                    'event' => $applet['trigger']['event'],
                ],
                'action' => [
                    'service' => $applet['action']['service'],
                    'event' => $applet['action']['event'],
                ],
                'created' => $applet['created'],
                'updated' => $applet['updated'],
                'url' => $applet['url'],
            ], $data['applets'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get IFTTT applet details.
     *
     * @param string $appletId Applet ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     status: string,
     *     trigger: array{
     *         service: string,
     *         event: string,
     *         fields: array<string, mixed>,
     *     },
     *     action: array{
     *         service: string,
     *         event: string,
     *         fields: array<string, mixed>,
     *     },
     *     created: string,
     *     updated: string,
     *     url: string,
     *     runCount: int,
     * }|string
     */
    public function getApplet(string $appletId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://ifttt.com/api/v1/applets/{$appletId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting applet: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'status' => $data['status'],
                'trigger' => [
                    'service' => $data['trigger']['service'],
                    'event' => $data['trigger']['event'],
                    'fields' => $data['trigger']['fields'] ?? [],
                ],
                'action' => [
                    'service' => $data['action']['service'],
                    'event' => $data['action']['event'],
                    'fields' => $data['action']['fields'] ?? [],
                ],
                'created' => $data['created'],
                'updated' => $data['updated'],
                'url' => $data['url'],
                'runCount' => $data['runCount'] ?? 0,
            ];
        } catch (\Exception $e) {
            return 'Error getting applet: '.$e->getMessage();
        }
    }

    /**
     * Update IFTTT applet.
     *
     * @param string               $appletId      Applet ID
     * @param string               $name          New applet name
     * @param string               $status        New applet status
     * @param array<string, mixed> $triggerFields Updated trigger field values
     * @param array<string, mixed> $actionFields  Updated action field values
     *
     * @return array{
     *     success: bool,
     *     appletId: string,
     *     url: string,
     *     error: string,
     * }
     */
    public function updateApplet(
        string $appletId,
        string $name = '',
        string $status = '',
        array $triggerFields = [],
        array $actionFields = [],
    ): array {
        try {
            $body = [];

            if ($name) {
                $body['name'] = $name;
            }
            if ($status) {
                $body['status'] = $status;
            }
            if (!empty($triggerFields)) {
                $body['triggerFields'] = $triggerFields;
            }
            if (!empty($actionFields)) {
                $body['actionFields'] = $actionFields;
            }

            $response = $this->httpClient->request('PATCH', "https://ifttt.com/api/v1/applets/{$appletId}", [
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
                    'appletId' => '',
                    'url' => '',
                    'error' => $data['error']['message'] ?? 'Unknown error',
                ];
            }

            return [
                'success' => true,
                'appletId' => $data['id'] ?? $appletId,
                'url' => $data['url'] ?? '',
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'appletId' => '',
                'url' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete IFTTT applet.
     *
     * @param string $appletId Applet ID
     *
     * @return array{
     *     success: bool,
     *     error: string,
     * }
     */
    public function deleteApplet(string $appletId): array
    {
        try {
            $response = $this->httpClient->request('DELETE', "https://ifttt.com/api/v1/applets/{$appletId}", [
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
                    'error' => $data['error']['message'] ?? 'Failed to delete applet',
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
