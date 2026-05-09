<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Crop\Image;

/**
 * Source crop box and target output dimensions used by the {@see Resampler}.
 *
 * The resampler crops the source image to (srcX, srcY, srcW, srcH) and then
 * scales the result to (outW, outH).
 */
final readonly class Resampling
{
    public function __construct(
        public int $srcX,
        public int $srcY,
        public int $srcW,
        public int $srcH,
        public int $outW,
        public int $outH,
    ) {
    }
}
