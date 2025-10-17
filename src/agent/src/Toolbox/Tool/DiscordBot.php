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
#[AsTool('discord_send_message', 'Tool that sends messages to Discord channels')]
#[AsTool('discord_get_messages', 'Tool that retrieves messages from Discord channels', method: 'getMessages')]
#[AsTool('discord_get_channels', 'Tool that lists Discord channels', method: 'getChannels')]
#[AsTool('discord_get_guild_info', 'Tool that gets Discord server information', method: 'getGuildInfo')]
#[AsTool('discord_create_embed', 'Tool that creates rich embeds for Discord messages', method: 'createEmbed')]
final readonly class DiscordBot
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
     * Send a message to a Discord channel.
     *
     * @param string               $channelId  Discord channel ID
     * @param string               $content    Message content
     * @param array<string, mixed> $embed      Optional rich embed
     * @param array<string, mixed> $components Optional message components (buttons, select menus)
     *
     * @return array{
     *     id: string,
     *     channel_id: string,
     *     content: string,
     *     timestamp: string,
     *     author: array{
     *         id: string,
     *         username: string,
     *         discriminator: string,
     *         avatar: string,
     *         bot: bool,
     *     },
     * }|string
     */
    public function __invoke(
        string $channelId,
        #[With(maximum: 2000)]
        string $content,
        array $embed = [],
        array $components = [],
    ): array|string {
        try {
            $payload = [
                'content' => $content,
            ];

            if (!empty($embed)) {
                $payload['embeds'] = [$embed];
            }

            if (!empty($components)) {
                $payload['components'] = $components;
            }

            $response = $this->httpClient->request('POST', "https://discord.com/api/v10/channels/{$channelId}/messages", [
                'headers' => [
                    'Authorization' => 'Bot '.$this->botToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            if (200 !== $response->getStatusCode()) {
                $errorData = $response->toArray(false);

                return 'Error sending message: '.($errorData['message'] ?? 'Unknown error');
            }

            $data = $response->toArray();

            return [
                'id' => $data['id'],
                'channel_id' => $data['channel_id'],
                'content' => $data['content'],
                'timestamp' => $data['timestamp'],
                'author' => [
                    'id' => $data['author']['id'],
                    'username' => $data['author']['username'],
                    'discriminator' => $data['author']['discriminator'],
                    'avatar' => $data['author']['avatar'],
                    'bot' => $data['author']['bot'],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error sending message: '.$e->getMessage();
        }
    }

    /**
     * Get messages from a Discord channel.
     *
     * @param string $channelId Discord channel ID
     * @param int    $limit     Maximum number of messages to retrieve (1-100)
     * @param string $before    Get messages before this message ID
     * @param string $after     Get messages after this message ID
     * @param string $around    Get messages around this message ID
     *
     * @return array<int, array{
     *     id: string,
     *     channel_id: string,
     *     content: string,
     *     timestamp: string,
     *     edited_timestamp: string|null,
     *     author: array{
     *         id: string,
     *         username: string,
     *         discriminator: string,
     *         avatar: string,
     *         bot: bool,
     *     },
     *     embeds: array<int, array<string, mixed>>,
     *     attachments: array<int, array<string, mixed>>,
     *     mentions: array<int, array<string, mixed>>,
     * }>|string
     */
    public function getMessages(
        string $channelId,
        int $limit = 50,
        string $before = '',
        string $after = '',
        string $around = '',
    ): array|string {
        try {
            $params = [
                'limit' => min(max($limit, 1), 100), // Clamp between 1 and 100
            ];

            if ($before) {
                $params['before'] = $before;
            }
            if ($after) {
                $params['after'] = $after;
            }
            if ($around) {
                $params['around'] = $around;
            }

            $response = $this->httpClient->request('GET', "https://discord.com/api/v10/channels/{$channelId}/messages", [
                'headers' => [
                    'Authorization' => 'Bot '.$this->botToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            if (200 !== $response->getStatusCode()) {
                $errorData = $response->toArray(false);

                return 'Error getting messages: '.($errorData['message'] ?? 'Unknown error');
            }

            $data = $response->toArray();
            $messages = [];

            foreach ($data as $message) {
                $messages[] = [
                    'id' => $message['id'],
                    'channel_id' => $message['channel_id'],
                    'content' => $message['content'],
                    'timestamp' => $message['timestamp'],
                    'edited_timestamp' => $message['edited_timestamp'],
                    'author' => [
                        'id' => $message['author']['id'],
                        'username' => $message['author']['username'],
                        'discriminator' => $message['author']['discriminator'],
                        'avatar' => $message['author']['avatar'],
                        'bot' => $message['author']['bot'],
                    ],
                    'embeds' => $message['embeds'] ?? [],
                    'attachments' => $message['attachments'] ?? [],
                    'mentions' => $message['mentions'] ?? [],
                ];
            }

            return $messages;
        } catch (\Exception $e) {
            return 'Error getting messages: '.$e->getMessage();
        }
    }

    /**
     * Get channels from a Discord guild (server).
     *
     * @param string $guildId Discord guild ID
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     type: int,
     *     position: int,
     *     topic: string|null,
     *     nsfw: bool,
     *     parent_id: string|null,
     *     permission_overwrites: array<int, array<string, mixed>>,
     * }>|string
     */
    public function getChannels(string $guildId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://discord.com/api/v10/guilds/{$guildId}/channels", [
                'headers' => [
                    'Authorization' => 'Bot '.$this->botToken,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                $errorData = $response->toArray(false);

                return 'Error getting channels: '.($errorData['message'] ?? 'Unknown error');
            }

            $data = $response->toArray();
            $channels = [];

            foreach ($data as $channel) {
                $channels[] = [
                    'id' => $channel['id'],
                    'name' => $channel['name'],
                    'type' => $channel['type'],
                    'position' => $channel['position'],
                    'topic' => $channel['topic'] ?? null,
                    'nsfw' => $channel['nsfw'] ?? false,
                    'parent_id' => $channel['parent_id'] ?? null,
                    'permission_overwrites' => $channel['permission_overwrites'] ?? [],
                ];
            }

            return $channels;
        } catch (\Exception $e) {
            return 'Error getting channels: '.$e->getMessage();
        }
    }

    /**
     * Get Discord guild (server) information.
     *
     * @param string $guildId Discord guild ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string|null,
     *     icon: string|null,
     *     splash: string|null,
     *     banner: string|null,
     *     owner_id: string,
     *     member_count: int,
     *     features: array<int, string>,
     *     verification_level: int,
     *     explicit_content_filter: int,
     *     default_message_notifications: int,
     *     mfa_level: int,
     *     premium_tier: int,
     *     premium_subscription_count: int,
     * }|string
     */
    public function getGuildInfo(string $guildId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://discord.com/api/v10/guilds/{$guildId}", [
                'headers' => [
                    'Authorization' => 'Bot '.$this->botToken,
                ],
                'query' => [
                    'with_counts' => 'true',
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                $errorData = $response->toArray(false);

                return 'Error getting guild info: '.($errorData['message'] ?? 'Unknown error');
            }

            $data = $response->toArray();

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? null,
                'splash' => $data['splash'] ?? null,
                'banner' => $data['banner'] ?? null,
                'owner_id' => $data['owner_id'],
                'member_count' => $data['member_count'] ?? 0,
                'features' => $data['features'] ?? [],
                'verification_level' => $data['verification_level'],
                'explicit_content_filter' => $data['explicit_content_filter'],
                'default_message_notifications' => $data['default_message_notifications'],
                'mfa_level' => $data['mfa_level'],
                'premium_tier' => $data['premium_tier'],
                'premium_subscription_count' => $data['premium_subscription_count'] ?? 0,
            ];
        } catch (\Exception $e) {
            return 'Error getting guild info: '.$e->getMessage();
        }
    }

    /**
     * Create a rich embed for Discord messages.
     *
     * @param string               $title       Embed title
     * @param string               $description Embed description
     * @param string               $color       Embed color (hex code without #)
     * @param array<string, mixed> $fields      Embed fields
     * @param string               $footer      Embed footer text
     * @param string               $thumbnail   Thumbnail URL
     * @param string               $image       Image URL
     *
     * @return array<string, mixed>
     */
    public function createEmbed(
        string $title = '',
        string $description = '',
        string $color = '',
        array $fields = [],
        string $footer = '',
        string $thumbnail = '',
        string $image = '',
    ): array {
        $embed = [];

        if ($title) {
            $embed['title'] = $title;
        }

        if ($description) {
            $embed['description'] = $description;
        }

        if ($color) {
            $embed['color'] = hexdec($color);
        }

        if (!empty($fields)) {
            $embed['fields'] = $fields;
        }

        if ($footer) {
            $embed['footer'] = ['text' => $footer];
        }

        if ($thumbnail) {
            $embed['thumbnail'] = ['url' => $thumbnail];
        }

        if ($image) {
            $embed['image'] = ['url' => $image];
        }

        $embed['timestamp'] = date('c'); // ISO 8601 timestamp

        return $embed;
    }

    /**
     * Send a message with a rich embed.
     *
     * @param string               $channelId Discord channel ID
     * @param string               $content   Message content
     * @param array<string, mixed> $embedData Embed data
     *
     * @return array<string, mixed>|string
     */
    public function sendEmbedMessage(string $channelId, string $content, array $embedData): array|string
    {
        return $this->__invoke($channelId, $content, $embedData);
    }
}
