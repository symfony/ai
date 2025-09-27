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
#[AsTool('slack_send_message', 'Tool that sends messages to Slack channels')]
#[AsTool('slack_get_messages', 'Tool that retrieves messages from Slack channels', method: 'getMessages')]
#[AsTool('slack_list_channels', 'Tool that lists Slack channels', method: 'listChannels')]
#[AsTool('slack_get_user_info', 'Tool that gets Slack user information', method: 'getUserInfo')]
#[AsTool('slack_upload_file', 'Tool that uploads files to Slack', method: 'uploadFile')]
final readonly class Slack
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
     * Send a message to a Slack channel.
     *
     * @param string               $message  The message to be sent
     * @param string               $channel  The channel, private group, or IM channel to send message to
     * @param array<string, mixed> $blocks   Optional rich formatting blocks
     * @param string               $threadTs Optional timestamp of parent message for threading
     *
     * @return array{
     *     ok: bool,
     *     channel: string,
     *     ts: string,
     *     message: array<string, mixed>,
     * }|string
     */
    public function __invoke(
        #[With(maximum: 4000)]
        string $message,
        string $channel,
        array $blocks = [],
        string $threadTs = '',
    ): array|string {
        try {
            $payload = [
                'channel' => $channel,
                'text' => $message,
            ];

            if (!empty($blocks)) {
                $payload['blocks'] = $blocks;
            }

            if ($threadTs) {
                $payload['thread_ts'] = $threadTs;
            }

            $response = $this->httpClient->request('POST', 'https://slack.com/api/chat.postMessage', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->botToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error sending message: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'ok' => $data['ok'],
                'channel' => $data['channel'],
                'ts' => $data['ts'],
                'message' => $data['message'],
            ];
        } catch (\Exception $e) {
            return 'Error sending message: '.$e->getMessage();
        }
    }

    /**
     * Get messages from a Slack channel.
     *
     * @param string $channelId The channel ID to get messages from
     * @param int    $limit     Maximum number of messages to retrieve
     * @param string $oldest    Start of time range for messages (timestamp)
     * @param string $latest    End of time range for messages (timestamp)
     *
     * @return array<int, array{
     *     user: string,
     *     text: string,
     *     ts: string,
     *     thread_ts: string|null,
     *     replies: array<int, array{user: string, ts: string}>,
     * }>|string
     */
    public function getMessages(
        string $channelId,
        int $limit = 100,
        string $oldest = '',
        string $latest = '',
    ): array|string {
        try {
            $params = [
                'channel' => $channelId,
                'limit' => $limit,
            ];

            if ($oldest) {
                $params['oldest'] = $oldest;
            }
            if ($latest) {
                $params['latest'] = $latest;
            }

            $response = $this->httpClient->request('GET', 'https://slack.com/api/conversations.history', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->botToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error getting messages: '.($data['error'] ?? 'Unknown error');
            }

            $messages = [];
            foreach ($data['messages'] as $message) {
                if (isset($message['user']) && isset($message['text']) && isset($message['ts'])) {
                    $messages[] = [
                        'user' => $message['user'],
                        'text' => $message['text'],
                        'ts' => $message['ts'],
                        'thread_ts' => $message['thread_ts'] ?? null,
                        'replies' => $message['replies'] ?? [],
                    ];
                }
            }

            return $messages;
        } catch (\Exception $e) {
            return 'Error getting messages: '.$e->getMessage();
        }
    }

    /**
     * List Slack channels.
     *
     * @param bool   $excludeArchived Whether to exclude archived channels
     * @param string $types           Types of channels to list (public_channel, private_channel, mpim, im)
     * @param int    $limit           Maximum number of channels to retrieve
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     is_channel: bool,
     *     is_group: bool,
     *     is_im: bool,
     *     is_private: bool,
     *     is_archived: bool,
     *     is_member: bool,
     *     num_members: int,
     *     purpose: array{value: string, creator: string, last_set: int},
     *     topic: array{value: string, creator: string, last_set: int},
     * }>|string
     */
    public function listChannels(
        bool $excludeArchived = true,
        string $types = 'public_channel,private_channel',
        int $limit = 100,
    ): array|string {
        try {
            $response = $this->httpClient->request('GET', 'https://slack.com/api/conversations.list', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->botToken,
                ],
                'query' => array_merge($this->options, [
                    'exclude_archived' => $excludeArchived,
                    'types' => $types,
                    'limit' => $limit,
                ]),
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error listing channels: '.($data['error'] ?? 'Unknown error');
            }

            $channels = [];
            foreach ($data['channels'] as $channel) {
                $channels[] = [
                    'id' => $channel['id'],
                    'name' => $channel['name'],
                    'is_channel' => $channel['is_channel'] ?? false,
                    'is_group' => $channel['is_group'] ?? false,
                    'is_im' => $channel['is_im'] ?? false,
                    'is_private' => $channel['is_private'] ?? false,
                    'is_archived' => $channel['is_archived'] ?? false,
                    'is_member' => $channel['is_member'] ?? false,
                    'num_members' => $channel['num_members'] ?? 0,
                    'purpose' => $channel['purpose'] ?? ['value' => '', 'creator' => '', 'last_set' => 0],
                    'topic' => $channel['topic'] ?? ['value' => '', 'creator' => '', 'last_set' => 0],
                ];
            }

            return $channels;
        } catch (\Exception $e) {
            return 'Error listing channels: '.$e->getMessage();
        }
    }

    /**
     * Get Slack user information.
     *
     * @param string $userId User ID to get information for
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     real_name: string,
     *     display_name: string,
     *     profile: array{
     *         title: string,
     *         phone: string,
     *         skype: string,
     *         real_name: string,
     *         real_name_normalized: string,
     *         display_name: string,
     *         display_name_normalized: string,
     *         email: string,
     *         image_24: string,
     *         image_32: string,
     *         image_48: string,
     *         image_72: string,
     *         image_192: string,
     *         image_512: string,
     *         status_text: string,
     *         status_emoji: string,
     *     },
     *     is_admin: bool,
     *     is_owner: bool,
     *     is_bot: bool,
     *     is_app_user: bool,
     *     deleted: bool,
     * }|string
     */
    public function getUserInfo(string $userId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://slack.com/api/users.info', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->botToken,
                ],
                'query' => array_merge($this->options, [
                    'user' => $userId,
                ]),
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error getting user info: '.($data['error'] ?? 'Unknown error');
            }

            $user = $data['user'];

            return [
                'id' => $user['id'],
                'name' => $user['name'],
                'real_name' => $user['real_name'],
                'display_name' => $user['profile']['display_name'] ?? '',
                'profile' => [
                    'title' => $user['profile']['title'] ?? '',
                    'phone' => $user['profile']['phone'] ?? '',
                    'skype' => $user['profile']['skype'] ?? '',
                    'real_name' => $user['profile']['real_name'] ?? '',
                    'real_name_normalized' => $user['profile']['real_name_normalized'] ?? '',
                    'display_name' => $user['profile']['display_name'] ?? '',
                    'display_name_normalized' => $user['profile']['display_name_normalized'] ?? '',
                    'email' => $user['profile']['email'] ?? '',
                    'image_24' => $user['profile']['image_24'] ?? '',
                    'image_32' => $user['profile']['image_32'] ?? '',
                    'image_48' => $user['profile']['image_48'] ?? '',
                    'image_72' => $user['profile']['image_72'] ?? '',
                    'image_192' => $user['profile']['image_192'] ?? '',
                    'image_512' => $user['profile']['image_512'] ?? '',
                    'status_text' => $user['profile']['status_text'] ?? '',
                    'status_emoji' => $user['profile']['status_emoji'] ?? '',
                ],
                'is_admin' => $user['is_admin'] ?? false,
                'is_owner' => $user['is_owner'] ?? false,
                'is_bot' => $user['is_bot'] ?? false,
                'is_app_user' => $user['is_app_user'] ?? false,
                'deleted' => $user['deleted'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error getting user info: '.$e->getMessage();
        }
    }

    /**
     * Upload a file to Slack.
     *
     * @param string $filePath       Path to the file to upload
     * @param string $channels       Comma-separated list of channel names or IDs
     * @param string $title          Title of the file
     * @param string $initialComment Initial comment to add to the file
     *
     * @return array{
     *     ok: bool,
     *     file: array{
     *         id: string,
     *         name: string,
     *         title: string,
     *         mimetype: string,
     *         filetype: string,
     *         pretty_type: string,
     *         user: string,
     *         size: int,
     *         url_private: string,
     *         url_private_download: string,
     *         permalink: string,
     *         permalink_public: string,
     *     },
     * }|string
     */
    public function uploadFile(
        string $filePath,
        string $channels,
        string $title = '',
        string $initialComment = '',
    ): array|string {
        try {
            if (!file_exists($filePath)) {
                return 'Error: File does not exist';
            }

            $fileContent = file_get_contents($filePath);
            $fileName = basename($filePath);

            $response = $this->httpClient->request('POST', 'https://slack.com/api/files.upload', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->botToken,
                ],
                'body' => [
                    'channels' => $channels,
                    'title' => $title ?: $fileName,
                    'initial_comment' => $initialComment,
                    'filename' => $fileName,
                    'file' => $fileContent,
                ],
            ]);

            $data = $response->toArray();

            if (!$data['ok']) {
                return 'Error uploading file: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'ok' => $data['ok'],
                'file' => [
                    'id' => $data['file']['id'],
                    'name' => $data['file']['name'],
                    'title' => $data['file']['title'],
                    'mimetype' => $data['file']['mimetype'],
                    'filetype' => $data['file']['filetype'],
                    'pretty_type' => $data['file']['pretty_type'],
                    'user' => $data['file']['user'],
                    'size' => $data['file']['size'],
                    'url_private' => $data['file']['url_private'],
                    'url_private_download' => $data['file']['url_private_download'],
                    'permalink' => $data['file']['permalink'],
                    'permalink_public' => $data['file']['permalink_public'],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error uploading file: '.$e->getMessage();
        }
    }
}
