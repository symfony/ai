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

final readonly class ImageSize
{
    public function __construct(
        public int $width,
        public int $height,
    ) {
    }
}
