<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\Output;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ImageSegment;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ImageSegmentationResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class ImageSegmentationResultTest extends TestCase
{
    #[TestDox('Construction with segments array creates valid instance')]
    public function testConstruction()
    {
        $segments = [
            new ImageSegment('person', 0.95, 'mask1'),
            new ImageSegment('background', 0.85, 'mask2'),
            new ImageSegment('car', 0.75, 'mask3'),
        ];

        $result = new ImageSegmentationResult($segments);

        $this->assertSame($segments, $result->getSegments());
        $this->assertCount(3, $result->getSegments());
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new ImageSegmentationResult([]);

        $this->assertSame([], $result->getSegments());
        $this->assertCount(0, $result->getSegments());
    }
}
