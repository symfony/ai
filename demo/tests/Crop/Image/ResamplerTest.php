<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Crop\Image;

use App\Crop\Image\Resampler;
use App\Crop\Image\Resampling;
use PHPUnit\Framework\TestCase;

final class ResamplerTest extends TestCase
{
    public function testResamplerCropsThenScalesPng()
    {
        $sourcePath = $this->writeSolidPng(200, 100);
        $resampler = new Resampler();
        $resampling = new Resampling(srcX: 50, srcY: 25, srcW: 100, srcH: 50, outW: 60, outH: 30);

        try {
            $bytes = $resampler->resample($sourcePath, 'image/png', $resampling);
        } finally {
            @unlink($sourcePath);
        }

        $info = getimagesizefromstring($bytes);
        $this->assertNotFalse($info);
        $this->assertSame(60, $info[0]);
        $this->assertSame(30, $info[1]);
        $this->assertSame('image/png', $info['mime']);
    }

    public function testResamplerRejectsUnsupportedMimeType()
    {
        $resampler = new Resampler();
        $resampling = new Resampling(srcX: 0, srcY: 0, srcW: 10, srcH: 10, outW: 10, outH: 10);

        $this->expectException(\InvalidArgumentException::class);
        $resampler->resample('/dev/null', 'image/tiff', $resampling);
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function writeSolidPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        \assert(false !== $image);
        $color = imagecolorallocate($image, 200, 100, 50);
        \assert(false !== $color);
        imagefill($image, 0, 0, $color);
        $path = tempnam(sys_get_temp_dir(), 'resampler_test_').'.png';
        imagepng($image, $path);

        return $path;
    }
}
