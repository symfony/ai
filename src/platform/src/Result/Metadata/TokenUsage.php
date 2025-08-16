<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Metadata;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class TokenUsage implements \JsonSerializable
{
    public function __construct(
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $thinkingTokens = null,
        public ?int $cachedTokens = null,
        public ?int $remainingTokens = null,
        public ?int $remainingTokensMinute = null,
        public ?int $remainingTokensMonth = null,
        public ?int $totalTokens = null,
    ) {
    }

    /**
     * @return array{
     *      prompt_tokens: ?int,
     *      completion_tokens: ?int,
     *      thinking_tokens: ?int,
     *      cached_tokens: ?int,
     *      remaining_tokens: ?int,
     *      remaining_tokens_minute: ?int,
     *      remaining_tokens_month: ?int,
     *      total_tokens: ?int,
     *  }
     */
    public function jsonSerialize(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'thinking_tokens' => $this->thinkingTokens,
            'cached_tokens' => $this->cachedTokens,
            'remaining_tokens' => $this->remainingTokens,
            'remaining_tokens_minute' => $this->remainingTokensMinute,
            'remaining_tokens_month' => $this->remainingTokensMonth,
            'total_tokens' => $this->totalTokens,
        ];
    }
}
