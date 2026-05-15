<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Component\Process\Process;

/**
 * Wraps a Symfony Process running the Codex CLI as a RawResultInterface.
 *
 * @author Johannes Wachter <johannes@sulu.io>
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
     * agent message together with usage data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->process->wait();

        if (!$this->process->isSuccessful()) {
            throw new RuntimeException(\sprintf('Codex CLI process failed: "%s"', $this->process->getErrorOutput()));
        }

        $output = $this->process->getOutput();
        $lastAgentMessage = [];
        $lastError = [];
        $usage = [];
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

            $type = $decoded['type'] ?? '';

            if ('item.completed' === $type
                && 'agent_message' === ($decoded['item']['type'] ?? '')
            ) {
                $lastAgentMessage = $decoded;
            }

            if ('error' === $type) {
                $lastError = $decoded;
            }

            if ('turn.completed' === $type && isset($decoded['usage'])) {
                $usage = $decoded['usage'];
            }
        }

        if ([] !== $lastError && [] === $lastAgentMessage) {
            return $lastError;
        }

        if ([] !== $usage && [] !== $lastAgentMessage) {
            $lastAgentMessage['usage'] = $usage;
        }

        return $this->attachToolCalls($lastAgentMessage, $lastError, $events);
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
            throw new RuntimeException(\sprintf('Codex CLI process failed: "%s"', $this->process->getErrorOutput()));
        }
    }

    public function getObject(): Process
    {
        return $this->process;
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param array<string, mixed>       $lastAgentMessage
     * @param array<string, mixed>       $lastError
     *
     * @return array<string, mixed>
     */
    private function attachToolCalls(array $lastAgentMessage, array $lastError, array $events): array
    {
        $toolCalls = $this->extractToolCalls($events);

        if ([] === $toolCalls) {
            return [] !== $lastAgentMessage ? $lastAgentMessage : $lastError;
        }

        if ([] !== $lastAgentMessage) {
            $lastAgentMessage[self::TOOL_CALLS] = $toolCalls;

            return $lastAgentMessage;
        }

        if ([] !== $lastError) {
            $lastError[self::TOOL_CALLS] = $toolCalls;

            return $lastError;
        }

        return [self::TOOL_CALLS => $toolCalls];
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @return list<array{id: string, name: string, arguments: array<string, mixed>}>
     */
    private function extractToolCalls(array $events): array
    {
        $started = [];
        $toolCalls = [];

        foreach ($events as $index => $event) {
            $type = $event['type'] ?? null;
            if (!\is_string($type)) {
                continue;
            }

            $toolCall = match ($type) {
                'item.started', 'item.completed' => $this->extractLegacyToolCall($event),
                'event_msg' => $this->extractEventMessageToolCall($event),
                default => null,
            };
            if (null === $toolCall) {
                continue;
            }

            $key = '' !== $toolCall['id'] ? $toolCall['id'] : \sprintf('%s-%d', $toolCall['name'], $index);

            if ('item.started' === $type || ('event_msg' === $type && ($event['payload']['type'] ?? null) === 'mcp_tool_call_start')) {
                $started[$key] = $toolCall;
                continue;
            }

            if (isset($started[$key])) {
                $toolCall = $this->mergeStartedToolCall($started[$key], $toolCall);
                unset($started[$key]);
            }

            if ('' !== $toolCall['id']) {
                $toolCalls[] = $toolCall;
            }
        }

        foreach ($started as $toolCall) {
            if ('' !== $toolCall['id']) {
                $toolCalls[] = $toolCall;
            }
        }

        return $toolCalls;
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array{id: string, name: string, arguments: array<string, mixed>}|null
     */
    private function extractLegacyToolCall(array $event): ?array
    {
        $item = $event['item'] ?? null;
        if (!\is_array($item) || !$this->isToolCallItem($item)) {
            return null;
        }

        $name = $this->extractToolCallName($item);
        if (null === $name) {
            return null;
        }

        return [
            'id' => $this->extractString($item, ['id', 'call_id']) ?? '',
            'name' => $name,
            'arguments' => $this->extractToolCallArguments($item),
        ];
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array{id: string, name: string, arguments: array<string, mixed>}|null
     */
    private function extractEventMessageToolCall(array $event): ?array
    {
        $payload = $event['payload'] ?? null;
        if (!\is_array($payload)) {
            return null;
        }

        $payloadType = $payload['type'] ?? null;
        if (!\is_string($payloadType) || !\in_array($payloadType, ['mcp_tool_call_start', 'mcp_tool_call_end'], true)) {
            return null;
        }

        $invocation = $payload['invocation'] ?? null;
        if (!\is_array($invocation)) {
            return null;
        }

        $name = $this->extractString($invocation, ['tool']);
        if (null === $name) {
            return null;
        }

        return [
            'id' => $this->extractString($payload, ['call_id']) ?? '',
            'name' => $name,
            'arguments' => $this->extractToolCallArguments($invocation),
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isToolCallItem(array $item): bool
    {
        $type = $item['type'] ?? null;
        if (!\is_string($type) || '' === $type) {
            return false;
        }

        if (\in_array($type, ['agent_message', 'command_execution'], true)) {
            return false;
        }

        if (str_contains($type, 'tool')) {
            return true;
        }

        if (\in_array($type, ['function_call', 'mcp_call'], true)) {
            return true;
        }

        return isset($item['name']) || isset($item['tool_name']) || isset($item['function']);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractToolCallName(array $item): ?string
    {
        $name = $this->extractString($item, ['name', 'tool_name', 'tool']);
        if (null !== $name) {
            return $name;
        }

        $function = $item['function'] ?? null;
        if (\is_array($function) && isset($function['name']) && \is_string($function['name']) && '' !== $function['name']) {
            return $function['name'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function extractToolCallArguments(array $item): array
    {
        foreach (['arguments', 'input'] as $key) {
            if (!isset($item[$key])) {
                continue;
            }

            if (\is_array($item[$key])) {
                return $item[$key];
            }

            if (\is_string($item[$key]) && '' !== $item[$key]) {
                try {
                    $decoded = json_decode($item[$key], true, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    continue;
                }

                if (\is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $function = $item['function'] ?? null;
        if (\is_array($function)) {
            return $this->extractToolCallArguments($function);
        }

        return [];
    }

    /**
     * @param array{id: string, name: string, arguments: array<string, mixed>} $started
     * @param array{id: string, name: string, arguments: array<string, mixed>} $completed
     *
     * @return array{id: string, name: string, arguments: array<string, mixed>}
     */
    private function mergeStartedToolCall(array $started, array $completed): array
    {
        if ([] === $completed['arguments']) {
            $completed['arguments'] = $started['arguments'];
        }

        return $completed;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     */
    private function extractString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (\is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }
}
