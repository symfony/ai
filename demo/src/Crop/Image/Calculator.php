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
 * Derives the source crop box and target output dimensions from the detected
 * relevant area and the user's chosen ratio + width.
 *
 * The pipeline is "crop then scale": find the smallest box at the requested
 * ratio that contains the relevant area, clamp it to the image bounds,
 * centre it on the relevant area's centroid, and produce a {@see Resampling}
 * the {@see Resampler} can apply directly.
 */
final readonly class Calculator
{
    public function calculate(ImageSize $size, RelevantArea $area, CroppingTarget $target): Resampling
    {
        [$rW, $rH] = $target->ratio->getWidthHeight();

        [$boxW, $boxH] = $this->coverBox($area->getWidth(), $area->getHeight(), $rW, $rH);
        [$boxW, $boxH] = $this->clampBoxToImage($boxW, $boxH, $size->width, $size->height, $rW, $rH);

        $centerX = ($area->xMin + $area->xMax) / 2;
        $centerY = ($area->yMin + $area->yMax) / 2;

        $srcX = $this->clamp($centerX - $boxW / 2, 0.0, $size->width - $boxW);
        $srcY = $this->clamp($centerY - $boxH / 2, 0.0, $size->height - $boxH);

        return new Resampling(
            srcX: (int) round($srcX),
            srcY: (int) round($srcY),
            srcW: (int) round($boxW),
            srcH: (int) round($boxH),
            outW: $target->width,
            outH: $target->ratio->targetHeight($target->width),
        );
    }

    /**
     * Smallest rectangle at ratio rW:rH that fully contains the relevant area.
     *
     * @return array{float, float}
     */
    private function coverBox(int $areaW, int $areaH, int $rW, int $rH): array
    {
        $areaRatio = $areaW / $areaH;
        $targetRatio = $rW / $rH;

        if ($areaRatio > $targetRatio) {
            return [(float) $areaW, $areaW * $rH / $rW];
        }

        return [$areaH * $rW / $rH, (float) $areaH];
    }

    /**
     * If the box exceeds image bounds on either axis, replace it with the
     * largest at-ratio rectangle that fits inside the image.
     *
     * @return array{float, float}
     */
    private function clampBoxToImage(float $boxW, float $boxH, int $imageW, int $imageH, int $rW, int $rH): array
    {
        if ($boxW <= $imageW && $boxH <= $imageH) {
            return [$boxW, $boxH];
        }

        $imageRatio = $imageW / $imageH;
        $targetRatio = $rW / $rH;

        if ($imageRatio > $targetRatio) {
            return [$imageH * $rW / $rH, (float) $imageH];
        }

        return [(float) $imageW, $imageW * $rH / $rW];
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
