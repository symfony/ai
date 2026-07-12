<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp;

use Symfony\AI\Platform\Bridge\Acp\Exception\ProtocolException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
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
 * Converts ACP stream output to Platform result objects.
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Acp;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if ([] === $data) {
            throw new ProtocolException('ACP did not return any result.');
        }

        $text = '';
        $toolCalls = [];

        foreach ($result->getDataStream() as $message) {
            $delta = $this->messageToDelta($message);
            if (null !== $delta) {
                $this->accumulateDelta($delta, $text, $toolCalls);
            }
        }

        $results = [];
        foreach ($toolCalls as $toolCall) {
            $results[] = new ToolCallResult([$toolCall]);
        }

        if ('' !== $text) {
            $results[] = new TextResult($text);
        }

        if ([] === $results) {
            throw new ProtocolException('ACP result does not contain any supported content.');
        }

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
     * @return \Generator<int, DeltaInterface>
     */
    private function convertStream(RawResultInterface $result): \Generator
    {
        foreach ($result->getDataStream() as $message) {
            $delta = $this->messageToDelta($message);
            if (null !== $delta) {
                yield $delta;
            }
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function messageToDelta(array $message): ?DeltaInterface
    {
        $method = $message['method'] ?? '';

        if ('agent/messageStream' === $method) {
            return $this->parseMessageStream($message);
        }

        if ('session/update' === $method) {
            return $this->parseSessionUpdate($message);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function parseMessageStream(array $message): ?DeltaInterface
    {
        $content = $message['params']['content'] ?? [];
        $type = $content['type'] ?? 'text';
        $text = $content['text'] ?? '';

        return match ($type) {
            'text' => new TextDelta($text),
            'thought' => new ThinkingDelta($text),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $message
     */
    private function parseSessionUpdate(array $message): ?DeltaInterface
    {
        $update = $message['params']['update'] ?? [];
        $type = $update['sessionUpdate'] ?? '';

        if ('agent_thought_chunk' === $type) {
            $content = $update['content'] ?? [];
            $text = \is_array($content) ? (string) ($content['text'] ?? '') : '';

            return new ThinkingDelta($text);
        }

        if ('tool_call' === $type) {
            $id = $update['toolCallId'] ?? null;
            $title = $update['title'] ?? '';

            return \is_string($id) ? new ToolCallStart($id, $title) : null;
        }

        if ('tool_call_update' === $type) {
            return $this->parseToolCallUpdate($update);
        }

        if (\in_array($type, ['agent_message_chunk', 'agent_message'], true)) {
            $content = $update['content'] ?? [];
            $contentType = \is_array($content) ? ($content['type'] ?? 'text') : 'text';
            $text = \is_array($content) ? (string) ($content['text'] ?? '') : '';

            return match ($contentType) {
                'text' => new TextDelta($text),
                'thought' => new ThinkingDelta($text),
                default => null,
            };
        }

        return null;
    }

    /**
     * @param array<string, mixed> $update
     */
    private function parseToolCallUpdate(array $update): ?DeltaInterface
    {
        $id = $update['toolCallId'] ?? null;
        $title = $update['title'] ?? '';
        $status = $update['status'] ?? '';

        if (!\is_string($id)) {
            return null;
        }

        if ('in_progress' === $status) {
            $rawInput = $update['rawInput'] ?? [];
            $json = \is_array($rawInput) ? json_encode($rawInput, \JSON_THROW_ON_ERROR) : (string) $rawInput;

            return new ToolInputDelta($id, $title, $json);
        }

        if ('completed' === $status || 'failed' === $status) {
            $arguments = [];
            $rawOutput = $update['rawOutput'] ?? [];

            if (\is_array($rawOutput) && isset($rawOutput['output'])) {
                if (\is_array($rawOutput['output'])) {
                    $arguments = $rawOutput['output'];
                } else {
                    $arguments = ['output' => $rawOutput['output']];
                }
            }

            $toolCall = new ToolCall($id, $title, $arguments);

            return new ToolCallComplete([$toolCall]);
        }

        return null;
    }

    /**
     * @param array<ToolCall> $toolCalls
     */
    private function accumulateDelta(DeltaInterface $delta, string &$text, array &$toolCalls): void
    {
        if ($delta instanceof TextDelta) {
            $text .= $delta->getText();
        } elseif ($delta instanceof ThinkingDelta) {
            $text .= $delta->getThinking();
        } elseif ($delta instanceof ToolCallComplete) {
            foreach ($delta->getToolCalls() as $toolCall) {
                $toolCalls[] = $toolCall;
            }
        }
    }
}
