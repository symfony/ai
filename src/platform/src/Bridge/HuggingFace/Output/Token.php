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
        public readonly string $entityGroup,
        public readonly float $score,
        public readonly string $word,
        public readonly int $start,
        public readonly int $end,
    ) {
    }
}
