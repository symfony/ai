<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\TokenUsage;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Metadata\MergeableMetadataInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TokenUsageAggregation implements TokenUsageInterface, MergeableMetadataInterface
{
    /**
     * @param TokenUsageInterface[] $tokenUsages
     */
    public function __construct(
        private array $tokenUsages = [],
    ) {
    }

    public function add(TokenUsageInterface $tokenUsage): void
    {
        $this->tokenUsages[] = $tokenUsage;
    }

    public function merge(MergeableMetadataInterface $metadata): self
    {
        if (!$metadata instanceof TokenUsageInterface) {
            throw new InvalidArgumentException(\sprintf('Cannot merge "%s" with "%s".', self::class, $metadata::class));
        }

        return new self([...$this->tokenUsages, $metadata]);
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->tokenUsages as $usage) {
            ++$total;
            if ($usage instanceof self) {
                $total += $usage->count() - 1;
            }
        }

        return $total;
    }

    public function getPromptTokens(): ?int
    {
        return $this->sum(static fn (TokenUsageInterface $usage) => $usage->getPromptTokens());
    }

    public function getCompletionTokens(): ?int
    {
        return $this->sum(static fn (TokenUsageInterface $usage) => $usage->getCompletionTokens());
    }

    public function getThinkingTokens(): ?int
    {
        return $this->sum(static fn (TokenUsageInterface $usage) => $usage->getThinkingTokens());
    }

    public function getToolTokens(): ?int
    {
        return $this->sum(static fn (TokenUsageInterface $usage) => $usage->getToolTokens());
    }

    public function getCachedTokens(): ?int
    {
        return $this->sum(static fn (TokenUsageInterface $usage) => $usage->getCachedTokens());
    }

    public function getCacheCreationTokens(): ?int
    {
        return $this->sum(static fn (TokenUsageInterface $usage) => $usage->getCacheCreationTokens());
    }

    public function getCacheReadTokens(): ?int
    {
        return $this->sum(static fn (TokenUsageInterface $usage) => $usage->getCacheReadTokens());
    }

    public function getRemainingTokens(): ?int
    {
        return $this->min(static fn (TokenUsageInterface $usage) => $usage->getRemainingTokens());
    }

    public function getRemainingTokensMinute(): ?int
    {
        return $this->min(static fn (TokenUsageInterface $usage) => $usage->getRemainingTokensMinute());
    }

    public function getRemainingTokensMonth(): ?int
    {
        return $this->min(static fn (TokenUsageInterface $usage) => $usage->getRemainingTokensMonth());
    }

    public function getTotalTokens(): ?int
    {
        return $this->sum(static fn (TokenUsageInterface $usage) => $usage->getTotalTokens());
    }

    private function sum(\Closure $mapFunction): ?int
    {
        $array = array_filter(array_map($mapFunction, $this->tokenUsages));

        if ([] === $array) {
            return null;
        }

        return array_sum($array);
    }

    private function min(\Closure $mapFunction): ?int
    {
        $array = array_filter(array_map($mapFunction, $this->tokenUsages));

        if ([] === $array) {
            return null;
        }

        return min($array);
    }
}
