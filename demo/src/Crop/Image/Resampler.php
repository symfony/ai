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
 * Crops the image at the source coordinates from {@see Resampling} and then
 * scales the cropped piece to the target output dimensions.
 *
 * Returns the encoded image bytes; the caller assembles the data URL.
 */
final readonly class Resampler
{
    private const int JPEG_QUALITY = 90;

    public function resample(string $imagePath, string $mimeType, Resampling $resampling): string
    {
        $source = $this->load($imagePath, $mimeType);

        $cropped = imagecrop($source, [
            'x' => $resampling->srcX,
            'y' => $resampling->srcY,
            'width' => $resampling->srcW,
            'height' => $resampling->srcH,
        ]);

        if (false === $cropped) {
            throw new \RuntimeException('Failed to crop the image.');
        }

        $scaled = imagescale($cropped, $resampling->outW, $resampling->outH);

        if (false === $scaled) {
            throw new \RuntimeException('Failed to scale the image.');
        }

        return $this->encode($scaled, $mimeType);
    }

    private function load(string $imagePath, string $mimeType): \GdImage
    {
        $image = match ($mimeType) {
            'image/png' => imagecreatefrompng($imagePath),
            'image/jpeg' => imagecreatefromjpeg($imagePath),
            'image/gif' => imagecreatefromgif($imagePath),
            'image/vnd.wap.wbmp' => imagecreatefromwbmp($imagePath),
            'image/webp' => imagecreatefromwebp($imagePath),
            default => throw new \InvalidArgumentException(\sprintf('Mime type "%s" is not supported.', $mimeType)),
        };

        if (false === $image) {
            throw new \RuntimeException('Failed to create an image from the provided data.');
        }

        return $image;
    }

    private function encode(\GdImage $image, string $mimeType): string
    {
        ob_start();

        $ok = match ($mimeType) {
            'image/png' => imagepng($image),
            'image/jpeg' => imagejpeg($image, null, self::JPEG_QUALITY),
            'image/gif' => imagegif($image),
            'image/vnd.wap.wbmp' => imagewbmp($image),
            'image/webp' => imagewebp($image),
            default => throw new \InvalidArgumentException(\sprintf('Mime type "%s" is not supported.', $mimeType)),
        };

        $bytes = ob_get_clean();

        if (false === $ok || false === $bytes) {
            throw new \RuntimeException('Failed to encode the image.');
        }

        return $bytes;
    }
}
