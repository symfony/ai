<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * OpenAI /v1/chat/completions contract handler.
 *
 * Provides an alternative invocation path for the same GPT models the
 * {@see ResponsesHandler} serves — the catalog declares both endpoints
 * on the {@see Model}, and the user picks one via `$options['endpoint']`.
 * This is the "single model, multiple contracts" case that the previous
 * design, which routed by `Gpt::class instanceof`, could not satisfy.
 *
 * Reshapes the upstream Responses-API-style payload (`input[]`) emitted by
 * {@see OpenAiContract} into the chat-completions shape (`messages[]`); the
 * conversion is intentionally minimal — text, image_url and tool_call
 * content types are translated, anything else passes through unchanged so
 * upstream consumers can see what the API rejected.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionsClient implements EndpointClientInterface
{
    public const ENDPOINT = 'openai.chat_completions';

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

        $body = array_merge($options, ['model' => $model->getName()], $payload);

        if (isset($body['input']) && !isset($body['messages'])) {
            $body['messages'] = $this->convertInputToMessages($body['input']);
            unset($body['input']);
        }

        // The Responses API stuffs structured output into text.format; chat
        // completions uses response_format directly, so undo the Responses
        // reshape if a previous handler applied it.
        if (isset($body['text']['format']['type'])) {
            $format = $body['text']['format'];
            $body['response_format'] = [
                'type' => $format['type'],
                'json_schema' => array_diff_key($format, ['type' => true]),
            ];
            unset($body['text']);
        }

        $envelope = new RequestEnvelope(
            payload: $body,
            path: '/v1/chat/completions',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($raw));
        }

        $data = $raw->getData();

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error "%s"-%s (%s): "%s".', $data['error']['code'] ?? '-', $data['error']['type'] ?? '-', $data['error']['param'] ?? '-', $data['error']['message'] ?? '-'));
        }

        if (!isset($data['choices']) || [] === $data['choices']) {
            throw new RuntimeException('Response does not contain any choices.');
        }

        $results = [];
        foreach ($data['choices'] as $choice) {
            $message = $choice['message'] ?? [];

            if (isset($message['tool_calls']) && [] !== $message['tool_calls']) {
                $toolCalls = [];
                foreach ($message['tool_calls'] as $toolCall) {
                    $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true, flags: \JSON_THROW_ON_ERROR);
                    $toolCalls[] = new ToolCall($toolCall['id'], $toolCall['function']['name'], $arguments);
                }
                $results[] = new ToolCallResult($toolCalls);
                continue;
            }

            $results[] = new TextResult($message['content'] ?? '');
        }

        return 1 === \count($results) ? $results[0] : new ChoiceResult($results);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new class implements TokenUsageExtractorInterface {
            public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsage
            {
                if ($options['stream'] ?? false) {
                    return null;
                }

                $usage = $rawResult->getData()['usage'] ?? null;
                if (null === $usage) {
                    return null;
                }

                return new TokenUsage(
                    promptTokens: $usage['prompt_tokens'] ?? null,
                    completionTokens: $usage['completion_tokens'] ?? null,
                );
            }
        };
    }

    /**
     * @param list<array<string, mixed>> $input
     *
     * @return list<array<string, mixed>>
     */
    private function convertInputToMessages(array $input): array
    {
        $messages = [];
        foreach ($input as $item) {
            if ('message' !== ($item['type'] ?? 'message')) {
                // Pass through unknown items unchanged so the API can complain visibly.
                $messages[] = $item;
                continue;
            }

            $messages[] = [
                'role' => $item['role'] ?? 'user',
                'content' => $this->convertContent($item['content'] ?? ''),
            ];
        }

        return $messages;
    }

    /**
     * @param string|list<array<string, mixed>> $content
     *
     * @return string|list<array<string, mixed>>
     */
    private function convertContent(string|array $content): string|array
    {
        if (\is_string($content)) {
            return $content;
        }

        $allText = true;
        $parts = [];
        foreach ($content as $part) {
            $type = $part['type'] ?? null;
            if ('input_text' === $type || 'output_text' === $type || 'text' === $type) {
                $parts[] = ['type' => 'text', 'text' => $part['text'] ?? ''];
                continue;
            }
            if ('input_image' === $type) {
                $parts[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $part['image_url'] ?? $part['url'] ?? ''],
                ];
                $allText = false;
                continue;
            }
            $parts[] = $part;
            $allText = false;
        }

        // Collapse to a plain string for the simple text-only case so requests
        // hitting older chat-completions endpoints stay maximally compatible.
        if ($allText && 1 === \count($parts)) {
            return $parts[0]['text'];
        }

        return $parts;
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];

        foreach ($result->getDataStream() as $event) {
            if (isset($event['usage'])) {
                yield $this->buildUsage($event['usage']);
            }

            $delta = $event['choices'][0]['delta'] ?? null;
            if (null === $delta) {
                continue;
            }

            if (isset($delta['content']) && '' !== $delta['content']) {
                yield new TextDelta($delta['content']);
            }

            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $toolCallDelta) {
                    $index = $toolCallDelta['index'] ?? 0;
                    $toolCalls[$index] ??= ['id' => null, 'name' => null, 'arguments' => ''];

                    if (isset($toolCallDelta['id'])) {
                        $toolCalls[$index]['id'] = $toolCallDelta['id'];
                    }
                    if (isset($toolCallDelta['function']['name'])) {
                        $toolCalls[$index]['name'] = $toolCallDelta['function']['name'];
                    }
                    if (isset($toolCallDelta['function']['arguments'])) {
                        $toolCalls[$index]['arguments'] .= $toolCallDelta['function']['arguments'];
                    }
                }
            }

            $finish = $event['choices'][0]['finish_reason'] ?? null;
            if ('tool_calls' === $finish && [] !== $toolCalls) {
                $finalized = [];
                foreach ($toolCalls as $tc) {
                    $arguments = '' !== $tc['arguments']
                        ? json_decode($tc['arguments'], true, flags: \JSON_THROW_ON_ERROR)
                        : [];
                    $finalized[] = new ToolCall($tc['id'] ?? '', $tc['name'] ?? '', $arguments);
                }
                yield new ToolCallComplete($finalized);
                $toolCalls = [];
            }
        }
    }

    /**
     * @param array<string, mixed> $usage
     */
    private function buildUsage(array $usage): TokenUsage
    {
        return new TokenUsage(
            promptTokens: $usage['prompt_tokens'] ?? null,
            completionTokens: $usage['completion_tokens'] ?? null,
        );
    }
}
