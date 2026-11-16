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
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Process\Process;

/**
 * Wraps a Symfony Process running the Claude Code CLI as a RawResultInterface.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RawProcessResult implements RawResultInterface
{
    private const TOOL_CALLS = 'tool_calls';

    public function __construct(
        private readonly Process $process,
    ) {
    }

    /**
     * Waits for the process to finish, parses all output lines, and returns the final
     * result message (type=result).
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->process->wait();

        if (!$this->process->isSuccessful()) {
            throw $this->createFailureException();
        }

        $output = $this->process->getOutput();
        $result = [];
        $events = [];

        foreach (explode(\PHP_EOL, $output) as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $decoded = json_decode($line, true);

            if (null === $decoded) {
                continue;
            }

            $events[] = $decoded;

            if (isset($decoded['type']) && 'result' === $decoded['type']) {
                $result = $decoded;
            }
        }

        $toolCalls = $this->extractToolCalls($events);
        if ([] !== $toolCalls && [] !== $result) {
            $result[self::TOOL_CALLS] = $toolCalls;
        }

        return $result;
    }

    /**
     * Polls the process for incremental output, yielding each complete JSON line.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function getDataStream(): \Generator
    {
        $buffer = '';

        while ($this->process->isRunning()) {
            $incrementalOutput = $this->process->getIncrementalOutput();

            if ('' === $incrementalOutput) {
                usleep(10000); // 10ms polling interval
                continue;
            }

            $buffer .= $incrementalOutput;
            $lines = explode(\PHP_EOL, $buffer);

            // Keep the last (potentially incomplete) line in the buffer
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);

                if ('' === $line) {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (null !== $decoded) {
                    yield $decoded;
                }
            }
        }

        // Process remaining output after process finishes
        $buffer .= $this->process->getIncrementalOutput();

        foreach (explode(\PHP_EOL, $buffer) as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $decoded = json_decode($line, true);

            if (null !== $decoded) {
                yield $decoded;
            }
        }

        if (!$this->process->isSuccessful()) {
            throw $this->createFailureException();
        }
    }

    public function getObject(): Process
    {
        return $this->process;
    }

    /**
     * The CLI reports why it stopped in the final `type=result` event on stdout, while
     * stderr often only carries unrelated warnings, e.g. about the active auth source.
     */
    private function createFailureException(): RuntimeException
    {
        $reason = $this->extractResultError() ?? trim($this->process->getErrorOutput());

        if ('' === $reason) {
            $reason = \sprintf('exit code %d', $this->process->getExitCode() ?? -1);
        }

        return new RuntimeException(\sprintf('Claude Code CLI process failed: "%s"', $reason));
    }

    private function extractResultError(): ?string
    {
        foreach (array_reverse(explode(\PHP_EOL, $this->process->getOutput())) as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            $decoded = json_decode($line, true);

            if (!\is_array($decoded) || 'result' !== ($decoded['type'] ?? null)) {
                continue;
            }

            $errors = \is_array($decoded['errors'] ?? null) ? array_filter($decoded['errors'], 'is_string') : [];

            if ([] !== $errors) {
                return implode(', ', $errors);
            }

            $subtype = $decoded['subtype'] ?? null;

            return \is_string($subtype) && '' !== $subtype ? $subtype : null;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @return list<array{id: string, name: string, arguments: array<string, mixed>, output: string|null, is_error: bool|null, server?: string, tool?: string}>
     */
    private function extractToolCalls(array $events): array
    {
        $toolCalls = [];
        $started = [];
        $seenIds = [];

        foreach ($events as $index => $event) {
            $type = $event['type'] ?? null;
            if ('assistant' === $type) {
                $message = $event['message'] ?? null;
                if (\is_array($message)) {
                    $this->collectCompletedToolUsesFromMessage($toolCalls, $seenIds, $message);
                }

                continue;
            }

            if ('stream_event' !== $type) {
                continue;
            }

            $streamEvent = $event['event'] ?? null;
            if (!\is_array($streamEvent)) {
                continue;
            }

            $streamType = $streamEvent['type'] ?? null;
            $key = (string) ($streamEvent['index'] ?? $index);

            if ('content_block_start' === $streamType) {
                $contentBlock = $streamEvent['content_block'] ?? null;
                if (\is_array($contentBlock) && 'tool_use' === ($contentBlock['type'] ?? null)) {
                    $started[$key] = [
                        'id' => isset($contentBlock['id']) && \is_string($contentBlock['id']) ? $contentBlock['id'] : '',
                        'name' => (string) ($contentBlock['name'] ?? ''),
                        'arguments' => \is_array($contentBlock['input'] ?? null) ? $contentBlock['input'] : [],
                        'partial_input' => '',
                    ];
                }

                continue;
            }

            if ('content_block_delta' === $streamType && isset($started[$key])) {
                $delta = $streamEvent['delta'] ?? null;
                if (\is_array($delta) && 'input_json_delta' === ($delta['type'] ?? null) && \is_string($delta['partial_json'] ?? null)) {
                    $started[$key]['partial_input'] .= $delta['partial_json'];
                }

                continue;
            }

            if ('content_block_stop' === $streamType && isset($started[$key])) {
                $toolCall = $started[$key];
                unset($started[$key]);

                if ([] === $toolCall['arguments'] && '' !== $toolCall['partial_input']) {
                    try {
                        $decoded = json_decode($toolCall['partial_input'], true, 512, \JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $decoded = null;
                    }

                    if (\is_array($decoded)) {
                        $toolCall['arguments'] = $decoded;
                    }
                }

                unset($toolCall['partial_input']);

                // the CLI writes the assistant message with the call before the block stops, so the call may be known already
                if ('' !== $toolCall['name'] && '' !== $toolCall['id'] && !isset($seenIds[$toolCall['id']])) {
                    $toolCalls[] = $toolCall;
                    $seenIds[$toolCall['id']] = true;
                }
            }
        }

        return $this->attachMcpServers($this->attachToolResults($toolCalls, $events), $events);
    }

    /**
     * Adds the server and tool of each MCP tool call. The CLI names such a tool "mcp__<server>__<tool>", with the
     * server and tool names rewritten as {@see self::toolNamePart()} does. A server name may contain "__" itself, so
     * the name is matched against the servers of the init event, the longest first. A call that matches none of them
     * keeps only its name. Two servers the CLI writes the same way cannot be told apart, the last one listed is used.
     *
     * @param list<array{id: string, name: string, arguments: array<string, mixed>, output: string|null, is_error: bool|null}> $toolCalls
     * @param list<array<string, mixed>>                                                                                       $events
     *
     * @return list<array{id: string, name: string, arguments: array<string, mixed>, output: string|null, is_error: bool|null, server?: string, tool?: string}>
     */
    private function attachMcpServers(array $toolCalls, array $events): array
    {
        $servers = [];
        foreach ($events as $event) {
            if ('system' !== ($event['type'] ?? null) || 'init' !== ($event['subtype'] ?? null) || !\is_array($event['mcp_servers'] ?? null)) {
                continue;
            }

            foreach ($event['mcp_servers'] as $server) {
                if (\is_array($server) && \is_string($server['name'] ?? null) && '' !== $server['name']) {
                    $servers['mcp__'.self::toolNamePart($server['name']).'__'] = $server['name'];
                }
            }
        }

        uksort($servers, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        foreach ($toolCalls as $index => $toolCall) {
            foreach ($servers as $prefix => $server) {
                if (str_starts_with($toolCall['name'], $prefix) && \strlen($toolCall['name']) > \strlen($prefix)) {
                    $toolCalls[$index] += ['server' => $server, 'tool' => substr($toolCall['name'], \strlen($prefix))];

                    break;
                }
            }
        }

        return $toolCalls;
    }

    /**
     * Rewrites a name as the CLI does in tool names: every character other than an ASCII letter, a digit, "_" or "-"
     * becomes "_", one per UTF-16 code unit, so a character outside the Basic Multilingual Plane becomes "__". For a
     * name that starts with "claude.ai ", runs of "_" become one and leading and trailing "_" are removed.
     */
    private static function toolNamePart(string $name): string
    {
        $part = preg_replace_callback('/[^a-zA-Z0-9_-]/u', static fn (array $match): string => 4 === \strlen($match[0]) ? '__' : '_', $name) ?? $name;

        if (str_starts_with($name, 'claude.ai ')) {
            $part = trim(preg_replace('/_+/', '_', $part) ?? $part, '_');
        }

        return $part;
    }

    /**
     * Adds what the CLI reported in the `tool_result` block of each tool it ran, null when it reported nothing.
     *
     * @param list<array{id: string, name: string, arguments: array<string, mixed>}> $toolCalls
     * @param list<array<string, mixed>>                                             $events
     *
     * @return list<array{id: string, name: string, arguments: array<string, mixed>, output: string|null, is_error: bool|null}>
     */
    private function attachToolResults(array $toolCalls, array $events): array
    {
        $toolResults = [];
        foreach ($events as $event) {
            $message = 'user' === ($event['type'] ?? null) ? ($event['message'] ?? null) : null;
            $content = \is_array($message) ? ($message['content'] ?? null) : null;
            if (!\is_array($content)) {
                continue;
            }

            foreach ($content as $block) {
                if (\is_array($block) && 'tool_result' === ($block['type'] ?? null) && \is_string($block['tool_use_id'] ?? null)) {
                    $toolResults[$block['tool_use_id']] = $block;
                }
            }
        }

        return array_map(fn (array $toolCall): array => $toolCall + [
            'output' => isset($toolResults[$toolCall['id']]) ? $this->extractText($toolResults[$toolCall['id']]['content'] ?? null) : null,
            'is_error' => isset($toolResults[$toolCall['id']]) ? true === ($toolResults[$toolCall['id']]['is_error'] ?? false) : null,
        ], $toolCalls);
    }

    private function extractText(mixed $content): ?string
    {
        if (\is_string($content)) {
            return $content;
        }

        if (!\is_array($content)) {
            return null;
        }

        $text = '';
        foreach ($content as $block) {
            if (\is_array($block) && 'text' === ($block['type'] ?? null) && \is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }

        return $text;
    }

    /**
     * @param list<array{id: string, name: string, arguments: array<string, mixed>}> $toolCalls
     * @param array<string, true>                                                    $seenIds
     * @param array<string, mixed>                                                   $message
     */
    private function collectCompletedToolUsesFromMessage(array &$toolCalls, array &$seenIds, array $message): void
    {
        $content = $message['content'] ?? null;
        if (!\is_array($content)) {
            return;
        }

        foreach ($content as $block) {
            if (!\is_array($block) || 'tool_use' !== ($block['type'] ?? null) || !\is_string($block['name'] ?? null) || '' === $block['name']) {
                continue;
            }

            $id = isset($block['id']) && \is_string($block['id']) ? $block['id'] : '';
            if ('' === $id || isset($seenIds[$id])) {
                continue;
            }

            /** @var array<string, mixed> $arguments */
            $arguments = \is_array($block['input'] ?? null) ? $block['input'] : [];
            $toolCalls[] = [
                'id' => $id,
                'name' => $block['name'],
                'arguments' => $arguments,
            ];

            $seenIds[$id] = true;
        }
    }
}
