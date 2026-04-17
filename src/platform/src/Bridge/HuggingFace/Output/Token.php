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
final class Token
{
    public function __construct(
        private readonly string $entityGroup,
        private readonly float $score,
        private readonly string $word,
        private readonly int $start,
        private readonly int $end,
    ) {
    }

    public function getEntityGroup(): string
    {
        return $this->entityGroup;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getWord(): string
    {
        return $this->word;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }
}
