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

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Formats Symfony AI profiler data for AI consumption.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
final class AiCollectorFormatter implements CollectorFormatterInterface
{
    use ExtractsCollectorDataTrait;

    public function getName(): string
    {
        return 'ai';
    }

    public function format(DataCollectorInterface $collector): array
    {
        $data = $this->extractCollectorData($collector);

        $platformCalls = $this->normalizePlatformCalls($data['platform_calls'] ?? []);
        $tools = $this->normalizeTools($data['tools'] ?? []);
        $toolCalls = $this->normalizeToolCalls($data['tool_calls'] ?? []);
        $messages = $this->normalizeStoredMessages($data['messages'] ?? []);
        $chats = $this->normalizeChats($data['chats'] ?? []);
        $agents = $this->normalizeAgents($data['agents'] ?? []);
        $stores = $this->normalizeStores($data['stores'] ?? []);

        return [
            'platform_call_count' => \count($platformCalls),
            'tool_count' => \count($tools),
            'tool_call_count' => \count($toolCalls),
            'message_count' => \count($messages),
            'chat_count' => \count($chats),
            'agent_call_count' => \count($agents),
            'store_call_count' => \count($stores),
            'platform_calls' => $platformCalls,
            'tools' => $tools,
            'tool_calls' => $toolCalls,
            'messages' => $messages,
            'chats' => $chats,
            'agents' => $agents,
            'stores' => $stores,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        $data = $this->extractCollectorData($collector);

        return [
            'platform_call_count' => $this->countList($data['platform_calls'] ?? []),
            'tool_count' => $this->countList($data['tools'] ?? []),
            'tool_call_count' => $this->countList($data['tool_calls'] ?? []),
            'message_count' => $this->countList($data['messages'] ?? []),
            'chat_count' => $this->countList($data['chats'] ?? []),
            'agent_call_count' => $this->countList($data['agents'] ?? []),
            'store_call_count' => $this->countList($data['stores'] ?? []),
        ];
    }

    /**
     * @param mixed $calls
     *
     * @return list<array<string, mixed>>
     */
    private function normalizePlatformCalls(mixed $calls): array
    {
        if (!\is_array($calls)) {
            return [];
        }

        return array_values(array_map(function (mixed $call): array {
            if (!\is_array($call)) {
                return ['value' => $this->normalizeValue($call)];
            }

            return [
                'model' => isset($call['model']) && \is_string($call['model']) ? $call['model'] : null,
                'input' => $this->normalizeValue($call['input'] ?? null),
                'options' => $this->normalizeOptions($call['options'] ?? []),
                'result_type' => isset($call['result_type']) && \is_string($call['result_type']) ? $call['result_type'] : null,
                'result' => $this->normalizeValue($call['result'] ?? null),
                'metadata' => $this->normalizeMetadata($call['metadata'] ?? null),
            ];
        }, $calls));
    }

    /**
     * @param mixed $tools
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeTools(mixed $tools): array
    {
        if (!\is_array($tools)) {
            return [];
        }

        return array_values(array_map(fn (mixed $tool): array => $this->normalizeTool($tool), $tools));
    }

    /**
     * @param mixed $toolCalls
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeToolCalls(mixed $toolCalls): array
    {
        if (!\is_array($toolCalls)) {
            return [];
        }

        return array_values(array_map(function (mixed $toolResult): array {
            if (\is_object($toolResult) && method_exists($toolResult, 'getToolCall') && method_exists($toolResult, 'getResult')) {
                $sources = null;
                if (method_exists($toolResult, 'getSources')) {
                    $sources = $toolResult->getSources();
                }

                return [
                    'tool_call' => $this->normalizeToolCall($toolResult->getToolCall()),
                    'result' => $this->normalizeValue($toolResult->getResult()),
                    'sources' => $this->normalizeSources($sources),
                ];
            }

            return [
                'value' => $this->normalizeValue($toolResult),
            ];
        }, $toolCalls));
    }

    /**
     * @param mixed $messages
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeStoredMessages(mixed $messages): array
    {
        if (!\is_array($messages)) {
            return [];
        }

        return array_values(array_map(function (mixed $messageCall): array {
            if (!\is_array($messageCall)) {
                return ['value' => $this->normalizeValue($messageCall)];
            }

            return [
                'saved_at' => $this->normalizeDateTime($messageCall['saved_at'] ?? null),
                'bag' => $this->normalizeMessageBag($messageCall['bag'] ?? null),
            ];
        }, $messages));
    }

    /**
     * @param mixed $chats
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeChats(mixed $chats): array
    {
        if (!\is_array($chats)) {
            return [];
        }

        return array_values(array_map(function (mixed $chatCall): array {
            if (!\is_array($chatCall)) {
                return ['value' => $this->normalizeValue($chatCall)];
            }

            return [
                'action' => isset($chatCall['action']) && \is_string($chatCall['action']) ? $chatCall['action'] : null,
                'bag' => $this->normalizeMessageBag($chatCall['bag'] ?? null),
                'message' => $this->normalizeMessage($chatCall['message'] ?? null),
                'initiated_at' => $this->normalizeDateTime($chatCall['initiated_at'] ?? null),
                'submitted_at' => $this->normalizeDateTime($chatCall['submitted_at'] ?? null),
                'streamed_at' => $this->normalizeDateTime($chatCall['streamed_at'] ?? null),
            ];
        }, $chats));
    }

    /**
     * @param mixed $agents
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeAgents(mixed $agents): array
    {
        if (!\is_array($agents)) {
            return [];
        }

        return array_values(array_map(function (mixed $agentCall): array {
            if (!\is_array($agentCall)) {
                return ['value' => $this->normalizeValue($agentCall)];
            }

            return [
                'called_at' => $this->normalizeDateTime($agentCall['called_at'] ?? null),
                'messages' => $this->normalizeMessageBag($agentCall['messages'] ?? null),
                'options' => $this->normalizeOptions($agentCall['options'] ?? []),
            ];
        }, $agents));
    }

    /**
     * @param mixed $stores
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeStores(mixed $stores): array
    {
        if (!\is_array($stores)) {
            return [];
        }

        return array_values(array_map(function (mixed $storeCall): array {
            if (!\is_array($storeCall)) {
                return ['value' => $this->normalizeValue($storeCall)];
            }

            return [
                'method' => isset($storeCall['method']) && \is_string($storeCall['method']) ? $storeCall['method'] : null,
                'called_at' => $this->normalizeDateTime($storeCall['called_at'] ?? null),
                'documents' => $this->normalizeDocuments($storeCall['documents'] ?? null),
                'query' => $this->normalizeQuery($storeCall['query'] ?? null),
                'ids' => $this->normalizeIds($storeCall['ids'] ?? null),
                'options' => $this->normalizeOptions($storeCall['options'] ?? []),
            ];
        }, $stores));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeTool(mixed $tool): array
    {
        if (\is_object($tool) && method_exists($tool, 'getName') && method_exists($tool, 'getDescription')) {
            $reference = null;
            if (method_exists($tool, 'getReference')) {
                $reference = $tool->getReference();
            }

            $parameters = null;
            if (method_exists($tool, 'getParameters')) {
                $parameters = $tool->getParameters();
            }

            return [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'reference' => $this->normalizeExecutionReference($reference),
                'parameters' => $this->normalizeValue($parameters),
            ];
        }

        return [
            'value' => $this->normalizeValue($tool),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeExecutionReference(mixed $reference): ?array
    {
        if (!\is_object($reference)) {
            return null;
        }

        if (!method_exists($reference, 'getClass') || !method_exists($reference, 'getMethod')) {
            return null;
        }

        return [
            'class' => $reference->getClass(),
            'method' => $reference->getMethod(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeToolCall(mixed $toolCall): ?array
    {
        if (!\is_object($toolCall)) {
            return null;
        }

        if (!method_exists($toolCall, 'getId') || !method_exists($toolCall, 'getName') || !method_exists($toolCall, 'getArguments')) {
            return null;
        }

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
        if (!\is_object($messageBag)) {
            return null;
        }

        if (!method_exists($messageBag, 'getMessages')) {
            return null;
        }

        $messages = $messageBag->getMessages();
        if (!\is_array($messages)) {
            return null;
        }

        $id = null;
        if (method_exists($messageBag, 'getId')) {
            $id = $this->normalizeIdentifier($messageBag->getId());
        }

        return [
            'id' => $id,
            'message_count' => \count($messages),
            'messages' => array_values(array_map(fn (mixed $message): array => $this->normalizeMessage($message), $messages)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMessage(mixed $message): ?array
    {
        if (!\is_object($message) || !method_exists($message, 'getRole')) {
            return null;
        }

        $normalized = [
            'role' => $this->normalizeRole($message->getRole()),
            'class' => $message::class,
        ];

        if (method_exists($message, 'getContent')) {
            $normalized['content'] = $this->normalizeValue($message->getContent());
        }

        if (method_exists($message, 'hasToolCalls') && $message->hasToolCalls() && method_exists($message, 'getToolCalls')) {
            $normalized['tool_calls'] = $this->normalizeToolCallsList($message->getToolCalls());
        }

        if (method_exists($message, 'hasThinkingContent') && $message->hasThinkingContent() && method_exists($message, 'getThinkingContent')) {
            $normalized['thinking_content'] = $message->getThinkingContent();
        }

        if (method_exists($message, 'getToolCall')) {
            $normalized['tool_call'] = $this->normalizeToolCall($message->getToolCall());
        }

        return $normalized;
    }

    /**
     * @param mixed $toolCalls
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeToolCallsList(mixed $toolCalls): array
    {
        if (!\is_array($toolCalls)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $toolCall): ?array {
            return $this->normalizeToolCall($toolCall);
        }, $toolCalls)));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadata(mixed $metadata): array
    {
        if (\is_object($metadata) && method_exists($metadata, 'all')) {
            $all = $metadata->all();
            if (\is_array($all)) {
                return $this->normalizeAssociativeArray($all);
            }
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

        if (\is_array($documents)) {
            return array_values(array_map(fn (mixed $document): array => $this->normalizeDocument($document), $documents));
        }

        if (\is_object($documents)) {
            return $this->normalizeDocument($documents);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDocument(mixed $document): array
    {
        if (\is_object($document) && method_exists($document, 'getId') && method_exists($document, 'getVector') && method_exists($document, 'getMetadata')) {
            $score = null;
            if (method_exists($document, 'getScore')) {
                $score = $document->getScore();
            }

            return [
                'id' => $document->getId(),
                'vector' => $this->normalizeVector($document->getVector()),
                'metadata' => $this->normalizeValue($document->getMetadata()),
                'score' => $score,
            ];
        }

        return [
            'value' => $this->normalizeValue($document),
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

        if (method_exists($query, 'getVector') && method_exists($query, 'getTexts') && method_exists($query, 'getSemanticRatio')) {
            return [
                'type' => 'hybrid',
                'texts' => $this->normalizeValue($query->getTexts()),
                'vector' => $this->normalizeVector($query->getVector()),
                'semantic_ratio' => $query->getSemanticRatio(),
                'keyword_ratio' => method_exists($query, 'getKeywordRatio') ? $query->getKeywordRatio() : null,
            ];
        }

        if (method_exists($query, 'getTexts')) {
            return [
                'type' => 'text',
                'texts' => $this->normalizeValue($query->getTexts()),
                'text' => method_exists($query, 'getText') ? $query->getText() : null,
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
        if (!\is_object($vector) || !method_exists($vector, 'getDimensions')) {
            return null;
        }

        $data = null;
        if (method_exists($vector, 'getData')) {
            $data = $vector->getData();
        }

        return [
            'dimensions' => $vector->getDimensions(),
            'data' => \is_array($data) ? $data : null,
        ];
    }

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
     * @return array<string, mixed>
     */
    private function normalizeOptions(mixed $options): array
    {
        if (!\is_array($options)) {
            return [];
        }

        return $this->normalizeAssociativeArray($options);
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
            $messageBag = $this->normalizeMessageBag($value);
            if (null !== $messageBag) {
                return $messageBag;
            }

            $message = $this->normalizeMessage($value);
            if (null !== $message) {
                return $message;
            }

            $toolCall = $this->normalizeToolCall($value);
            if (null !== $toolCall) {
                return $toolCall;
            }

            $toolReference = $this->normalizeExecutionReference($value);
            if (null !== $toolReference) {
                return $toolReference;
            }

            if (method_exists($value, 'getName') && method_exists($value, 'getDescription')) {
                return $this->normalizeTool($value);
            }

            if (method_exists($value, 'getToolCall') && method_exists($value, 'getResult')) {
                return [
                    'tool_call' => $this->normalizeToolCall($value->getToolCall()),
                    'result' => $this->normalizeValue($value->getResult()),
                ];
            }

            $vector = $this->normalizeVector($value);
            if (null !== $vector) {
                return $vector;
            }

            if (method_exists($value, 'getFormat') && method_exists($value, 'getFilename')) {
                $path = null;
                if (method_exists($value, 'asPath')) {
                    $path = $value->asPath();
                }

                return [
                    'type' => 'file',
                    'class' => $value::class,
                    'format' => $value->getFormat(),
                    'filename' => $value->getFilename(),
                    'path' => $path,
                ];
            }

            if (method_exists($value, 'getUrl')) {
                return [
                    'type' => 'url',
                    'class' => $value::class,
                    'url' => $value->getUrl(),
                ];
            }

            if (method_exists($value, 'getText')) {
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

    private function normalizeRole(mixed $role): string|null
    {
        if (\is_string($role)) {
            return $role;
        }

        if ($role instanceof \BackedEnum) {
            return (string) $role->value;
        }

        if ($role instanceof \UnitEnum) {
            return $role->name;
        }

        if (\is_object($role) && isset($role->value) && \is_scalar($role->value)) {
            return (string) $role->value;
        }

        return null;
    }

    private function normalizeIdentifier(mixed $identifier): string|null
    {
        if (\is_string($identifier)) {
            return $identifier;
        }

        if (\is_object($identifier) && method_exists($identifier, 'toRfc4122')) {
            return $identifier->toRfc4122();
        }

        if ($identifier instanceof \Stringable) {
            return (string) $identifier;
        }

        return null;
    }

    private function normalizeDateTime(mixed $dateTime): ?string
    {
        if (!$dateTime instanceof \DateTimeInterface) {
            return null;
        }

        return $dateTime->format(\DateTimeInterface::ATOM);
    }

    private function countList(mixed $value): int
    {
        return \is_array($value) ? \count($value) : 0;
    }
}
