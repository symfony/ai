<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Crop;

use App\Crop\Image\Analyzer;
use App\Crop\Image\Resampler;

final readonly class ImageCropper
{
    public function __construct(
        private Analyzer $analyzer,
        private Resampler $resampler,
    ) {
    }

    public function crop(string $imageData, string $format, int $width): string
    {
        $relevantArea = $this->analyzer->getRelevantArea($imageData);

        return $this->resampler->resample($imageData, $relevantArea, $format, $width);
    }
}
