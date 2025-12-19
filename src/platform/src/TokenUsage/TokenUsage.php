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
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class TokenUsage implements TokenUsageInterface
{
    public function __construct(
        private readonly ?int $promptTokens = null,
        private readonly ?int $completionTokens = null,
        private readonly ?int $thinkingTokens = null,
        private readonly ?int $toolTokens = null,
        private readonly ?int $cachedTokens = null,
        private readonly ?int $remainingTokens = null,
        private readonly ?int $remainingTokensMinute = null,
        private readonly ?int $remainingTokensMonth = null,
        private readonly ?int $totalTokens = null,
    ) {
    }

    public function getPromptTokens(): ?int
    {
        return $this->promptTokens;
    }

    public function getCompletionTokens(): ?int
    {
        return $this->completionTokens;
    }

    public function getThinkingTokens(): ?int
    {
        return $this->thinkingTokens;
    }

    public function getToolTokens(): ?int
    {
        return $this->toolTokens;
    }

    public function getCachedTokens(): ?int
    {
        return $this->cachedTokens;
    }

    public function getRemainingTokens(): ?int
    {
        return $this->remainingTokens;
    }

    public function getRemainingTokensMinute(): ?int
    {
        return $this->remainingTokensMinute;
    }

    public function getRemainingTokensMonth(): ?int
    {
        return $this->remainingTokensMonth;
    }

    public function getTotalTokens(): ?int
    {
        return $this->totalTokens;
    }
}
