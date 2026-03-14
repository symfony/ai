<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Reranking;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RerankingEntry
{
    public function __construct(
        private readonly int $index,
        private readonly float $score,
    ) {
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getScore(): float
    {
        return $this->score;
    }
}
