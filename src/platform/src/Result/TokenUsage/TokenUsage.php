<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\TokenUsage;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class TokenUsage implements \JsonSerializable
{
    public function __construct(
        public ?int $prompt = null,
        public ?int $completion = null,
        public ?int $thinking = null,
        public ?int $remaining = null,
        public ?int $remainingTokensMinute = null,
        public ?int $remainingTokensMonth = null,
        public ?int $total = null,
    ) {
    }

    /**
     * @return array<string, int|null>
     */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'completion' => $this->completion,
            'thinking' => $this->thinking,
            'remaining' => $this->remaining,
            'remaining_tokens_minute' => $this->remainingTokensMinute,
            'remaining_tokens_month' => $this->remainingTokensMonth,
            'total' => $this->total,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
