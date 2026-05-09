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
use App\Crop\Image\Calculator;
use App\Crop\Image\CroppingTarget;
use App\Crop\Image\ImageSize;
use App\Crop\Image\Resampler;

final readonly class ImageCropper
{
    public function __construct(
        private Analyzer $analyzer,
        private Calculator $calculator,
        private Resampler $resampler,
    ) {
    }

    public function crop(string $imageDataUrl, CroppingTarget $target): string
    {
        [$mimeType, $bytes] = $this->parseDataUrl($imageDataUrl);

        $filePath = sys_get_temp_dir().'/'.uniqid('resample_', true);

        try {
            file_put_contents($filePath, $bytes);

            $info = getimagesize($filePath);
            if (false === $info) {
                throw new \InvalidArgumentException('The provided data is not a valid image.');
            }

            $relevantArea = $this->analyzer->getRelevantArea($imageDataUrl);
            $resampling = $this->calculator->calculate(new ImageSize($info[0], $info[1]), $relevantArea, $target);
            $output = $this->resampler->resample($filePath, $mimeType, $resampling);
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($output);
    }

    /**
     * @return array{string, string}
     */
    private function parseDataUrl(string $dataUrl): array
    {
        if (1 !== preg_match('#^data:([^;,]+);base64,(.+)$#s', $dataUrl, $matches)) {
            throw new \InvalidArgumentException('Image data must be a base64-encoded data URL.');
        }

        $bytes = base64_decode($matches[2], true);
        if (false === $bytes) {
            throw new \InvalidArgumentException('Image data is not valid base64.');
        }

        return [$matches[1], $bytes];
    }
}
