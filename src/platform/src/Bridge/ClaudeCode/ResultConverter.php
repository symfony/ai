<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Converts Claude Code CLI stream-json output into platform result objects.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof ClaudeCode;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if ([] === $data) {
            throw new RuntimeException('Claude Code CLI did not return any result.');
        }

        if (isset($data['is_error']) && true === $data['is_error']) {
            throw new RuntimeException(\sprintf('Claude Code CLI error: "%s"', $data['result'] ?? 'Unknown error'));
        }

        if (!isset($data['result'])) {
            throw new RuntimeException('Claude Code CLI result does not contain a "result" field.');
        }

        $results = [];
        foreach ($data['tool_calls'] ?? [] as $toolCall) {
            $results[] = new ToolCallResult([new ToolCall(
                $toolCall['id'],
                $toolCall['name'],
                $toolCall['arguments'] ?? [],
            )]);
        }

        $results[] = new TextResult($data['result']);

        if (1 === \count($results)) {
            return $results[0];
        }

        return new MultiPartResult($results);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $toolCalls = [];
        $currentToolCall = null;
        $currentToolCallJson = '';

        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';

            // Handle streaming text deltas (wrapped in stream_event)
            if ('stream_event' === $type
                && 'content_block_delta' === ($data['event']['type'] ?? '')
                && 'text_delta' === ($data['event']['delta']['type'] ?? '')
            ) {
                yield new TextDelta($data['event']['delta']['text']);
            }

            // Handle tool_use content block start
            if ('stream_event' === $type
                && 'content_block_start' === ($data['event']['type'] ?? '')
                && 'tool_use' === ($data['event']['content_block']['type'] ?? '')
            ) {
                $currentToolCall = [
                    'id' => $data['event']['content_block']['id'],
                    'name' => $data['event']['content_block']['name'],
                ];
                $currentToolCallJson = '';
                yield new ToolCallStart($currentToolCall['id'], $currentToolCall['name']);
            }

            // Handle tool_use input JSON deltas
            if ('stream_event' === $type
                && 'content_block_delta' === ($data['event']['type'] ?? '')
                && 'input_json_delta' === ($data['event']['delta']['type'] ?? '')
            ) {
                $partialJson = $data['event']['delta']['partial_json'] ?? '';
                $currentToolCallJson .= $partialJson;
                if (null !== $currentToolCall) {
                    yield new ToolInputDelta($currentToolCall['id'], $currentToolCall['name'], $partialJson);
                }
            }

            // Handle content block stop - finalize current tool call
            if ('stream_event' === $type
                && 'content_block_stop' === ($data['event']['type'] ?? '')
                && null !== $currentToolCall
            ) {
                $input = '' !== $currentToolCallJson
                    ? json_decode($currentToolCallJson, true, flags: \JSON_THROW_ON_ERROR)
                    : [];
                $toolCalls[] = new ToolCall(
                    $currentToolCall['id'],
                    $currentToolCall['name'],
                    $input
                );
                $currentToolCall = null;
                $currentToolCallJson = '';
            }

            // Handle message stop - yield tool calls if any were collected
            if ('stream_event' === $type
                && 'message_stop' === ($data['event']['type'] ?? '')
                && [] !== $toolCalls
            ) {
                yield new ToolCallComplete($toolCalls);
                $toolCalls = [];
            }
        }
    }
}
