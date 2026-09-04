<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test\Recording;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\AI\Platform\Vector\Vector;

/**
 * Converts a {@see ResultInterface} to and from a JSON-serializable array for {@see Cassette}.
 *
 * Supported result types (v1): text, object, vector, tool call, and text-delta streams.
 *
 * Result metadata is preserved for the values a provider actually reports at this boundary:
 * scalars, `null`, {@see FinishReason}, token usage ({@see TokenUsage} and
 * {@see TokenUsageAggregation}), and arrays nesting any of those to any depth. An unsupported
 * result type, delta type, or metadata value throws
 * rather than being dropped silently, so a cassette never claims to hold something it lost.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResultSerializer
{
    private const METADATA_KEY = 'metadata';

    /**
     * Marks a serialized metadata value that is not a plain scalar or array. Reserved: a metadata
     * array carrying this key would be indistinguishable from one of the envelopes below.
     */
    private const TYPE_KEY = '#type';

    private const TYPE_FINISH_REASON = 'finish_reason';
    private const TYPE_TOKEN_USAGE = 'token_usage';
    private const TYPE_TOKEN_USAGE_AGGREGATION = 'token_usage_aggregation';

    /**
     * @return array<string, mixed>
     */
    public static function toArray(ResultInterface $result): array
    {
        // The content pass runs first on purpose: a StreamResult only fills its metadata while its
        // generator is drained, so reading the metadata any earlier would always see it empty.
        $data = self::contentToArray($result);
        $metadata = self::metadataToArray($result->getMetadata());

        if ([] !== $metadata) {
            $data[self::METADATA_KEY] = $metadata;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ResultInterface
    {
        $result = self::contentFromArray($data);

        // Presence, not non-nullness: toArray() omits the key entirely when there is no metadata,
        // so a key that is present but not an array is a malformed cassette, `null` included.
        if (\array_key_exists(self::METADATA_KEY, $data)) {
            $metadata = self::metadataFromArray($data[self::METADATA_KEY]);

            if ([] !== $metadata) {
                $result->getMetadata()->set($metadata);
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function contentToArray(ResultInterface $result): array
    {
        if ($result instanceof TextResult) {
            return [
                'type' => 'text',
                'content' => $result->getContent(),
                'signature' => $result->getSignature(),
            ];
        }

        if ($result instanceof ObjectResult) {
            $content = $result->getContent();

            return [
                'type' => 'object',
                'is_object' => \is_object($content),
                'content' => json_encode($content, \JSON_THROW_ON_ERROR),
            ];
        }

        if ($result instanceof VectorResult) {
            return [
                'type' => 'vector',
                'vectors' => array_map(static fn (Vector $vector): array => $vector->getData(), $result->getContent()),
            ];
        }

        if ($result instanceof ToolCallResult) {
            return [
                'type' => 'toolcall',
                'tool_calls' => array_map(static fn (ToolCall $toolCall): array => [
                    'id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                    'arguments' => $toolCall->getArguments(),
                    'signature' => $toolCall->getSignature(),
                ], $result->getContent()),
            ];
        }

        if ($result instanceof StreamResult) {
            $deltas = [];
            foreach ($result->getContent() as $delta) {
                if (!$delta instanceof \Stringable) {
                    throw new InvalidArgumentException(\sprintf('Cannot record stream delta of type "%s"; only text deltas are supported.', get_debug_type($delta)));
                }

                $deltas[] = (string) $delta;
            }

            return [
                'type' => 'stream',
                'deltas' => $deltas,
            ];
        }

        throw new InvalidArgumentException(\sprintf('Cannot record result of type "%s".', $result::class));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function contentFromArray(array $data): ResultInterface
    {
        $type = $data['type'] ?? null;

        if ('text' === $type) {
            return new TextResult((string) $data['content'], $data['signature'] ?? null);
        }

        if ('object' === $type) {
            $content = json_decode((string) $data['content'], !($data['is_object'] ?? false), flags: \JSON_THROW_ON_ERROR);

            return new ObjectResult($content);
        }

        if ('vector' === $type) {
            return new VectorResult(array_map(
                static fn (array $vector): Vector => new Vector(array_map(static fn ($value): float => (float) $value, $vector)),
                $data['vectors'] ?? [],
            ));
        }

        if ('toolcall' === $type) {
            return new ToolCallResult(array_map(
                static fn (array $toolCall): ToolCall => new ToolCall(
                    (string) $toolCall['id'],
                    (string) $toolCall['name'],
                    $toolCall['arguments'] ?? [],
                    $toolCall['signature'] ?? null,
                ),
                $data['tool_calls'] ?? [],
            ));
        }

        if ('stream' === $type) {
            $deltas = $data['deltas'] ?? [];

            return new StreamResult((static function () use ($deltas): \Generator {
                foreach ($deltas as $text) {
                    yield new TextDelta((string) $text);
                }
            })());
        }

        throw new InvalidArgumentException(\sprintf('Cannot rebuild result of unknown type "%s".', \is_string($type) ? $type : get_debug_type($type)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function metadataToArray(Metadata $metadata): array
    {
        $data = [];
        foreach ($metadata->all() as $key => $value) {
            $data[$key] = self::metadataValueToArray((string) $key, $value);
        }

        return $data;
    }

    private static function metadataValueToArray(string $key, mixed $value): mixed
    {
        if (null === $value || \is_scalar($value)) {
            return $value;
        }

        if (\is_array($value)) {
            if (\array_key_exists(self::TYPE_KEY, $value)) {
                throw new InvalidArgumentException(\sprintf('Cannot record result metadata "%s": an array value must not use the reserved key "%s".', $key, self::TYPE_KEY));
            }

            return array_map(static fn (mixed $item): mixed => self::metadataValueToArray($key, $item), $value);
        }

        if ($value instanceof FinishReason) {
            return [
                self::TYPE_KEY => self::TYPE_FINISH_REASON,
                'case' => $value->getCase()->value,
                'raw' => $value->getRaw(),
            ];
        }

        if ($value instanceof TokenUsageAggregation) {
            return [
                self::TYPE_KEY => self::TYPE_TOKEN_USAGE_AGGREGATION,
                'usages' => array_map(
                    static fn (mixed $usage): mixed => self::metadataValueToArray($key, $usage),
                    $value->getTokenUsages(),
                ),
            ];
        }

        if ($value instanceof TokenUsage) {
            return [
                self::TYPE_KEY => self::TYPE_TOKEN_USAGE,
                'prompt_tokens' => $value->getPromptTokens(),
                'completion_tokens' => $value->getCompletionTokens(),
                'thinking_tokens' => $value->getThinkingTokens(),
                'tool_tokens' => $value->getToolTokens(),
                'cached_tokens' => $value->getCachedTokens(),
                'cache_creation_tokens' => $value->getCacheCreationTokens(),
                'cache_read_tokens' => $value->getCacheReadTokens(),
                'remaining_tokens' => $value->getRemainingTokens(),
                'remaining_tokens_minute' => $value->getRemainingTokensMinute(),
                'remaining_tokens_month' => $value->getRemainingTokensMonth(),
                'total_tokens' => $value->getTotalTokens(),
                'model' => $value->getModel(),
            ];
        }

        throw new InvalidArgumentException(\sprintf('Cannot record result metadata "%s" of type "%s"; only scalars, arrays of those, finish reasons and token usage can be replayed.', $key, get_debug_type($value)));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function metadataFromArray(mixed $data): array
    {
        if (!\is_array($data)) {
            throw new InvalidArgumentException(\sprintf('Cannot rebuild result metadata from "%s"; expected an array.', get_debug_type($data)));
        }

        $metadata = [];
        foreach ($data as $key => $value) {
            $metadata[$key] = self::metadataValueFromArray((string) $key, $value);
        }

        return $metadata;
    }

    private static function metadataValueFromArray(string $key, mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (!\array_key_exists(self::TYPE_KEY, $value)) {
            return array_map(static fn (mixed $item): mixed => self::metadataValueFromArray($key, $item), $value);
        }

        return match ($value[self::TYPE_KEY]) {
            self::TYPE_FINISH_REASON => self::finishReasonFromArray($key, $value),
            self::TYPE_TOKEN_USAGE => new TokenUsage(
                promptTokens: self::envelopeInt($key, $value, 'prompt_tokens'),
                completionTokens: self::envelopeInt($key, $value, 'completion_tokens'),
                thinkingTokens: self::envelopeInt($key, $value, 'thinking_tokens'),
                toolTokens: self::envelopeInt($key, $value, 'tool_tokens'),
                cachedTokens: self::envelopeInt($key, $value, 'cached_tokens'),
                cacheCreationTokens: self::envelopeInt($key, $value, 'cache_creation_tokens'),
                cacheReadTokens: self::envelopeInt($key, $value, 'cache_read_tokens'),
                remainingTokens: self::envelopeInt($key, $value, 'remaining_tokens'),
                remainingTokensMinute: self::envelopeInt($key, $value, 'remaining_tokens_minute'),
                remainingTokensMonth: self::envelopeInt($key, $value, 'remaining_tokens_month'),
                totalTokens: self::envelopeInt($key, $value, 'total_tokens'),
                model: self::envelopeNullableString($key, $value, 'model'),
            ),
            self::TYPE_TOKEN_USAGE_AGGREGATION => new TokenUsageAggregation(array_map(
                static fn (mixed $usage): TokenUsageInterface => self::aggregatedUsageFromArray($key, $usage),
                self::envelopeList($key, $value, 'usages'),
            )),
            default => throw new InvalidArgumentException(\sprintf('Cannot rebuild result metadata "%s" of unknown type "%s".', $key, \is_string($value[self::TYPE_KEY]) ? $value[self::TYPE_KEY] : get_debug_type($value[self::TYPE_KEY]))),
        };
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private static function finishReasonFromArray(string $key, array $envelope): FinishReason
    {
        $case = self::envelopeString($key, $envelope, 'case');

        return new FinishReason(
            FinishReasonCase::tryFrom($case)
                ?? throw new InvalidArgumentException(\sprintf('Cannot rebuild result metadata "%s": "%s" is not a known finish reason case.', $key, $case)),
            self::envelopeString($key, $envelope, 'raw'),
        );
    }

    /**
     * An aggregation may nest another aggregation, so this recurses through the same read path and
     * only accepts what a {@see TokenUsageAggregation} can actually hold.
     */
    private static function aggregatedUsageFromArray(string $key, mixed $usage): TokenUsageInterface
    {
        $rebuilt = self::metadataValueFromArray($key, $usage);

        if (!$rebuilt instanceof TokenUsageInterface) {
            throw new InvalidArgumentException(\sprintf('Cannot rebuild result metadata "%s": an aggregated usage of type "%s" is not a token usage.', $key, get_debug_type($rebuilt)));
        }

        return $rebuilt;
    }

    /**
     * A cassette is a committed fixture that gets hand-edited, so every envelope field is read
     * through one of these: a malformed one must raise the project exception rather than a PHP
     * warning, a \TypeError, or a silent coercion.
     *
     * @param array<string, mixed> $envelope
     */
    private static function envelopeString(string $key, array $envelope, string $field): string
    {
        $value = $envelope[$field] ?? null;

        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Cannot rebuild result metadata "%s": field "%s" must be a string, got "%s".', $key, $field, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private static function envelopeNullableString(string $key, array $envelope, string $field): ?string
    {
        $value = $envelope[$field] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Cannot rebuild result metadata "%s": field "%s" must be a string or null, got "%s".', $key, $field, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private static function envelopeInt(string $key, array $envelope, string $field): ?int
    {
        $value = $envelope[$field] ?? null;

        if (null === $value) {
            return null;
        }

        // A JSON cassette can only carry an int here; anything else lost its type on the way in.
        if (!\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('Cannot rebuild result metadata "%s": field "%s" must be an integer or null, got "%s".', $key, $field, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $envelope
     *
     * @return list<mixed>
     */
    private static function envelopeList(string $key, array $envelope, string $field): array
    {
        $value = $envelope[$field] ?? null;

        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(\sprintf('Cannot rebuild result metadata "%s": field "%s" must be a list, got "%s".', $key, $field, get_debug_type($value)));
        }

        return $value;
    }
}
