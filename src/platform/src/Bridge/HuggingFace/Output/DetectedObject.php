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
final readonly class DetectedObject
{
    public function __construct(
        public string $label,
        public float $score,
        public float $xmin,
        public float $ymin,
        public float $xmax,
        public float $ymax,
    ) {
    }
}
