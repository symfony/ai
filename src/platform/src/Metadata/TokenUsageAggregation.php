<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Metadata;

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
        return array_sum(array_map(fn (TokenUsageInterface $t) => $t->getPromptTokens() ?? 0, $this->tokenUsages));
    }

    public function getCompletionTokens(): ?int
    {
        return array_sum(array_map(fn (TokenUsageInterface $t) => $t->getCompletionTokens() ?? 0, $this->tokenUsages));
    }

    public function getThinkingTokens(): ?int
    {
        return array_sum(array_map(fn (TokenUsageInterface $t) => $t->getThinkingTokens() ?? 0, $this->tokenUsages));
    }

    public function getToolTokens(): ?int
    {
        return array_sum(array_map(fn (TokenUsageInterface $t) => $t->getToolTokens() ?? 0, $this->tokenUsages));
    }

    public function getCachedTokens(): ?int
    {
        return array_sum(array_map(fn (TokenUsageInterface $t) => $t->getCachedTokens() ?? 0, $this->tokenUsages));
    }

    public function getRemainingTokens(): ?int
    {
        return min(array_map(fn (TokenUsageInterface $t) => $t->getRemainingTokens() ?? 0, $this->tokenUsages));
    }

    public function getRemainingTokensMinute(): ?int
    {
        return min(array_map(fn (TokenUsageInterface $t) => $t->getRemainingTokensMinute() ?? 0, $this->tokenUsages));
    }

    public function getRemainingTokensMonth(): ?int
    {
        return min(array_map(fn (TokenUsageInterface $t) => $t->getRemainingTokensMonth() ?? 0, $this->tokenUsages));
    }

    public function getTotalTokens(): ?int
    {
        return array_sum(array_map(fn (TokenUsageInterface $t) => $t->getTotalTokens() ?? 0, $this->tokenUsages));
    }
}
