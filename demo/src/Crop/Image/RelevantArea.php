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

final readonly class RelevantArea
{
    public function __construct(
        public int $xMin,
        public int $yMin,
        public int $xMax,
        public int $yMax,
    ) {
    }

    /**
     * @return int<1, max>
     */
    public function getWidth(): int
    {
        $width = $this->xMax - $this->xMin;

        if ($width < 1) {
            throw new \InvalidArgumentException('Width must be at least 1 pixel.');
        }

        return $width;
    }

    /**
     * @return int<1, max>
     */
    public function getHeight(): int
    {
        $height = $this->yMax - $this->yMin;

        if ($height < 1) {
            throw new \InvalidArgumentException('Height must be at least 1 pixel.');
        }

        return $height;
    }
}
