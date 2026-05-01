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

use Symfony\AI\AiBundle\Profiler\DataCollector as AiDataCollector;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
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

        $platformCalls = $collector->getPlatformCalls();
        $tools = $collector->getTools();
        $toolCalls = $collector->getToolCalls();
        $messages = $collector->getMessages();
        $chats = $collector->getChats();
        $agents = $collector->getAgents();
        $stores = $collector->getStores();

        return [
            'platform_call_count' => \count($platformCalls),
            'tool_count' => \count($tools),
            'tool_call_count' => \count($toolCalls),
            'message_count' => \count($messages),
            'chat_count' => \count($chats),
            'agent_call_count' => \count($agents),
            'store_call_count' => \count($stores),
            'platform_calls' => $this->normalizePlatformCalls($platformCalls),
            'tools' => $this->normalizeTools($tools),
            'tool_calls' => $this->normalizeToolCalls($toolCalls),
            'messages' => $this->normalizeStoredMessages($messages),
            'chats' => $this->normalizeChats($chats),
            'agents' => $this->normalizeAgents($agents),
            'stores' => $this->normalizeStores($stores),
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
     * @param array<mixed> $calls
     *
     * @return list<array<string, mixed>>
     */
    private function normalizePlatformCalls(array $calls): array
    {
        return array_values(array_map(fn (mixed $call): array => [
            'model' => \is_array($call) && isset($call['model']) && \is_string($call['model']) ? $call['model'] : null,
            'input' => $this->normalizeValue(\is_array($call) ? ($call['input'] ?? null) : null),
            'options' => $this->normalizeAssociativeArray(\is_array($call) && \is_array($call['options'] ?? null) ? $call['options'] : []),
            'result_type' => \is_array($call) && isset($call['result_type']) && \is_string($call['result_type']) ? $call['result_type'] : null,
            'result' => $this->normalizeValue(\is_array($call) ? ($call['result'] ?? null) : null),
            'metadata' => $this->normalizeMetadata(\is_array($call) ? ($call['metadata'] ?? null) : null),
        ], $calls));
    }

    /**
     * @param array<mixed> $tools
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeTools(array $tools): array
    {
        return array_values(array_map(fn (mixed $tool): array => $this->normalizeTool($tool), $tools));
    }

    /**
     * @param array<mixed> $toolCalls
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeToolCalls(array $toolCalls): array
    {
        return array_values(array_map(fn (mixed $toolResult): array => [
            'tool_call' => \is_object($toolResult) && method_exists($toolResult, 'getToolCall') ? $this->normalizeToolCall($toolResult->getToolCall()) : null,
            'result' => $this->normalizeValue(\is_object($toolResult) && method_exists($toolResult, 'getResult') ? $toolResult->getResult() : null),
            'sources' => $this->normalizeSources(\is_object($toolResult) && method_exists($toolResult, 'getSources') ? $toolResult->getSources() : null),
        ], $toolCalls));
    }

    /**
     * @param array<mixed> $messages
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeStoredMessages(array $messages): array
    {
        return array_values(array_map(fn (mixed $messageCall): array => [
            'saved_at' => $this->normalizeDateTime(\is_array($messageCall) ? ($messageCall['saved_at'] ?? null) : null),
            'bag' => $this->normalizeMessageBag(\is_array($messageCall) ? ($messageCall['bag'] ?? null) : null),
        ], $messages));
    }

    /**
     * @param array<mixed> $chats
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeChats(array $chats): array
    {
        return array_values(array_map(fn (mixed $chatCall): array => [
            'action' => \is_array($chatCall) && isset($chatCall['action']) && \is_string($chatCall['action']) ? $chatCall['action'] : null,
            'bag' => $this->normalizeMessageBag(\is_array($chatCall) ? ($chatCall['bag'] ?? null) : null),
            'message' => $this->normalizeMessage(\is_array($chatCall) ? ($chatCall['message'] ?? null) : null),
            'initiated_at' => $this->normalizeDateTime(\is_array($chatCall) ? ($chatCall['initiated_at'] ?? null) : null),
            'submitted_at' => $this->normalizeDateTime(\is_array($chatCall) ? ($chatCall['submitted_at'] ?? null) : null),
            'streamed_at' => $this->normalizeDateTime(\is_array($chatCall) ? ($chatCall['streamed_at'] ?? null) : null),
        ], $chats));
    }

    /**
     * @param array<mixed> $agents
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeAgents(array $agents): array
    {
        return array_values(array_map(fn (mixed $agentCall): array => [
            'called_at' => $this->normalizeDateTime(\is_array($agentCall) ? ($agentCall['called_at'] ?? null) : null),
            'messages' => $this->normalizeMessageBag(\is_array($agentCall) ? ($agentCall['messages'] ?? null) : null),
            'options' => $this->normalizeAssociativeArray(\is_array($agentCall) && \is_array($agentCall['options'] ?? null) ? $agentCall['options'] : []),
        ], $agents));
    }

    /**
     * @param array<mixed> $stores
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeStores(array $stores): array
    {
        return array_values(array_map(fn (mixed $storeCall): array => [
            'method' => \is_array($storeCall) && isset($storeCall['method']) && \is_string($storeCall['method']) ? $storeCall['method'] : null,
            'called_at' => $this->normalizeDateTime(\is_array($storeCall) ? ($storeCall['called_at'] ?? null) : null),
            'documents' => $this->normalizeDocuments(\is_array($storeCall) ? ($storeCall['documents'] ?? null) : null),
            'query' => $this->normalizeQuery(\is_array($storeCall) ? ($storeCall['query'] ?? null) : null),
            'ids' => $this->normalizeIds(\is_array($storeCall) ? ($storeCall['ids'] ?? null) : null),
            'options' => $this->normalizeAssociativeArray(\is_array($storeCall) && \is_array($storeCall['options'] ?? null) ? $storeCall['options'] : []),
        ], $stores));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeTool(mixed $tool): array
    {
        if (!\is_object($tool)) {
            return [];
        }

        return [
            'name' => method_exists($tool, 'getName') ? $tool->getName() : null,
            'description' => method_exists($tool, 'getDescription') ? $tool->getDescription() : null,
            'reference' => method_exists($tool, 'getReference') ? $this->normalizeExecutionReference($tool->getReference()) : null,
            'parameters' => $this->normalizeValue(method_exists($tool, 'getParameters') ? $tool->getParameters() : null),
        ];
    }

    /**
     * @return array{class: string, method: string}|null
     */
    private function normalizeExecutionReference(mixed $reference): ?array
    {
        if (!\is_object($reference)) {
            return null;
        }

        return [
            'class' => method_exists($reference, 'getClass') ? $reference->getClass() : '',
            'method' => method_exists($reference, 'getMethod') ? $reference->getMethod() : '',
        ];
    }

    /**
     * @return array{id: string, name: string, arguments: mixed}|null
     */
    private function normalizeToolCall(mixed $toolCall): ?array
    {
        if (!\is_object($toolCall)) {
            return null;
        }

        return [
            'id' => method_exists($toolCall, 'getId') ? $toolCall->getId() : null,
            'name' => method_exists($toolCall, 'getName') ? $toolCall->getName() : null,
            'arguments' => $this->normalizeValue(method_exists($toolCall, 'getArguments') ? $toolCall->getArguments() : null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMessageBag(mixed $messageBag): ?array
    {
        if (!\is_object($messageBag)) {
            return null;
        }

        if (!method_exists($messageBag, 'getMessages')) {
            return null;
        }

        $messages = $messageBag->getMessages();

        $id = method_exists($messageBag, 'getId') ? $messageBag->getId() : null;
        $idString = \is_object($id) && method_exists($id, 'toRfc4122') ? $id->toRfc4122() : ($id instanceof \Stringable ? (string) $id : null);

        return [
            'id' => $idString,
            'message_count' => \count($messages),
            'messages' => array_values(array_map(fn (mixed $message): ?array => $this->normalizeMessage($message), $messages)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMessage(mixed $message): ?array
    {
        if (!\is_object($message)) {
            return null;
        }

        if (!method_exists($message, 'getRole') || !method_exists($message, 'getContent')) {
            return null;
        }

        $role = $message->getRole();
        $normalized = [
            'role' => \is_object($role) && isset($role->value) ? $role->value : (string) $role,
            'class' => $message::class,
            'content' => $this->normalizeValue($message->getContent()),
        ];

        if (method_exists($message, 'hasToolCalls') && $message->hasToolCalls()) {
            $toolCalls = method_exists($message, 'getToolCalls') ? ($message->getToolCalls() ?? []) : [];
            $normalized['tool_calls'] = array_values(array_map(
                fn (mixed $toolCall): ?array => $this->normalizeToolCall($toolCall),
                $toolCalls,
            ));

            if (method_exists($message, 'hasThinkingContent') && $message->hasThinkingContent()) {
                $normalized['thinking_content'] = method_exists($message, 'getThinkingContent') ? $message->getThinkingContent() : null;
            }
        }

        if (method_exists($message, 'getToolCall')) {
            $normalized['tool_call'] = $this->normalizeToolCall($message->getToolCall());
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadata(mixed $metadata): array
    {
        if (\is_object($metadata) && method_exists($metadata, 'all')) {
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
    private function normalizeDocuments(mixed $documents): ?array
    {
        if (null === $documents) {
            return null;
        }

        if (\is_object($documents) && method_exists($documents, 'getId') && method_exists($documents, 'getVector')) {
            return $this->normalizeDocument($documents);
        }

        if (\is_array($documents)) {
            return array_values(array_map(
                fn (mixed $document): array => $this->normalizeDocument($document),
                $documents,
            ));
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDocument(mixed $document): array
    {
        if (!\is_object($document)) {
            return [];
        }

        $metadata = method_exists($document, 'getMetadata') ? $document->getMetadata() : null;
        if (\is_object($metadata) && method_exists($metadata, 'getArrayCopy')) {
            $metadata = $metadata->getArrayCopy();
        }

        return [
            'id' => method_exists($document, 'getId') ? $document->getId() : null,
            'vector' => $this->normalizeVector(method_exists($document, 'getVector') ? $document->getVector() : null),
            'metadata' => $this->normalizeValue($metadata),
            'score' => method_exists($document, 'getScore') ? $document->getScore() : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeQuery(mixed $query): ?array
    {
        if (!\is_object($query)) {
            return null;
        }

        if (method_exists($query, 'getTexts') && method_exists($query, 'getVector') && method_exists($query, 'getSemanticRatio')) {
            return [
                'type' => 'hybrid',
                'texts' => $query->getTexts(),
                'vector' => $this->normalizeVector($query->getVector()),
                'semantic_ratio' => $query->getSemanticRatio(),
                'keyword_ratio' => method_exists($query, 'getKeywordRatio') ? $query->getKeywordRatio() : null,
            ];
        }

        if (method_exists($query, 'getTexts') && method_exists($query, 'getText')) {
            return [
                'type' => 'text',
                'texts' => $query->getTexts(),
                'text' => $query->getText(),
            ];
        }

        if (method_exists($query, 'getVector')) {
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
        if (!\is_object($vector) || !method_exists($vector, 'getDimensions') || !method_exists($vector, 'getData')) {
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
            if (method_exists($value, 'getMessages') && method_exists($value, 'getId')) {
                return $this->normalizeMessageBag($value);
            }

            if (method_exists($value, 'getRole') && method_exists($value, 'getContent')) {
                return $this->normalizeMessage($value);
            }

            if (method_exists($value, 'getId') && method_exists($value, 'getName') && method_exists($value, 'getArguments')) {
                return $this->normalizeToolCall($value);
            }

            if (method_exists($value, 'getClass') && method_exists($value, 'getMethod')) {
                return $this->normalizeExecutionReference($value);
            }

            if (method_exists($value, 'getName') && method_exists($value, 'getDescription') && method_exists($value, 'getReference')) {
                return $this->normalizeTool($value);
            }

            if (method_exists($value, 'getToolCall') && method_exists($value, 'getResult')) {
                return [
                    'tool_call' => $this->normalizeToolCall($value->getToolCall()),
                    'result' => $this->normalizeValue($value->getResult()),
                ];
            }

            if (method_exists($value, 'getDimensions') && method_exists($value, 'getData')) {
                return $this->normalizeVector($value);
            }

            if (method_exists($value, 'getFormat') && method_exists($value, 'getFilename') && method_exists($value, 'asPath')) {
                return [
                    'type' => 'file',
                    'class' => $value::class,
                    'format' => $value->getFormat(),
                    'filename' => $value->getFilename(),
                    'path' => $value->asPath(),
                ];
            }

            if (method_exists($value, 'getText') && !method_exists($value, 'getRole')) {
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
