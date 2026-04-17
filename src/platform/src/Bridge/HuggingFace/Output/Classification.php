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
final class Classification
{
    public function __construct(
        private readonly string $label,
        private readonly float $score,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getScore(): float
    {
        return $this->score;
    }
}
