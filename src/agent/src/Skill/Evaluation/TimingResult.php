<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation;

/**
 * Captures timing and token usage for an evaluation run.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TimingResult
{
    public function __construct(
        private readonly int $totalTokens,
        private readonly int $durationMs,
    ) {
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    /**
     * @return array{total_tokens: int, duration_ms: int}
     */
    public function toArray(): array
    {
        return [
            'total_tokens' => $this->totalTokens,
            'duration_ms' => $this->durationMs,
        ];
    }
}
