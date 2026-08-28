<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval\Checkpoint;

use Symfony\AI\Agent\Approval\Exception\InvalidCheckpointException;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Serializes and deserializes ExecutionCheckpoint objects to and from JSON arrays.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
final class ExecutionCheckpointSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(ExecutionCheckpoint $checkpoint): array
    {
        $messagesData = [];
        foreach ($checkpoint->getMessages() as $message) {
            $messagesData[] = $this->serializeMessage($message);
        }

        $pendingCallsData = [];
        foreach ($checkpoint->getPendingToolCalls() as $toolCall) {
            $pendingCallsData[] = $this->serializeToolCall($toolCall);
        }

        $completedResultsData = [];
        foreach ($checkpoint->getCompletedToolResults() as $toolResult) {
            $completedResultsData[] = $this->serializeToolResult($toolResult);
        }

        $sourcesData = null;
        if (null !== $checkpoint->getSources()) {
            $sourcesData = [];
            foreach ($checkpoint->getSources()->all() as $source) {
                $sourcesData[] = [
                    'name' => $source->getName(),
                    'reference' => $source->getReference(),
                    'content' => $source->getContent(),
                ];
            }
        }

        return [
            'id' => $checkpoint->getId(),
            'agentName' => $checkpoint->getAgentName(),
            'model' => $checkpoint->getModel(),
            'messages' => $messagesData,
            'options' => $checkpoint->getOptions(),
            'pendingToolCalls' => $pendingCallsData,
            'completedToolResults' => $completedResultsData,
            'iterations' => $checkpoint->getIterations(),
            'sources' => $sourcesData,
            'createdAt' => $checkpoint->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'expiresAt' => $checkpoint->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): ExecutionCheckpoint
    {
        if (!isset($data['id'], $data['messages'])) {
            throw InvalidCheckpointException::unreadable('Missing required fields "id" or "messages".');
        }

        $messages = [];
        foreach ($data['messages'] as $messageData) {
            $messages[] = $this->deserializeMessage($messageData);
        }

        $messageBag = new MessageBag(...$messages);

        $pendingCalls = [];
        foreach ($data['pendingToolCalls'] ?? [] as $callData) {
            $pendingCalls[] = $this->deserializeToolCall($callData);
        }

        $completedResults = [];
        foreach ($data['completedToolResults'] ?? [] as $resultData) {
            $completedResults[] = $this->deserializeToolResult($resultData);
        }

        $sources = null;
        if (isset($data['sources']) && \is_array($data['sources'])) {
            $sourceObjects = [];
            foreach ($data['sources'] as $src) {
                $sourceObjects[] = new Source(
                    $src['name'] ?? '',
                    $src['reference'] ?? '',
                    $src['content'] ?? '',
                );
            }
            $sources = new SourceCollection($sourceObjects);
        }

        $createdAt = isset($data['createdAt']) ? new \DateTimeImmutable($data['createdAt']) : new \DateTimeImmutable();
        $expiresAt = isset($data['expiresAt']) ? new \DateTimeImmutable($data['expiresAt']) : null;

        return new ExecutionCheckpoint(
            id: $data['id'],
            agentName: $data['agentName'] ?? 'agent',
            model: $data['model'] ?? '',
            messages: $messageBag,
            options: $data['options'] ?? [],
            pendingToolCalls: $pendingCalls,
            completedToolResults: $completedResults,
            iterations: (int) ($data['iterations'] ?? 0),
            sources: $sources,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );
    }

    public function toJson(ExecutionCheckpoint $checkpoint): string
    {
        return json_encode($this->toArray($checkpoint), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    public function fromJson(string $json): ExecutionCheckpoint
    {
        try {
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw InvalidCheckpointException::unreadable($e->getMessage());
        }

        if (!\is_array($data)) {
            throw InvalidCheckpointException::unreadable('JSON does not decode into an array.');
        }

        return $this->fromArray($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(MessageInterface $message): array
    {
        $type = $message::class;
        $id = (string) $message->getId();
        $metadata = $message->getMetadata()->all();

        if ($message instanceof SystemMessage) {
            return [
                'type' => 'system',
                'id' => $id,
                'content' => (string) $message->getContent(),
                'metadata' => $metadata,
            ];
        }

        if ($message instanceof UserMessage) {
            $parts = [];
            foreach ($message->getContent() as $part) {
                if ($part instanceof Text) {
                    $parts[] = ['type' => 'text', 'text' => $part->getText()];
                } elseif ($part instanceof Image) {
                    $parts[] = ['type' => 'image', 'data' => $part->asBase64(), 'mimeType' => $part->getFormat()];
                } elseif ($part instanceof Audio) {
                    $parts[] = ['type' => 'audio', 'data' => $part->asBase64(), 'format' => $part->getFormat()];
                } elseif ($part instanceof Document) {
                    $parts[] = ['type' => 'document', 'data' => $part->asBase64(), 'mimeType' => $part->getFormat()];
                }
            }

            return [
                'type' => 'user',
                'id' => $id,
                'parts' => $parts,
                'metadata' => $metadata,
            ];
        }

        if ($message instanceof AssistantMessage) {
            $parts = [];
            foreach ($message->getContent() as $part) {
                if ($part instanceof Text) {
                    $parts[] = ['type' => 'text', 'text' => $part->getText()];
                } elseif ($part instanceof Thinking) {
                    $parts[] = ['type' => 'thinking', 'thought' => $part->getContent()];
                } elseif ($part instanceof ToolCall) {
                    $parts[] = ['type' => 'tool_call', 'tool_call' => $this->serializeToolCall($part)];
                }
            }

            return [
                'type' => 'assistant',
                'id' => $id,
                'parts' => $parts,
                'metadata' => $metadata,
            ];
        }

        if ($message instanceof ToolCallMessage) {
            $parts = [];
            foreach ($message->getContent() as $part) {
                if ($part instanceof Text) {
                    $parts[] = ['type' => 'text', 'text' => $part->getText()];
                }
            }

            return [
                'type' => 'tool_call_message',
                'id' => $id,
                'tool_call' => $this->serializeToolCall($message->getToolCall()),
                'parts' => $parts,
                'metadata' => $metadata,
            ];
        }

        return [
            'type' => $type,
            'id' => $id,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializeMessage(array $data): MessageInterface
    {
        $type = $data['type'] ?? 'user';
        $uuid = isset($data['id']) ? Uuid::fromString($data['id']) : Uuid::v7();

        $message = match ($type) {
            'system' => new SystemMessage($data['content'] ?? ''),
            'user' => $this->deserializeUserMessage($data),
            'assistant' => $this->deserializeAssistantMessage($data),
            'tool_call_message' => $this->deserializeToolCallMessage($data),
            default => Message::ofUser($data['content'] ?? ''),
        };

        if ($uuid instanceof TimeBasedUidInterface) {
            $message = $message->withId($uuid);
        }
        if (isset($data['metadata']) && \is_array($data['metadata'])) {
            $message->getMetadata()->set($data['metadata']);
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializeUserMessage(array $data): UserMessage
    {
        $contents = [];
        foreach ($data['parts'] ?? [] as $part) {
            $partType = $part['type'] ?? 'text';
            if ('text' === $partType) {
                $contents[] = new Text($part['text'] ?? '');
            } elseif ('image' === $partType && isset($part['data'])) {
                $contents[] = new Image(base64_decode($part['data']), $part['mimeType'] ?? 'image/png');
            } elseif ('audio' === $partType && isset($part['data'])) {
                $contents[] = new Audio(base64_decode($part['data']), $part['format'] ?? 'audio/mp3');
            } elseif ('document' === $partType && isset($part['data'])) {
                $contents[] = new Document(base64_decode($part['data']), $part['mimeType'] ?? 'application/pdf');
            }
        }

        if ([] === $contents && isset($data['content'])) {
            $contents[] = new Text($data['content']);
        }

        return new UserMessage(...$contents);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializeAssistantMessage(array $data): AssistantMessage
    {
        $contents = [];
        foreach ($data['parts'] ?? [] as $part) {
            $partType = $part['type'] ?? 'text';
            if ('text' === $partType) {
                $contents[] = new Text($part['text'] ?? '');
            } elseif ('thinking' === $partType) {
                $contents[] = new Thinking($part['thought'] ?? '');
            } elseif ('tool_call' === $partType && isset($part['tool_call'])) {
                $contents[] = $this->deserializeToolCall($part['tool_call']);
            }
        }

        return new AssistantMessage(...$contents);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializeToolCallMessage(array $data): ToolCallMessage
    {
        $toolCall = $this->deserializeToolCall($data['tool_call'] ?? []);
        $contents = [];
        foreach ($data['parts'] ?? [] as $part) {
            if ('text' === ($part['type'] ?? 'text')) {
                $contents[] = new Text($part['text'] ?? '');
            }
        }

        if ([] === $contents && isset($data['content'])) {
            $contents[] = new Text($data['content']);
        }

        return new ToolCallMessage($toolCall, ...$contents);
    }

    /**
     * @return array{id: ?string, name: string, arguments: array<string, mixed>}
     */
    private function serializeToolCall(ToolCall $toolCall): array
    {
        return [
            'id' => $toolCall->getId(),
            'name' => $toolCall->getName(),
            'arguments' => $toolCall->getArguments(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializeToolCall(array $data): ToolCall
    {
        return new ToolCall(
            $data['id'] ?? null,
            $data['name'] ?? '',
            $data['arguments'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeToolResult(ToolResult $toolResult): array
    {
        return [
            'toolCall' => $this->serializeToolCall($toolResult->getToolCall()),
            'result' => $toolResult->getResult(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializeToolResult(array $data): ToolResult
    {
        $toolCall = $this->deserializeToolCall($data['toolCall'] ?? []);

        return new ToolResult($toolCall, $data['result'] ?? null);
    }
}
