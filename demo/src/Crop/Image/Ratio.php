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

enum Ratio: string
{
    case SQUARE = '1:1';
    case LANDSCAPE = '16:9';
    case PORTRAIT = '9:16';

    public function getLabel(): string
    {
        return match ($this) {
            self::SQUARE => 'Square (1:1)',
            self::LANDSCAPE => 'Landscape (16:9)',
            self::PORTRAIT => 'Portrait (9:16)',
        };
    }

    /**
     * @return array{int, int}
     */
    public function getWidthHeight(): array
    {
        return match ($this) {
            self::SQUARE => [1, 1],
            self::LANDSCAPE => [16, 9],
            self::PORTRAIT => [9, 16],
        };
    }

    public function targetHeight(int $width): int
    {
        [$rW, $rH] = $this->getWidthHeight();

        return (int) round($width * $rH / $rW);
    }
}
