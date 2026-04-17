<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Output;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MaskFill
{
    public function __construct(
        private readonly int $token,
        private readonly string $tokenStr,
        private readonly string $sequence,
        private readonly float $score,
    ) {
    }

    public function getToken(): int
    {
        return $this->token;
    }

    public function getTokenStr(): string
    {
        return $this->tokenStr;
    }

    public function getSequence(): string
    {
        return $this->sequence;
    }

    public function getScore(): float
    {
        return $this->score;
    }
}
