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

final readonly class Resampler
{
    /**
     * @param string       $imageData base64-encoded image data
     * @param RelevantArea $area      detected relevant area
     * @param string       $format    aspect ratio format (1:1, 16:9, 9:16)
     * @param int          $newWidth  new width in pixels (400, 800 or 1200)
     *
     * @return string base64-encoded image data
     */
    public function resample(string $imageData, RelevantArea $area, string $format, int $newWidth): string
    {
        $filePath = sys_get_temp_dir().'/'.uniqid('resample_', true);
        file_put_contents($filePath, base64_decode(explode(',', $imageData, 2)[1]));
        $mimeType = explode(';', explode(':', $imageData, 2)[1], 2)[0];

        switch ($mimeType) {
            case 'image/png':
                $image = imagecreatefrompng($filePath);
                break;
            case 'image/jpeg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($filePath);
                break;
            case 'image/vnd.wap.wbmp':
                $image = imagecreatefromwbmp($filePath);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($filePath);
                break;
            default:
                throw new \InvalidArgumentException(\sprintf('Mime type "%s" is not supported', $mimeType));
        }

        if (false === $image) {
            throw new \RuntimeException('Failed to create an image from the provided data.');
        }

        $cropped = imagecreatetruecolor($area->getWidth(), $area->getHeight());

        if (false === $cropped) {
            throw new \RuntimeException('Failed to create a true color image for cropping.');
        }

        imagecopy($cropped, $image, 0, 0, $area->xMin, $area->yMin, $area->getWidth(), $area->getHeight());

        [$aspectWidth, $aspectHeight] = array_map(intval(...), explode(':', $format));
        $newHeight = (int) ($newWidth / $aspectWidth * $aspectHeight);

        if ($newHeight < 1 || $newWidth < 1) {
            throw new \InvalidArgumentException('New dimensions must be at least 1 pixel.');
        }

        $resampled = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resampled, $cropped, 0, 0, 0, 0, $newWidth, $newHeight, $area->getWidth(), $area->getHeight());

        ob_start();
        match (explode(';', explode(':', $imageData, 2)[1], 2)[0]) {
            'image/png' => imagepng($resampled),
            'image/jpeg' => imagejpeg($resampled, null, 85),
            'image/gif' => imagegif($resampled),
            'image/webp' => imagewebp($resampled),
            default => imagepng($resampled),
        };
        $imageContent = ob_get_clean();

        if (false === $imageContent) {
            throw new \RuntimeException('Failed to capture the image output.');
        }

        imagedestroy($image);
        imagedestroy($cropped);
        imagedestroy($resampled);
        unlink($filePath);

        return 'data:'.explode(';', explode(':', $imageData, 2)[1], 2)[0].';base64,'.base64_encode($imageContent);
    }
}
