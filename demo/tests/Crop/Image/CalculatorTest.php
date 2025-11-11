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

use App\Crop\Image\Calculator;
use App\Crop\Image\CroppingTarget;
use App\Crop\Image\ImageSize;
use App\Crop\Image\Ratio;
use App\Crop\Image\RelevantArea;
use PHPUnit\Framework\TestCase;

final class CalculatorTest extends TestCase
{
    private Calculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    public function testSquareRatioWithCenteredSubject()
    {
        $resampling = $this->calculator->calculate(
            new ImageSize(1000, 1000),
            new RelevantArea(300, 300, 700, 700),
            new CroppingTarget(Ratio::SQUARE, 400),
        );

        $this->assertSame(300, $resampling->srcX);
        $this->assertSame(300, $resampling->srcY);
        $this->assertSame(400, $resampling->srcW);
        $this->assertSame(400, $resampling->srcH);
        $this->assertSame(400, $resampling->outW);
        $this->assertSame(400, $resampling->outH);
    }

    public function testLandscapeRatioExpandsHorizontallyAroundSubject()
    {
        // Subject 100x90 centred in a 1600x900 image; landscape ratio (16:9)
        // expands height-dominantly so the box becomes 160x90 around the subject.
        $resampling = $this->calculator->calculate(
            new ImageSize(1600, 900),
            new RelevantArea(750, 400, 850, 490),
            new CroppingTarget(Ratio::LANDSCAPE, 800),
        );

        $this->assertSame(720, $resampling->srcX);
        $this->assertSame(400, $resampling->srcY);
        $this->assertSame(160, $resampling->srcW);
        $this->assertSame(90, $resampling->srcH);
        $this->assertSame(800, $resampling->outW);
        $this->assertSame(450, $resampling->outH);
    }

    public function testPortraitRatioExpandsVerticallyAroundSubject()
    {
        // Subject 100x288 in a 900x1600 image; portrait ratio (9:16) means the
        // tall subject drives the box height, width expands to 162.
        $resampling = $this->calculator->calculate(
            new ImageSize(900, 1600),
            new RelevantArea(450, 700, 550, 988),
            new CroppingTarget(Ratio::PORTRAIT, 800),
        );

        $this->assertSame(419, $resampling->srcX);
        $this->assertSame(700, $resampling->srcY);
        $this->assertSame(162, $resampling->srcW);
        $this->assertSame(288, $resampling->srcH);
        $this->assertSame(800, $resampling->outW);
        $this->assertSame(1422, $resampling->outH);
    }

    public function testSubjectAtTopEdgeClampsBoxToYZero()
    {
        // Wide subject at the top of the image; square ratio inflates the box
        // to 900x900, which wants to centre at y=-300 and gets clamped to y=0.
        $resampling = $this->calculator->calculate(
            new ImageSize(1000, 1000),
            new RelevantArea(50, 100, 950, 200),
            new CroppingTarget(Ratio::SQUARE, 400),
        );

        $this->assertSame(50, $resampling->srcX);
        $this->assertSame(0, $resampling->srcY);
        $this->assertSame(900, $resampling->srcW);
        $this->assertSame(900, $resampling->srcH);
        $this->assertSame(400, $resampling->outW);
        $this->assertSame(400, $resampling->outH);
    }

    public function testSubjectNearBottomRightClampsBoxToImageEdge()
    {
        // Small subject 150x150 near the bottom-right; landscape ratio expands
        // width to 267, the centred x would land beyond imageW-boxW so it is
        // clamped to the right edge.
        $resampling = $this->calculator->calculate(
            new ImageSize(1000, 1000),
            new RelevantArea(800, 800, 950, 950),
            new CroppingTarget(Ratio::LANDSCAPE, 400),
        );

        $this->assertSame(733, $resampling->srcX);
        $this->assertSame(800, $resampling->srcY);
        $this->assertSame(267, $resampling->srcW);
        $this->assertSame(150, $resampling->srcH);
        $this->assertSame(400, $resampling->outW);
        $this->assertSame(225, $resampling->outH);
    }

    public function testRelevantAreaLargerThanRatioFitsInImageIsClamped()
    {
        // Tall narrow image (400x900) with a 300x700 subject. Square ratio
        // wants a 700x700 box but boxW exceeds imageW, so the box collapses
        // to the largest 1:1 rectangle inside the image (400x400).
        $resampling = $this->calculator->calculate(
            new ImageSize(400, 900),
            new RelevantArea(50, 100, 350, 800),
            new CroppingTarget(Ratio::SQUARE, 400),
        );

        $this->assertSame(0, $resampling->srcX);
        $this->assertSame(250, $resampling->srcY);
        $this->assertSame(400, $resampling->srcW);
        $this->assertSame(400, $resampling->srcH);
        $this->assertSame(400, $resampling->outW);
        $this->assertSame(400, $resampling->outH);
    }

    public function testMiniWidthAtLandscapeRatioPreservesSourceBoxAndShrinksOutput()
    {
        // The Mini (100px) target should not collapse the source box - it only
        // changes the output dimensions to 100x56.
        $resampling = $this->calculator->calculate(
            new ImageSize(1600, 900),
            new RelevantArea(750, 400, 850, 490),
            new CroppingTarget(Ratio::LANDSCAPE, 100),
        );

        $this->assertSame(720, $resampling->srcX);
        $this->assertSame(400, $resampling->srcY);
        $this->assertSame(160, $resampling->srcW);
        $this->assertSame(90, $resampling->srcH);
        $this->assertSame(100, $resampling->outW);
        $this->assertSame(56, $resampling->outH);
    }
}
