<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Serializer;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * (De)normalizes metadata values, keeping the type of objects next to their normalized value.
 *
 * Objects other than the platform's own value objects are handed to the serializer.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class MetadataNormalizer implements NormalizerAwareInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const TYPE = '@type';
    private const VALUE = '@value';
    private const LITERAL = 'array';

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(array $metadata, ?string $format = null, array $context = []): array
    {
        return array_map(fn (mixed $value): mixed => $this->normalizeValue($value, $format, $context), $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function denormalize(array $metadata, ?string $format = null, array $context = []): array
    {
        return array_map(fn (mixed $value): mixed => $this->denormalizeValue($value, $format, $context), $metadata);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function normalizeValue(mixed $value, ?string $format, array $context): mixed
    {
        if (\is_array($value)) {
            $normalized = array_map(fn (mixed $item): mixed => $this->normalizeValue($item, $format, $context), $value);

            // an array shaped like a typed object is wrapped to be restored as an array
            return self::isEnvelope($value) ? [self::TYPE => self::LITERAL, self::VALUE => $normalized] : $normalized;
        }

        if (!\is_object($value)) {
            return $value;
        }

        $normalized = match (true) {
            $value instanceof TokenUsage => [
                'promptTokens' => $value->getPromptTokens(),
                'completionTokens' => $value->getCompletionTokens(),
                'thinkingTokens' => $value->getThinkingTokens(),
                'toolTokens' => $value->getToolTokens(),
                'cachedTokens' => $value->getCachedTokens(),
                'cacheCreationTokens' => $value->getCacheCreationTokens(),
                'cacheReadTokens' => $value->getCacheReadTokens(),
                'remainingTokens' => $value->getRemainingTokens(),
                'remainingTokensMinute' => $value->getRemainingTokensMinute(),
                'remainingTokensMonth' => $value->getRemainingTokensMonth(),
                'totalTokens' => $value->getTotalTokens(),
                'model' => $value->getModel(),
            ],
            $value instanceof TokenUsageAggregation => array_map(
                fn (TokenUsageInterface $tokenUsage): mixed => $this->normalizeValue($tokenUsage, $format, $context),
                $value->getTokenUsages(),
            ),
            $value instanceof FinishReason => $value->jsonSerialize(),
            isset($this->normalizer) => $this->normalizer->normalize($value, $format, $context),
            default => throw new InvalidArgumentException(\sprintf('Cannot normalize the metadata value of type "%s", use a serializer with a normalizer for it.', $value::class)),
        };

        return [self::TYPE => $value::class, self::VALUE => $normalized];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function denormalizeValue(mixed $value, ?string $format, array $context): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (!self::isEnvelope($value)) {
            return array_map(fn (mixed $item): mixed => $this->denormalizeValue($item, $format, $context), $value);
        }

        return match ($value[self::TYPE]) {
            self::LITERAL => array_map(fn (mixed $item): mixed => $this->denormalizeValue($item, $format, $context), $value[self::VALUE]),
            TokenUsage::class => new TokenUsage(...$value[self::VALUE]),
            TokenUsageAggregation::class => new TokenUsageAggregation(array_map(
                fn (mixed $tokenUsage): mixed => $this->denormalizeValue($tokenUsage, $format, $context),
                $value[self::VALUE],
            )),
            FinishReason::class => new FinishReason(FinishReasonCase::from($value[self::VALUE]['case']), $value[self::VALUE]['raw']),
            default => isset($this->denormalizer)
                ? $this->denormalizer->denormalize($value[self::VALUE], $value[self::TYPE], $format, $context)
                : throw new InvalidArgumentException(\sprintf('Cannot denormalize the metadata value of type "%s", use a serializer with a denormalizer for it.', $value[self::TYPE])),
        };
    }

    /**
     * @param array<mixed> $value
     */
    private static function isEnvelope(array $value): bool
    {
        return 2 === \count($value) && \array_key_exists(self::TYPE, $value) && \array_key_exists(self::VALUE, $value);
    }
}
