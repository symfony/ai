<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Completions;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * Shared streaming and tool-call conversion logic for OpenAI-compatible completions APIs.
 *
 * Used by bridges whose response format follows the OpenAI chat completions schema
 * (choices[].delta.tool_calls, choices[].finish_reason, etc.).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
trait CompletionsConversionTrait
{
    protected function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        $reasoning = '';

        foreach ($result->getDataStream() as $data) {
            if (isset($data['usage'])) {
                yield $this->convertStreamUsage($data['usage']);
            }

            if ($this->streamIsToolCall($data)) {
                yield from $this->yieldToolCallDeltas($toolCalls, $data);
                $toolCalls = $this->convertStreamToToolCalls($toolCalls, $data);
            }

            if ([] !== $toolCalls && $this->isToolCallsStreamFinished($data)) {
                yield new ToolCallComplete(...array_map($this->convertToolCall(...), $toolCalls));
            }

            $reasoningContent = $data['choices'][0]['delta']['reasoning_content']
                ?? $data['choices'][0]['delta']['reasoning'] ?? null;
            if (null !== $reasoningContent && '' !== $reasoningContent) {
                $reasoning .= $reasoningContent;
                yield new ThinkingDelta($reasoningContent);
                continue;
            }

            if ('' !== $reasoning && isset($data['choices'][0]['delta']['content']) && '' !== $data['choices'][0]['delta']['content']) {
                yield new ThinkingComplete($reasoning);
                $reasoning = '';
            }

            if (!isset($data['choices'][0]['delta']['content'])) {
                continue;
            }

            yield new TextDelta($data['choices'][0]['delta']['content']);
        }

        if ('' !== $reasoning) {
            yield new ThinkingComplete($reasoning);
        }
    }

    /**
     * @param array<string, mixed> $usage
     */
    protected function convertStreamUsage(array $usage): TokenUsage
    {
        return (new TokenUsageExtractor())->extractFromArray($usage);
    }

    /**
     * @param array<string, mixed> $toolCalls
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function convertStreamToToolCalls(array $toolCalls, array $data): array
    {
        if (!isset($data['choices'][0]['delta']['tool_calls'])) {
            return $toolCalls;
        }

        foreach ($data['choices'][0]['delta']['tool_calls'] as $i => $toolCall) {
            if (isset($toolCall['id'])) {
                // initialize tool call
                $toolCalls[$i] = [
                    'id' => $toolCall['id'],
                    'function' => $toolCall['function'],
                ];
                continue;
            }

            // add arguments delta to tool call
            if (isset($toolCall['function']['arguments'])) {
                if (!isset($toolCalls[$i]['function']['arguments'])) {
                    $toolCalls[$i]['function']['arguments'] = '';
                }

                $toolCalls[$i]['function']['arguments'] .= $toolCall['function']['arguments'];
            }
        }

        return $toolCalls;
    }

    /**
     * @param array<string, mixed> $toolCalls Already-accumulated tool calls (before this chunk)
     * @param array<string, mixed> $data
     *
     * @return \Generator<ToolCallStart|ToolInputDelta>
     */
    protected function yieldToolCallDeltas(array $toolCalls, array $data): \Generator
    {
        foreach ($data['choices'][0]['delta']['tool_calls'] ?? [] as $i => $toolCall) {
            if (isset($toolCall['id'])) {
                yield new ToolCallStart($toolCall['id'], $toolCall['function']['name']);
            } elseif (isset($toolCall['function']['arguments'])) {
                yield new ToolInputDelta($toolCalls[$i]['id'] ?? '', $toolCalls[$i]['function']['name'] ?? '', $toolCall['function']['arguments']);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function streamIsToolCall(array $data): bool
    {
        return isset($data['choices'][0]['delta']['tool_calls']);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function isToolCallsStreamFinished(array $data): bool
    {
        return isset($data['choices'][0]['finish_reason']) && 'tool_calls' === $data['choices'][0]['finish_reason'];
    }

    /**
     * @param array{
     *     index: int,
     *     message: array{
     *         role: 'assistant',
     *         content: ?string,
     *         tool_calls: list<array{
     *             id: string,
     *             type: 'function',
     *             function: array{
     *                 name: string,
     *                 arguments: string
     *             },
     *         }>,
     *         refusal: ?mixed
     *     },
     *     logprobs: string,
     *     finish_reason: 'stop'|'length'|'tool_calls'|'content_filter',
     * } $choice
     */
    protected function convertChoice(array $choice): ToolCallResult|TextResult
    {
        if ('tool_calls' === $choice['finish_reason']) {
            return new ToolCallResult(...array_map([$this, 'convertToolCall'], $choice['message']['tool_calls']));
        }

        if (\in_array($choice['finish_reason'], ['stop', 'length'], true)) {
            return new TextResult($choice['message']['content']);
        }

        throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $choice['finish_reason']));
    }

    /**
     * @param array{
     *     id: string,
     *     type: 'function',
     *     function: array{
     *         name: string,
     *         arguments?: string
     *     }
     * } $toolCall
     */
    protected function convertToolCall(array $toolCall): ToolCall
    {
        if (isset($toolCall['function']['arguments']) && '' !== $toolCall['function']['arguments']) {
            $arguments = json_decode($toolCall['function']['arguments'], true, flags: \JSON_THROW_ON_ERROR);
        } else {
            $arguments = [];
        }

        return new ToolCall($toolCall['id'], $toolCall['function']['name'], $arguments);
    }
}
