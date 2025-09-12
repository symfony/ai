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
#[AsTool('telegram_send_message', 'Tool that sends messages via Telegram bot')]
#[AsTool('telegram_send_photo', 'Tool that sends photos via Telegram bot', method: 'sendPhoto')]
#[AsTool('telegram_send_document', 'Tool that sends documents via Telegram bot', method: 'sendDocument')]
#[AsTool('telegram_get_updates', 'Tool that gets updates from Telegram bot', method: 'getUpdates')]
#[AsTool('telegram_get_chat_info', 'Tool that gets chat information from Telegram', method: 'getChatInfo')]
final readonly class TelegramBot
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $botToken,
        private array $options = [],
    ) {
    }

    /**
     * Send a message via Telegram bot.
     *
     * @param string $chatId                Chat ID to send message to
     * @param string $text                  Message text
     * @param string $parseMode             Parse mode (HTML, Markdown, MarkdownV2)
     * @param bool   $disableWebPagePreview Whether to disable web page preview
     * @param bool   $disableNotification   Whether to disable notification
     * @param int    $replyToMessageId      ID of the original message if replying
     *
     * @return array{
     *     message_id: int,
     *     from: array{
     *         id: int,
     *         is_bot: bool,
     *         first_name: string,
     *         username: string,
     *     },
     *     chat: array{
     *         id: int,
     *         type: string,
     *         title: string,
     *         username: string,
     *     },
     *     date: int,
     *     text: string,
     * }|string
     */
    public function __invoke(
        string $chatId,
        #[With(maximum: 4096)]
        string $text,
        string $parseMode = 'HTML',
        bool $disableWebPagePreview = false,
        bool $disableNotification = false,
        int $replyToMessageId = 0,
    ): array|string {
        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => $disableWebPagePreview,
                'disable_notification' => $disableNotification,
            ];

            if ($replyToMessageId > 0) {
                $payload['reply_to_message_id'] = $replyToMessageId;
            }

            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error sending message: '.($data['description'] ?? 'Unknown error');
            }

            $message = $data['result'];

            return [
                'message_id' => $message['message_id'],
                'from' => [
                    'id' => $message['from']['id'],
                    'is_bot' => $message['from']['is_bot'],
                    'first_name' => $message['from']['first_name'],
                    'username' => $message['from']['username'] ?? '',
                ],
                'chat' => [
                    'id' => $message['chat']['id'],
                    'type' => $message['chat']['type'],
                    'title' => $message['chat']['title'] ?? '',
                    'username' => $message['chat']['username'] ?? '',
                ],
                'date' => $message['date'],
                'text' => $message['text'],
            ];
        } catch (\Exception $e) {
            return 'Error sending message: '.$e->getMessage();
        }
    }

    /**
     * Send a photo via Telegram bot.
     *
     * @param string $chatId    Chat ID to send photo to
     * @param string $photoPath Path to the photo file or photo URL
     * @param string $caption   Photo caption
     * @param string $parseMode Parse mode for caption (HTML, Markdown, MarkdownV2)
     *
     * @return array<string, mixed>|string
     */
    public function sendPhoto(
        string $chatId,
        string $photoPath,
        string $caption = '',
        string $parseMode = 'HTML',
    ): array|string {
        try {
            $payload = [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => $parseMode,
            ];

            // Check if it's a file path or URL
            if (file_exists($photoPath)) {
                $payload['photo'] = fopen($photoPath, 'r');
            } else {
                $payload['photo'] = $photoPath;
            }

            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$this->botToken}/sendPhoto", [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => $payload,
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error sending photo: '.($data['description'] ?? 'Unknown error');
            }

            return $data['result'];
        } catch (\Exception $e) {
            return 'Error sending photo: '.$e->getMessage();
        }
    }

    /**
     * Send a document via Telegram bot.
     *
     * @param string $chatId       Chat ID to send document to
     * @param string $documentPath Path to the document file
     * @param string $caption      Document caption
     * @param string $parseMode    Parse mode for caption (HTML, Markdown, MarkdownV2)
     *
     * @return array<string, mixed>|string
     */
    public function sendDocument(
        string $chatId,
        string $documentPath,
        string $caption = '',
        string $parseMode = 'HTML',
    ): array|string {
        try {
            if (!file_exists($documentPath)) {
                return 'Error: Document file does not exist';
            }

            $payload = [
                'chat_id' => $chatId,
                'document' => fopen($documentPath, 'r'),
                'caption' => $caption,
                'parse_mode' => $parseMode,
            ];

            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$this->botToken}/sendDocument", [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => $payload,
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error sending document: '.($data['description'] ?? 'Unknown error');
            }

            return $data['result'];
        } catch (\Exception $e) {
            return 'Error sending document: '.$e->getMessage();
        }
    }

    /**
     * Get updates from Telegram bot.
     *
     * @param int                   $offset         Identifier of the first update to be returned
     * @param int                   $limit          Limits the number of updates to be retrieved (1-100)
     * @param int                   $timeout        Timeout in seconds for long polling
     * @param array<string, string> $allowedUpdates List of update types to receive
     *
     * @return array<int, array{
     *     update_id: int,
     *     message: array{
     *         message_id: int,
     *         from: array{
     *             id: int,
     *             is_bot: bool,
     *             first_name: string,
     *             username: string,
     *         },
     *         chat: array{
     *             id: int,
     *             type: string,
     *             title: string,
     *             username: string,
     *         },
     *         date: int,
     *         text: string,
     *     },
     * }>|string
     */
    public function getUpdates(
        int $offset = 0,
        int $limit = 100,
        int $timeout = 0,
        array $allowedUpdates = [],
    ): array|string {
        try {
            $params = [
                'offset' => $offset,
                'limit' => min(max($limit, 1), 100),
                'timeout' => $timeout,
            ];

            if (!empty($allowedUpdates)) {
                $params['allowed_updates'] = $allowedUpdates;
            }

            $response = $this->httpClient->request('GET', "https://api.telegram.org/bot{$this->botToken}/getUpdates", [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error getting updates: '.($data['description'] ?? 'Unknown error');
            }

            $updates = [];
            foreach ($data['result'] as $update) {
                if (isset($update['message'])) {
                    $updates[] = [
                        'update_id' => $update['update_id'],
                        'message' => [
                            'message_id' => $update['message']['message_id'],
                            'from' => [
                                'id' => $update['message']['from']['id'],
                                'is_bot' => $update['message']['from']['is_bot'],
                                'first_name' => $update['message']['from']['first_name'],
                                'username' => $update['message']['from']['username'] ?? '',
                            ],
                            'chat' => [
                                'id' => $update['message']['chat']['id'],
                                'type' => $update['message']['chat']['type'],
                                'title' => $update['message']['chat']['title'] ?? '',
                                'username' => $update['message']['chat']['username'] ?? '',
                            ],
                            'date' => $update['message']['date'],
                            'text' => $update['message']['text'] ?? '',
                        ],
                    ];
                }
            }

            return $updates;
        } catch (\Exception $e) {
            return 'Error getting updates: '.$e->getMessage();
        }
    }

    /**
     * Get chat information from Telegram.
     *
     * @param string $chatId Chat ID to get information for
     *
     * @return array{
     *     id: int,
     *     type: string,
     *     title: string,
     *     username: string,
     *     first_name: string,
     *     last_name: string,
     *     description: string,
     *     invite_link: string,
     *     member_count: int,
     *     can_set_sticker_set: bool,
     *     sticker_set_name: string,
     * }|string
     */
    public function getChatInfo(string $chatId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.telegram.org/bot{$this->botToken}/getChat", [
                'query' => [
                    'chat_id' => $chatId,
                ],
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error getting chat info: '.($data['description'] ?? 'Unknown error');
            }

            $chat = $data['result'];

            return [
                'id' => $chat['id'],
                'type' => $chat['type'],
                'title' => $chat['title'] ?? '',
                'username' => $chat['username'] ?? '',
                'first_name' => $chat['first_name'] ?? '',
                'last_name' => $chat['last_name'] ?? '',
                'description' => $chat['description'] ?? '',
                'invite_link' => $chat['invite_link'] ?? '',
                'member_count' => $chat['member_count'] ?? 0,
                'can_set_sticker_set' => $chat['can_set_sticker_set'] ?? false,
                'sticker_set_name' => $chat['sticker_set_name'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error getting chat info: '.$e->getMessage();
        }
    }

    /**
     * Set webhook for Telegram bot.
     *
     * @param string $url         HTTPS URL to send updates to
     * @param string $certificate Path to public key certificate (optional)
     */
    public function setWebhook(string $url, string $certificate = ''): string
    {
        try {
            $payload = [
                'url' => $url,
            ];

            if ($certificate && file_exists($certificate)) {
                $payload['certificate'] = fopen($certificate, 'r');
            }

            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$this->botToken}/setWebhook", [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => $payload,
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error setting webhook: '.($data['description'] ?? 'Unknown error');
            }

            return 'Webhook set successfully';
        } catch (\Exception $e) {
            return 'Error setting webhook: '.$e->getMessage();
        }
    }

    /**
     * Delete webhook for Telegram bot.
     */
    public function deleteWebhook(): string
    {
        try {
            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$this->botToken}/deleteWebhook");

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error deleting webhook: '.($data['description'] ?? 'Unknown error');
            }

            return 'Webhook deleted successfully';
        } catch (\Exception $e) {
            return 'Error deleting webhook: '.$e->getMessage();
        }
    }
}
