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
final class DetectedObject
{
    public function __construct(
        private readonly string $label,
        private readonly float $score,
        private readonly float $xmin,
        private readonly float $ymin,
        private readonly float $xmax,
        private readonly float $ymax,
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

    public function getXmin(): float
    {
        return $this->xmin;
    }

    public function getYmin(): float
    {
        return $this->ymin;
    }

    public function getXmax(): float
    {
        return $this->xmax;
    }

    public function getYmax(): float
    {
        return $this->ymax;
    }
}
