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
#[AsTool('whatsapp_send_message', 'Tool that sends messages via WhatsApp Business API')]
#[AsTool('whatsapp_send_template', 'Tool that sends template messages via WhatsApp', method: 'sendTemplate')]
#[AsTool('whatsapp_send_media', 'Tool that sends media via WhatsApp', method: 'sendMedia')]
#[AsTool('whatsapp_get_webhook_events', 'Tool that gets WhatsApp webhook events', method: 'getWebhookEvents')]
#[AsTool('whatsapp_mark_as_read', 'Tool that marks messages as read', method: 'markAsRead')]
final readonly class WhatsApp
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $phoneNumberId,
        private string $businessAccountId,
        private array $options = [],
    ) {
    }

    /**
     * Send a message via WhatsApp Business API.
     *
     * @param string               $to      Recipient phone number (with country code, no +)
     * @param string               $message Message text content
     * @param string               $type    Message type (text, interactive, button, list)
     * @param array<string, mixed> $context Optional context for replies
     *
     * @return array{
     *     messaging_product: string,
     *     contacts: array<int, array{input: string, wa_id: string}>,
     *     messages: array<int, array{id: string}>,
     * }|string
     */
    public function __invoke(
        string $to,
        #[With(maximum: 4096)]
        string $message,
        string $type = 'text',
        array $context = [],
    ): array|string {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => $type,
                'text' => [
                    'body' => $message,
                ],
            ];

            if (!empty($context)) {
                $payload['context'] = $context;
            }

            $response = $this->httpClient->request('POST', "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error sending message: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'messaging_product' => $data['messaging_product'],
                'contacts' => $data['contacts'] ?? [],
                'messages' => $data['messages'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error sending message: '.$e->getMessage();
        }
    }

    /**
     * Send a template message via WhatsApp.
     *
     * @param string               $to           Recipient phone number
     * @param string               $templateName Template name
     * @param string               $language     Template language code
     * @param array<string, mixed> $components   Template components
     *
     * @return array{
     *     messaging_product: string,
     *     contacts: array<int, array{input: string, wa_id: string}>,
     *     messages: array<int, array{id: string}>,
     * }|string
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language = 'en_US',
        array $components = [],
    ): array|string {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $language,
                    ],
                ],
            ];

            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }

            $response = $this->httpClient->request('POST', "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error sending template: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'messaging_product' => $data['messaging_product'],
                'contacts' => $data['contacts'] ?? [],
                'messages' => $data['messages'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error sending template: '.$e->getMessage();
        }
    }

    /**
     * Send media via WhatsApp.
     *
     * @param string $to        Recipient phone number
     * @param string $mediaType Media type (image, video, audio, document)
     * @param string $mediaUrl  URL of the media file
     * @param string $caption   Optional caption for the media
     * @param string $filename  Optional filename for documents
     *
     * @return array{
     *     messaging_product: string,
     *     contacts: array<int, array{input: string, wa_id: string}>,
     *     messages: array<int, array{id: string}>,
     * }|string
     */
    public function sendMedia(
        string $to,
        string $mediaType,
        string $mediaUrl,
        string $caption = '',
        string $filename = '',
    ): array|string {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => $mediaType,
                $mediaType => [
                    'link' => $mediaUrl,
                ],
            ];

            if ($caption) {
                $payload[$mediaType]['caption'] = $caption;
            }

            if ($filename && 'document' === $mediaType) {
                $payload[$mediaType]['filename'] = $filename;
            }

            $response = $this->httpClient->request('POST', "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error sending media: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'messaging_product' => $data['messaging_product'],
                'contacts' => $data['contacts'] ?? [],
                'messages' => $data['messages'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error sending media: '.$e->getMessage();
        }
    }

    /**
     * Get WhatsApp webhook events (simulated - in real implementation, this would be handled by webhooks).
     *
     * @param int    $limit Maximum number of events to retrieve
     * @param string $since Get events since this timestamp
     *
     * @return array<int, array{
     *     id: string,
     *     timestamp: string,
     *     type: string,
     *     from: string,
     *     message: array{
     *         id: string,
     *         type: string,
     *         text: array{body: string}|null,
     *         image: array{id: string, mime_type: string, sha256: string}|null,
     *         document: array{id: string, filename: string, mime_type: string}|null,
     *     },
     * }>
     */
    public function getWebhookEvents(int $limit = 50, string $since = ''): array
    {
        try {
            // Note: In a real implementation, webhook events are received via HTTP POST
            // This is a placeholder method for demonstration purposes
            // You would typically store webhook events in a database and retrieve them here

            $params = [
                'limit' => min(max($limit, 1), 100),
            ];

            if ($since) {
                $params['since'] = $since;
            }

            // This would typically query your webhook event storage
            // For now, returning empty array as placeholder
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Mark messages as read.
     *
     * @param string $messageId WhatsApp message ID to mark as read
     *
     * @return array{
     *     success: bool,
     * }|string
     */
    public function markAsRead(string $messageId): array|string
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
            ];

            $response = $this->httpClient->request('POST', "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error marking as read: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            return 'Error marking as read: '.$e->getMessage();
        }
    }

    /**
     * Send interactive message with buttons.
     *
     * @param string                                                                   $to      Recipient phone number
     * @param string                                                                   $header  Header text
     * @param string                                                                   $body    Body text
     * @param string                                                                   $footer  Footer text
     * @param array<int, array{type: string, reply: array{id: string, title: string}}> $buttons Array of buttons
     *
     * @return array{
     *     messaging_product: string,
     *     contacts: array<int, array{input: string, wa_id: string}>,
     *     messages: array<int, array{id: string}>,
     * }|string
     */
    public function sendInteractiveMessage(
        string $to,
        string $header,
        string $body,
        string $footer = '',
        array $buttons = [],
    ): array|string {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'header' => [
                        'type' => 'text',
                        'text' => $header,
                    ],
                    'body' => [
                        'text' => $body,
                    ],
                    'action' => [
                        'buttons' => \array_slice($buttons, 0, 3), // WhatsApp allows max 3 buttons
                    ],
                ],
            ];

            if ($footer) {
                $payload['interactive']['footer'] = [
                    'text' => $footer,
                ];
            }

            $response = $this->httpClient->request('POST', "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error sending interactive message: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'messaging_product' => $data['messaging_product'],
                'contacts' => $data['contacts'] ?? [],
                'messages' => $data['messages'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error sending interactive message: '.$e->getMessage();
        }
    }

    /**
     * Send list message.
     *
     * @param string                                                            $to         Recipient phone number
     * @param string                                                            $header     Header text
     * @param string                                                            $body       Body text
     * @param string                                                            $footer     Footer text
     * @param string                                                            $buttonText Button text
     * @param array<int, array{id: string, title: string, description: string}> $sections   Array of list sections
     *
     * @return array{
     *     messaging_product: string,
     *     contacts: array<int, array{input: string, wa_id: string}>,
     *     messages: array<int, array{id: string}>,
     * }|string
     */
    public function sendListMessage(
        string $to,
        string $header,
        string $body,
        string $footer = '',
        string $buttonText = 'Choose an option',
        array $sections = [],
    ): array|string {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'header' => [
                        'type' => 'text',
                        'text' => $header,
                    ],
                    'body' => [
                        'text' => $body,
                    ],
                    'action' => [
                        'button' => $buttonText,
                        'sections' => $sections,
                    ],
                ],
            ];

            if ($footer) {
                $payload['interactive']['footer'] = [
                    'text' => $footer,
                ];
            }

            $response = $this->httpClient->request('POST', "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error sending list message: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'messaging_product' => $data['messaging_product'],
                'contacts' => $data['contacts'] ?? [],
                'messages' => $data['messages'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error sending list message: '.$e->getMessage();
        }
    }

    /**
     * Get WhatsApp Business API profile information.
     *
     * @return array{
     *     about: string,
     *     address: string,
     *     description: string,
     *     email: string,
     *     profile_picture_url: string,
     *     websites: array<int, string>,
     *     vertical: string,
     * }|string
     */
    public function getProfile(): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/whatsapp_business_profile", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting profile: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'about' => $data['about'] ?? '',
                'address' => $data['address'] ?? '',
                'description' => $data['description'] ?? '',
                'email' => $data['email'] ?? '',
                'profile_picture_url' => $data['profile_picture_url'] ?? '',
                'websites' => $data['websites'] ?? [],
                'vertical' => $data['vertical'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error getting profile: '.$e->getMessage();
        }
    }
}
