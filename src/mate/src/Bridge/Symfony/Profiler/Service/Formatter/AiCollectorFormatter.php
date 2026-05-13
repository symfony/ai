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

use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\AiBundle\Profiler\DataCollector as AiDataCollector;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\ToolCallMessage;
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
            'model' => \is_array($call) ? ($call['model'] ?? null) : null,
            'input' => $this->normalizeValue(\is_array($call) ? ($call['input'] ?? null) : null),
            'options' => $this->normalizeAssociativeArray(\is_array($call) && \is_array($call['options'] ?? null) ? $call['options'] : []),
            'result_type' => \is_array($call) ? ($call['result_type'] ?? null) : null,
            'result' => $this->normalizeValue(\is_array($call) ? ($call['result'] ?? null) : null),
            'metadata' => $this->normalizeMetadata(\is_array($call) && $call['metadata'] instanceof Metadata ? $call['metadata'] : new Metadata()),
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
     * @param array<mixed> $messages
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeStoredMessages(array $messages): array
    {
        return array_values(array_map(fn (mixed $messageCall): array => [
            'saved_at' => \is_array($messageCall) && $messageCall['saved_at'] instanceof \DateTimeInterface ? $messageCall['saved_at']->format(\DateTimeInterface::ATOM) : null,
            'bag' => $this->normalizeMessageBag(\is_array($messageCall) && $messageCall['bag'] instanceof MessageBag ? $messageCall['bag'] : null),
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
            'action' => \is_array($chatCall) ? ($chatCall['action'] ?? null) : null,
            'bag' => $this->normalizeMessageBag(\is_array($chatCall) && ($chatCall['bag'] ?? null) instanceof MessageBag ? $chatCall['bag'] : null),
            'message' => $this->normalizeMessage(\is_array($chatCall) && ($chatCall['message'] ?? null) instanceof MessageInterface ? $chatCall['message'] : null),
            'initiated_at' => \is_array($chatCall) && ($chatCall['initiated_at'] ?? null) instanceof \DateTimeInterface ? $chatCall['initiated_at']->format(\DateTimeInterface::ATOM) : null,
            'submitted_at' => \is_array($chatCall) && ($chatCall['submitted_at'] ?? null) instanceof \DateTimeInterface ? $chatCall['submitted_at']->format(\DateTimeInterface::ATOM) : null,
            'streamed_at' => \is_array($chatCall) && ($chatCall['streamed_at'] ?? null) instanceof \DateTimeInterface ? $chatCall['streamed_at']->format(\DateTimeInterface::ATOM) : null,
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
            'called_at' => \is_array($agentCall) && $agentCall['called_at'] instanceof \DateTimeInterface ? $agentCall['called_at']->format(\DateTimeInterface::ATOM) : null,
            'messages' => $this->normalizeMessageBag(\is_array($agentCall) && $agentCall['messages'] instanceof MessageBag ? $agentCall['messages'] : null),
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
            'method' => \is_array($storeCall) ? ($storeCall['method'] ?? null) : null,
            'called_at' => \is_array($storeCall) && ($storeCall['called_at'] ?? null) instanceof \DateTimeInterface ? $storeCall['called_at']->format(\DateTimeInterface::ATOM) : null,
            'documents' => $this->normalizeDocuments(\is_array($storeCall) ? ($storeCall['documents'] ?? null) : null),
            'query' => $this->normalizeQuery(\is_array($storeCall) && ($storeCall['query'] ?? null) instanceof QueryInterface ? $storeCall['query'] : null),
            'ids' => $this->normalizeIds(\is_array($storeCall) ? ($storeCall['ids'] ?? null) : null),
            'options' => $this->normalizeAssociativeArray(\is_array($storeCall) && \is_array($storeCall['options'] ?? null) ? $storeCall['options'] : []),
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
    private function normalizeMessageBag(?MessageBag $messageBag): ?array
    {
        if (null === $messageBag) {
            return null;
        }

        $messages = $messageBag->getMessages();

        return [
            'id' => (string) $messageBag->getId(),
            'message_count' => \count($messages),
            'messages' => array_values(array_map(fn (MessageInterface $message): array => $this->normalizeMessage($message), $messages)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMessage(MessageInterface $message): array
    {
        $role = $message->getRole();
        $normalized = [
            'role' => $role->value,
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
    private function normalizeMetadata(Metadata $metadata): array
    {
        return $this->normalizeAssociativeArray($metadata->all());
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function normalizeSources(?SourceCollection $sources): ?array
    {
        if (null === $sources) {
            return null;
        }

        return array_values(array_map(static fn (Source $source): array => [
            'name' => $source->getName(),
            'reference' => $source->getReference(),
            'content' => $source->getContent(),
        ], $sources->all()));
    }

    /**
     * @return list<array<string, mixed>>|array<string, mixed>|null
     */
    private function normalizeDocuments(mixed $documents): ?array
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
    private function normalizeQuery(?QueryInterface $query): ?array
    {
        if (null === $query) {
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
    private function normalizeVector(?VectorInterface $vector): ?array
    {
        if (null === $vector) {
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

        if ($value instanceof File) {
            return [
                'type' => 'file',
                'class' => $value::class,
                'format' => $value->getFormat(),
                'filename' => $value->getFilename(),
                'path' => $value->asPath(),
            ];
        }

        if ($value instanceof Text) {
            return [
                'type' => 'text',
                'text' => $value->getText(),
            ];
        }

        if ($value instanceof Metadata) {
            return $this->normalizeAssociativeArray($value->all());
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return [
            'type' => 'object',
            'class' => $value::class,
        ];
    }
}
