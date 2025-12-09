<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway\Llm;

use Symfony\AI\Platform\Bridge\Scaleway\Scaleway;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class ModelClient implements ModelClientInterface
{
    private const RESPONSES_MODEL = 'gpt-oss-120b';
    private const BASE_URL = 'https://api.scaleway.ai/v1';

    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Scaleway;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $body = \is_array($payload) ? $payload : ['input' => $payload];
        $body = array_merge($options, $body);
        $body['model'] = $model->getName();

        if (self::RESPONSES_MODEL === $model->getName()) {
            $body = $this->convertMessagesToResponsesInput($body);
            $body = $this->convertResponseFormat($body);
            $body = $this->convertTools($body);
            $url = self::BASE_URL.'/responses';
        } else {
            $url = self::BASE_URL.'/chat/completions';
        }

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'auth_bearer' => $this->apiKey,
            'json' => $body,
        ]));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function convertMessagesToResponsesInput(array $body): array
    {
        if (!isset($body['messages'])) {
            return $body;
        }

        $input = [];
        foreach ($body['messages'] as $message) {
            $converted = $this->convertMessage($message);
            if (array_is_list($converted) && isset($converted[0]) && \is_array($converted[0])) {
                // Multiple items returned (e.g., assistant message with tool calls)
                $input = array_merge($input, $converted);
            } else {
                $input[] = $converted;
            }
        }

        $body['input'] = $input;
        unset($body['messages']);

        return $body;
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function convertMessage(array $message): array
    {
        $role = $message['role'] ?? 'user';

        // Convert tool result messages to function_call_output format
        if ('tool' === $role) {
            return [
                'type' => 'function_call_output',
                'call_id' => $message['tool_call_id'] ?? '',
                'output' => $message['content'] ?? '',
            ];
        }

        // Convert assistant messages with tool_calls
        if ('assistant' === $role && isset($message['tool_calls'])) {
            $items = [];

            // Add text content if present
            if (isset($message['content']) && '' !== $message['content']) {
                $items[] = [
                    'role' => 'assistant',
                    'content' => [['type' => 'input_text', 'text' => $message['content']]],
                ];
            }

            // Add function calls
            foreach ($message['tool_calls'] as $toolCall) {
                $items[] = [
                    'type' => 'function_call',
                    'call_id' => $toolCall['id'] ?? '',
                    'name' => $toolCall['function']['name'] ?? '',
                    'arguments' => $toolCall['function']['arguments'] ?? '{}',
                ];
            }

            return $items;
        }

        // Convert regular messages
        $content = $message['content'] ?? '';

        if (\is_string($content)) {
            $content = [['type' => 'input_text', 'text' => $content]];
        }

        if (\is_array($content)) {
            if (!array_is_list($content)) {
                $content = [$content];
            }

            $content = array_map($this->convertContentPart(...), $content);
        }

        return [
            'role' => $role,
            'content' => $content,
        ];
    }

    /**
     * @param array<string, mixed>|string $contentPart
     *
     * @return array<string, mixed>
     */
    private function convertContentPart(array|string $contentPart): array
    {
        if (\is_string($contentPart)) {
            return ['type' => 'input_text', 'text' => $contentPart];
        }

        return match ($contentPart['type'] ?? null) {
            'text' => ['type' => 'input_text', 'text' => $contentPart['text'] ?? ''],
            'input_text' => $contentPart,
            'input_image', 'image_url' => [
                'type' => 'input_image',
                'image_url' => \is_array($contentPart['image_url'] ?? null) ? ($contentPart['image_url']['url'] ?? '') : ($contentPart['image_url'] ?? ''),
                ...isset($contentPart['detail']) ? ['detail' => $contentPart['detail']] : [],
            ],
            default => ['type' => 'input_text', 'text' => $contentPart['text'] ?? ''],
        };
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function convertResponseFormat(array $body): array
    {
        if (!isset($body[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            return $body;
        }

        $schema = $body[PlatformSubscriber::RESPONSE_FORMAT]['json_schema'];
        $body['text']['format'] = $schema;
        $body['text']['format']['name'] = $schema['name'];
        $body['text']['format']['type'] = $body[PlatformSubscriber::RESPONSE_FORMAT]['type'];

        unset($body[PlatformSubscriber::RESPONSE_FORMAT]);

        return $body;
    }

    /**
     * Converts tools from Chat Completions format to Responses API format.
     *
     * Chat Completions: {"type": "function", "function": {"name": "...", "description": "...", "parameters": {...}}}
     * Responses API:    {"type": "function", "name": "...", "description": "...", "parameters": {...}}
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function convertTools(array $body): array
    {
        if (!isset($body['tools'])) {
            return $body;
        }

        $body['tools'] = array_map(static function (array $tool): array {
            if ('function' !== ($tool['type'] ?? null) || !isset($tool['function'])) {
                return $tool;
            }

            return [
                'type' => 'function',
                'name' => $tool['function']['name'] ?? '',
                'description' => $tool['function']['description'] ?? '',
                ...isset($tool['function']['parameters']) ? ['parameters' => $tool['function']['parameters']] : [],
            ];
        }, $body['tools']);

        return $body;
    }
}
