<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Dto\Notification;

use Symfony\AI\Platform\Bridge\Acp\Dto\Message\AbstractJsonRpcNotification;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolInputDelta;

/**
 * JSON-RPC notification: session/update.
 */
final class SessionUpdateNotification extends AbstractJsonRpcNotification
{
    public string $method = 'session/update';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $update = null;

    /**
     * @param array<string, mixed> $data
     */
    public function setUpdate(array $data): void
    {
        $this->update = $data;
    }

    public function getUpdateType(): string
    {
        return $this->update ? ($this->update['sessionUpdate'] ?? '') : '';
    }

    public function getToolCallId(): ?string
    {
        return $this->update['toolCallId'] ?? null;
    }

    public function toDelta(): ?DeltaInterface
    {
        $type = $this->getUpdateType();

        if ('agent_thought_chunk' === $type) {
            $content = $this->update['content'] ?? [];
            $text = \is_array($content) ? (string) ($content['text'] ?? '') : '';

            return new ThinkingDelta($text);
        }

        if ('tool_call' === $type) {
            $id = $this->getToolCallId();
            $title = $this->update['title'] ?? '';

            return \is_string($id) ? new ToolCallStart($id, $title) : null;
        }

        if ('tool_call_update' === $type) {
            return $this->parseToolCallUpdate();
        }

        if (\in_array($type, ['agent_message_chunk', 'agent_message'], true)) {
            $content = $this->update['content'] ?? [];
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
     * @return ToolCallComplete|ToolInputDelta|null
     */
    private function parseToolCallUpdate(): ?DeltaInterface
    {
        $id = $this->getToolCallId();
        $title = $this->update['title'] ?? '';
        $status = $this->update['status'] ?? '';

        if (!\is_string($id)) {
            return null;
        }

        if ('in_progress' === $status) {
            $rawInput = $this->update['rawInput'] ?? [];
            $json = \is_array($rawInput) ? json_encode($rawInput, \JSON_THROW_ON_ERROR) : (string) $rawInput;

            return new ToolInputDelta($id, $title, $json);
        }

        if ('completed' === $status || 'failed' === $status) {
            $arguments = [];
            $rawOutput = $this->update['rawOutput'] ?? [];

            if (\is_array($rawOutput) && isset($rawOutput['output'])) {
                if (\is_array($rawOutput['output'])) {
                    $arguments = $rawOutput['output'];
                } else {
                    $arguments = ['output' => $rawOutput['output']];
                }
            }

            $toolCall = new \Symfony\AI\Platform\Result\ToolCall($id, $title, $arguments);

            return new ToolCallComplete([$toolCall]);
        }

        return null;
    }
}
