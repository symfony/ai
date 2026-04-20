<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\AiBundle\Profiler\DataCollector as AiDataCollector;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Formats Symfony AI profiler data for AI consumption.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<AiDataCollector>
 */
final class AiCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'ai';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof AiDataCollector);

        return [
            'platform_call_count' => \count($collector->getPlatformCalls()),
            'tool_count' => \count($collector->getTools()),
            'tool_call_count' => \count($collector->getToolCalls()),
            'message_count' => \count($collector->getMessages()),
            'chat_count' => \count($collector->getChats()),
            'agent_call_count' => \count($collector->getAgents()),
            'store_call_count' => \count($collector->getStores()),
            'platform_calls' => $this->normalizePlatformCalls($collector->getPlatformCalls()),
            'tools' => $this->normalizeTools($collector->getTools()),
            'tool_calls' => $this->normalizeToolCalls($collector->getToolCalls()),
            'messages' => $this->normalizeStoredMessages($collector->getMessages()),
            'chats' => $this->normalizeChats($collector->getChats()),
            'agents' => $this->normalizeAgents($collector->getAgents()),
            'stores' => $this->normalizeStores($collector->getStores()),
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof AiDataCollector);

        return [
            'platform_call_count' => \count($collector->getPlatformCalls()),
            'tool_count' => \count($collector->getTools()),
            'tool_call_count' => \count($collector->getToolCalls()),
            'message_count' => \count($collector->getMessages()),
            'chat_count' => \count($collector->getChats()),
            'agent_call_count' => \count($collector->getAgents()),
            'store_call_count' => \count($collector->getStores()),
        ];
    }

    /**
     * @param array<array<string, mixed>> $calls
     *
     * @return list<array<string, mixed>>
     */
    private function normalizePlatformCalls(array $calls): array
    {
        return array_values(array_map(fn (array $call): array => [
            'model' => isset($call['model']) && \is_string($call['model']) ? $call['model'] : null,
            'input' => $this->normalizeValue($call['input'] ?? null),
            'options' => $this->normalizeAssociativeArray($call['options'] ?? []),
            'result_type' => isset($call['result_type']) && \is_string($call['result_type']) ? $call['result_type'] : null,
            'result' => $this->normalizeValue($call['result'] ?? null),
            'metadata' => $this->normalizeMetadata($call['metadata'] ?? null),
        ], $calls));
    }

    /**
     * @param Tool[] $tools
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeTools(array $tools): array
    {
        return array_values(array_map(fn (Tool $tool): array => $this->normalizeTool($tool), $tools));
    }

    /**
     * @param ToolResult[] $toolCalls
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeToolCalls(array $toolCalls): array
    {
        return array_values(array_map(fn (ToolResult $toolResult): array => [
            'tool_call' => $this->normalizeToolCall($toolResult->getToolCall()),
            'result' => $this->normalizeValue($toolResult->getResult()),
            'sources' => $this->normalizeSources($toolResult->getSources()),
        ], $toolCalls));
    }

    /**
     * @param array<array{bag: MessageBag, saved_at: \DateTimeImmutable}> $messages
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeStoredMessages(array $messages): array
    {
        return array_values(array_map(fn (array $messageCall): array => [
            'saved_at' => $this->normalizeDateTime($messageCall['saved_at'] ?? null),
            'bag' => $this->normalizeMessageBag($messageCall['bag'] ?? null),
        ], $messages));
    }

    /**
     * @param array<array<string, mixed>> $chats
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeChats(array $chats): array
    {
        return array_values(array_map(fn (array $chatCall): array => [
            'action' => isset($chatCall['action']) && \is_string($chatCall['action']) ? $chatCall['action'] : null,
            'bag' => $this->normalizeMessageBag($chatCall['bag'] ?? null),
            'message' => $this->normalizeMessage($chatCall['message'] ?? null),
            'initiated_at' => $this->normalizeDateTime($chatCall['initiated_at'] ?? null),
            'submitted_at' => $this->normalizeDateTime($chatCall['submitted_at'] ?? null),
            'streamed_at' => $this->normalizeDateTime($chatCall['streamed_at'] ?? null),
        ], $chats));
    }

    /**
     * @param array<array{messages: MessageBag, options: array<string, mixed>, called_at: \DateTimeImmutable}> $agents
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeAgents(array $agents): array
    {
        return array_values(array_map(fn (array $agentCall): array => [
            'called_at' => $this->normalizeDateTime($agentCall['called_at'] ?? null),
            'messages' => $this->normalizeMessageBag($agentCall['messages'] ?? null),
            'options' => $this->normalizeAssociativeArray($agentCall['options'] ?? []),
        ], $agents));
    }

    /**
     * @param array<array<string, mixed>> $stores
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeStores(array $stores): array
    {
        return array_values(array_map(fn (array $storeCall): array => [
            'method' => isset($storeCall['method']) && \is_string($storeCall['method']) ? $storeCall['method'] : null,
            'called_at' => $this->normalizeDateTime($storeCall['called_at'] ?? null),
            'documents' => $this->normalizeDocuments($storeCall['documents'] ?? null),
            'query' => $this->normalizeQuery($storeCall['query'] ?? null),
            'ids' => $this->normalizeIds($storeCall['ids'] ?? null),
            'options' => $this->normalizeAssociativeArray($storeCall['options'] ?? []),
        ], $stores));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeTool(Tool $tool): array
    {
        return [
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
            'reference' => $this->normalizeExecutionReference($tool->getReference()),
            'parameters' => $this->normalizeValue($tool->getParameters()),
        ];
    }

    /**
     * @return array{class: string, method: string}
     */
    private function normalizeExecutionReference(ExecutionReference $reference): array
    {
        return [
            'class' => $reference->getClass(),
            'method' => $reference->getMethod(),
        ];
    }

    /**
     * @return array{id: string, name: string, arguments: mixed}
     */
    private function normalizeToolCall(ToolCall $toolCall): array
    {
        return [
            'id' => $toolCall->getId(),
            'name' => $toolCall->getName(),
            'arguments' => $this->normalizeValue($toolCall->getArguments()),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMessageBag(mixed $messageBag): ?array
    {
        if (!$messageBag instanceof MessageBag) {
            return null;
        }

        $messages = $messageBag->getMessages();

        return [
            'id' => $messageBag->getId()->toRfc4122(),
            'message_count' => \count($messages),
            'messages' => array_values(array_map(fn (MessageInterface $message): array => $this->normalizeMessage($message), $messages)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMessage(mixed $message): ?array
    {
        if (!$message instanceof MessageInterface) {
            return null;
        }

        $normalized = [
            'role' => $message->getRole()->value,
            'class' => $message::class,
            'content' => $this->normalizeValue($message->getContent()),
        ];

        if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
            $normalized['tool_calls'] = array_values(array_map(
                fn (ToolCall $toolCall): array => $this->normalizeToolCall($toolCall),
                $message->getToolCalls() ?? [],
            ));

            if ($message->hasThinkingContent()) {
                $normalized['thinking_content'] = $message->getThinkingContent();
            }
        }

        if ($message instanceof ToolCallMessage) {
            $normalized['tool_call'] = $this->normalizeToolCall($message->getToolCall());
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadata(mixed $metadata): array
    {
        if ($metadata instanceof Metadata) {
            return $this->normalizeAssociativeArray($metadata->all());
        }

        if (\is_array($metadata)) {
            return $this->normalizeAssociativeArray($metadata);
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function normalizeSources(mixed $sources): ?array
    {
        if (null === $sources) {
            return null;
        }

        if (\is_object($sources) && method_exists($sources, 'all')) {
            $sources = $sources->all();
        }

        if (!\is_array($sources)) {
            return null;
        }

        return array_values(array_map(function (mixed $source): array {
            if (\is_object($source) && method_exists($source, 'getName') && method_exists($source, 'getReference') && method_exists($source, 'getContent')) {
                return [
                    'name' => $source->getName(),
                    'reference' => $source->getReference(),
                    'content' => $source->getContent(),
                ];
            }

            return [
                'value' => $this->normalizeValue($source),
            ];
        }, $sources));
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>|null
     */
    private function normalizeDocuments(mixed $documents): array|null
    {
        if (null === $documents) {
            return null;
        }

        if ($documents instanceof VectorDocument) {
            return $this->normalizeDocument($documents);
        }

        if (\is_array($documents)) {
            return array_values(array_map(
                fn (VectorDocument $document): array => $this->normalizeDocument($document),
                $documents,
            ));
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDocument(VectorDocument $document): array
    {
        return [
            'id' => $document->getId(),
            'vector' => $this->normalizeVector($document->getVector()),
            'metadata' => $this->normalizeValue($document->getMetadata()->getArrayCopy()),
            'score' => $document->getScore(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeQuery(mixed $query): ?array
    {
        if (!$query instanceof QueryInterface) {
            return null;
        }

        if ($query instanceof HybridQuery) {
            return [
                'type' => 'hybrid',
                'texts' => $query->getTexts(),
                'vector' => $this->normalizeVector($query->getVector()),
                'semantic_ratio' => $query->getSemanticRatio(),
                'keyword_ratio' => $query->getKeywordRatio(),
            ];
        }

        if ($query instanceof TextQuery) {
            return [
                'type' => 'text',
                'texts' => $query->getTexts(),
                'text' => $query->getText(),
            ];
        }

        if ($query instanceof VectorQuery) {
            return [
                'type' => 'vector',
                'vector' => $this->normalizeVector($query->getVector()),
            ];
        }

        return [
            'type' => 'object',
            'class' => $query::class,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeVector(mixed $vector): ?array
    {
        if (!$vector instanceof VectorInterface) {
            return null;
        }

        return [
            'dimensions' => $vector->getDimensions(),
            'data' => $vector->getData(),
        ];
    }

    /**
     * @return list<mixed>|string|null
     */
    private function normalizeIds(mixed $ids): array|string|null
    {
        if (null === $ids || \is_string($ids)) {
            return $ids;
        }

        if (\is_array($ids)) {
            return array_values(array_map(fn (mixed $id): mixed => $this->normalizeValue($id), $ids));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function normalizeAssociativeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = $this->normalizeValue($value);
        }

        return $values;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (null === $value || \is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if ($value instanceof \UnitEnum) {
            if ($value instanceof \BackedEnum) {
                return $value->value;
            }

            return $value->name;
        }

        if (\is_array($value)) {
            if (array_is_list($value)) {
                return array_values(array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value));
            }

            return $this->normalizeAssociativeArray($value);
        }

        if (\is_object($value)) {
            if ($value instanceof MessageBag) {
                return $this->normalizeMessageBag($value);
            }

            if ($value instanceof MessageInterface) {
                return $this->normalizeMessage($value);
            }

            if ($value instanceof ToolCall) {
                return $this->normalizeToolCall($value);
            }

            if ($value instanceof ExecutionReference) {
                return $this->normalizeExecutionReference($value);
            }

            if ($value instanceof Tool) {
                return $this->normalizeTool($value);
            }

            if ($value instanceof ToolResult) {
                return [
                    'tool_call' => $this->normalizeToolCall($value->getToolCall()),
                    'result' => $this->normalizeValue($value->getResult()),
                ];
            }

            if ($value instanceof VectorInterface) {
                return $this->normalizeVector($value);
            }

            if ($value instanceof \Symfony\AI\Platform\Message\Content\File) {
                return [
                    'type' => 'file',
                    'class' => $value::class,
                    'format' => $value->getFormat(),
                    'filename' => $value->getFilename(),
                    'path' => $value->asPath(),
                ];
            }

            if ($value instanceof \Symfony\AI\Platform\Message\Content\Text) {
                return [
                    'type' => 'text',
                    'text' => $value->getText(),
                ];
            }

            if (method_exists($value, 'all')) {
                $all = $value->all();
                if (\is_array($all)) {
                    return $this->normalizeValue($all);
                }
            }

            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            return [
                'type' => 'object',
                'class' => $value::class,
            ];
        }

        return (string) $value;
    }

    private function normalizeDateTime(mixed $dateTime): ?string
    {
        if (!$dateTime instanceof \DateTimeInterface) {
            return null;
        }

        return $dateTime->format(\DateTimeInterface::ATOM);
    }
}
