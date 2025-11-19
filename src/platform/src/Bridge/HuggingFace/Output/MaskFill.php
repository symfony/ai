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
        public readonly int $token,
        public readonly string $tokenStr,
        public readonly string $sequence,
        public readonly float $score,
    ) {
    }
}
