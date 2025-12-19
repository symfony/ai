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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TokenUsageAggregation implements TokenUsageInterface
{
    /**
     * @var TokenUsageInterface[]
     */
    private readonly array $tokenUsages;

    public function __construct(
        TokenUsageInterface ...$tokenUsages,
    ) {
        $this->tokenUsages = $tokenUsages;
    }

    public function getPromptTokens(): ?int
    {
        return $this->sum(fn (TokenUsageInterface $usage) => $usage->getPromptTokens());
    }

    public function getCompletionTokens(): ?int
    {
        return $this->sum(fn (TokenUsageInterface $usage) => $usage->getCompletionTokens());
    }

    public function getThinkingTokens(): ?int
    {
        return $this->sum(fn (TokenUsageInterface $usage) => $usage->getThinkingTokens());
    }

    public function getToolTokens(): ?int
    {
        return $this->sum(fn (TokenUsageInterface $usage) => $usage->getToolTokens());
    }

    public function getCachedTokens(): ?int
    {
        return $this->sum(fn (TokenUsageInterface $usage) => $usage->getCachedTokens());
    }

    public function getRemainingTokens(): ?int
    {
        return $this->min(fn (TokenUsageInterface $usage) => $usage->getRemainingTokens());
    }

    public function getRemainingTokensMinute(): ?int
    {
        return $this->min(fn (TokenUsageInterface $usage) => $usage->getRemainingTokensMinute());
    }

    public function getRemainingTokensMonth(): ?int
    {
        return $this->min(fn (TokenUsageInterface $usage) => $usage->getRemainingTokensMonth());
    }

    public function getTotalTokens(): ?int
    {
        return $this->sum(fn (TokenUsageInterface $usage) => $usage->getTotalTokens());
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
