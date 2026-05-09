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

use App\Crop\Image\Ratio;
use PHPUnit\Framework\TestCase;

final class RatioTest extends TestCase
{
    public function testGetWidthHeight()
    {
        $this->assertSame([1, 1], Ratio::SQUARE->getWidthHeight());
        $this->assertSame([16, 9], Ratio::LANDSCAPE->getWidthHeight());
        $this->assertSame([9, 16], Ratio::PORTRAIT->getWidthHeight());
    }

    public function testGetLabel()
    {
        $this->assertSame('Square (1:1)', Ratio::SQUARE->getLabel());
        $this->assertSame('Landscape (16:9)', Ratio::LANDSCAPE->getLabel());
        $this->assertSame('Portrait (9:16)', Ratio::PORTRAIT->getLabel());
    }

    public function testTargetHeight()
    {
        $this->assertSame(800, Ratio::SQUARE->targetHeight(800));
        $this->assertSame(450, Ratio::LANDSCAPE->targetHeight(800));
        $this->assertSame(1422, Ratio::PORTRAIT->targetHeight(800));
        $this->assertSame(100, Ratio::SQUARE->targetHeight(100));
        $this->assertSame(56, Ratio::LANDSCAPE->targetHeight(100));
        $this->assertSame(178, Ratio::PORTRAIT->targetHeight(100));
    }
}
