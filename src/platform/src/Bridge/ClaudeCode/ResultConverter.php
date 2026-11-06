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

use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\MalformedToolCallException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\CustomToolCallResult;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
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
            $results[] = $this->convertExecutedToolCall($toolCall);
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

    /**
     * The CLI already ran the tool, so the call is reported as resolved, not as a tool call for the caller to run.
     *
     * @param array{id: string, name: string, arguments?: array<string, mixed>, output?: string|null, is_error?: bool|null, server?: string, tool?: string} $toolCall
     */
    private function convertExecutedToolCall(array $toolCall): McpCallResult|CustomToolCallResult
    {
        $arguments = json_encode((object) ($toolCall['arguments'] ?? []), \JSON_THROW_ON_ERROR);
        $failed = $toolCall['is_error'] ?? null;
        $status = null === $failed ? null : ($failed ? 'failed' : 'completed');

        [$server, $tool] = isset($toolCall['server'], $toolCall['tool']) ? [$toolCall['server'], $toolCall['tool']] : $this->splitMcpToolName($toolCall['name']);

        if (null !== $server && null !== $tool) {
            $output = $toolCall['output'] ?? null;

            return new McpCallResult($server, $tool, $arguments, true === $failed ? null : $output, true === $failed ? $output : null, $toolCall['id'], $status);
        }

        return new CustomToolCallResult($toolCall['name'], $arguments, $toolCall['id'], $status);
    }

    /**
     * Splits "mcp__<server>__<tool>" at the first "__", for a tool call whose server is not known from the init event.
     *
     * @return array{string, string}|array{null, null}
     */
    private function splitMcpToolName(string $name): array
    {
        return 1 === preg_match('/^mcp__(.+?)__(.+)$/', $name, $match) ? [$match[1], $match[2]] : [null, null];
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $currentToolCall = null;
        $currentToolCallJson = '';
        $inMessage = false;

        foreach ($result->getDataStream() as $data) {
            $type = $data['type'] ?? '';
            $eventType = $data['event']['type'] ?? null;

            if ('stream_event' === $type && 'error' === $eventType) {
                throw new RuntimeException($data['event']['error']['message'] ?? 'Unknown Claude Code stream error.');
            }

            if ('stream_event' === $type && 'message_start' === $eventType) {
                $inMessage = true;
            }

            // Handle streaming text deltas (wrapped in stream_event)
            if ('stream_event' === $type
                && 'content_block_delta' === $eventType
                && 'text_delta' === ($data['event']['delta']['type'] ?? '')
            ) {
                yield new TextDelta($data['event']['delta']['text']);
            }

            // Handle tool_use content block start
            if ('stream_event' === $type
                && 'content_block_start' === $eventType
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
                && 'content_block_delta' === $eventType
                && 'input_json_delta' === ($data['event']['delta']['type'] ?? '')
            ) {
                $partialJson = $data['event']['delta']['partial_json'] ?? '';
                $currentToolCallJson .= $partialJson;
                if (null !== $currentToolCall) {
                    yield new ToolInputDelta($currentToolCall['id'], $currentToolCall['name'], $partialJson);
                }
            }

            // Handle content block stop - the CLI runs the tool itself, so no ToolCallComplete asks the caller to run it
            if ('stream_event' === $type
                && 'content_block_stop' === $eventType
                && null !== $currentToolCall
            ) {
                // the CLI ran the tool, so the arguments are not returned; decoding them still raises
                // MalformedToolCallException for malformed arguments
                if ('' !== $currentToolCallJson) {
                    try {
                        json_decode($currentToolCallJson, flags: \JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        throw new MalformedToolCallException(\sprintf('Claude Code returned malformed JSON arguments for the "%s" tool: "%s"', $currentToolCall['name'], $e->getMessage()), 0, $e);
                    }
                }
                $currentToolCall = null;
                $currentToolCallJson = '';
            }

            if ('stream_event' === $type
                && 'message_stop' === $eventType
            ) {
                $inMessage = false;
            }
        }

        if ($inMessage) {
            throw new IncompleteStreamException('Claude Code stream ended before message_stop.');
        }
    }
}
