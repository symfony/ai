<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TransportInterface;

/**
 * Ollama `/api/chat` contract handler.
 *
 * Owns option flattening (Ollama's nested `options` shape vs the top-level
 * keys it accepts directly) and structured-output reshape
 * (`response_format` → `format`). The body's `stream` defaults to false
 * because Ollama defaults to true and breaks naive non-streaming callers.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatClient implements EndpointClientInterface
{
    public const ENDPOINT = 'ollama.chat';

    private const TOP_LEVEL_KEYS = [
        'stream',
        'format',
        'keep_alive',
        'tools',
        'think',
        'logprobs',
        'top_logprobs',
    ];

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $options['stream'] ??= false;

        if (isset($options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'])) {
            $options['format'] = $options[PlatformSubscriber::RESPONSE_FORMAT]['json_schema']['schema'];
            unset($options[PlatformSubscriber::RESPONSE_FORMAT]);
        }

        $options = self::splitOptions($options, self::TOP_LEVEL_KEYS);

        $envelope = new RequestEnvelope(
            payload: array_merge($options, $payload),
            path: '/api/chat',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($raw));
        }

        $data = $raw->getData();

        if (!isset($data['message'])) {
            throw new RuntimeException('Response does not contain message.');
        }

        if (!isset($data['message']['content'])) {
            throw new RuntimeException('Message does not contain content.');
        }

        $toolCalls = [];
        foreach ($data['message']['tool_calls'] ?? [] as $id => $toolCall) {
            $toolCalls[] = new ToolCall($id, $toolCall['function']['name'], $toolCall['function']['arguments']);
        }

        if ([] !== $toolCalls) {
            return new ToolCallResult($toolCalls);
        }

        return new TextResult($data['message']['content']);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * Ollama accepts a small set of "top level" body keys plus a nested
     * `options` map for everything else. Symfony AI passes flat options;
     * this method routes each key to the correct slot.
     *
     * @param array<string, mixed> $options
     * @param list<string>         $topLevelKeys
     *
     * @return array<string, mixed>
     */
    private static function splitOptions(array $options, array $topLevelKeys): array
    {
        $topLevelOptions = [];
        $nested = $options['options'] ?? [];

        foreach ($options as $key => $value) {
            if ('options' === $key) {
                continue;
            }

            if (\in_array($key, $topLevelKeys, true)) {
                $topLevelOptions[$key] = $value;
            } else {
                $nested[$key] ??= $value;
            }
        }

        if ([] !== $nested) {
            $topLevelOptions['options'] = $nested;
        }

        return $topLevelOptions;
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];

        foreach ($result->getDataStream() as $data) {
            if (isset($data['message']['tool_calls'])) {
                foreach ($data['message']['tool_calls'] as $id => $toolCall) {
                    $toolCalls[] = new ToolCall($id, $toolCall['function']['name'], $toolCall['function']['arguments']);
                }
            }

            if (isset($data['message']['thinking']) && '' !== $data['message']['thinking']) {
                yield new ThinkingDelta($data['message']['thinking']);
            }

            if (isset($data['message']['content']) && '' !== $data['message']['content']) {
                yield new TextDelta($data['message']['content']);
            }

            if ([] !== $toolCalls && ($data['done'] ?? false)) {
                yield new ToolCallComplete($toolCalls);
            }

            if (isset($data['prompt_eval_count'], $data['eval_count'])) {
                yield new TokenUsage(
                    promptTokens: $data['prompt_eval_count'],
                    completionTokens: $data['eval_count'],
                );
            }
        }
    }
}
