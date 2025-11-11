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

use App\Crop\Image\RelevantArea;
use PHPUnit\Framework\TestCase;

final class RelevantAreaTest extends TestCase
{
    public function testValidCase()
    {
        $relevantArea = new RelevantArea(150, 350, 450, 550);

        $this->assertEquals(150, $relevantArea->xMin);
        $this->assertEquals(350, $relevantArea->yMin);
        $this->assertEquals(450, $relevantArea->xMax);
        $this->assertEquals(550, $relevantArea->yMax);

        $this->assertEquals(300, $relevantArea->getWidth());
        $this->assertEquals(200, $relevantArea->getHeight());
    }

    public function testInvalidWidthWithNegative()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Width must be at least 1 pixel.');

        $relevantArea = new RelevantArea(500, 300, 100, 400);
        $relevantArea->getWidth();
    }

    public function testInvalidWidthWithZero()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Width must be at least 1 pixel.');

        $relevantArea = new RelevantArea(200, 300, 200, 400);
        $relevantArea->getWidth();
    }

    public function testInvalidHeightWithNegative()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Height must be at least 1 pixel.');

        $relevantArea = new RelevantArea(100, 500, 400, 200);
        $relevantArea->getHeight();
    }

    public function testInvalidHeightWithZero()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Height must be at least 1 pixel.');

        $relevantArea = new RelevantArea(100, 300, 400, 300);
        $relevantArea->getHeight();
    }
}
