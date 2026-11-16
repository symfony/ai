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
use Symfony\AI\Platform\Bridge\HuggingFace\Output\DetectedObject;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ObjectDetectionResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class ObjectDetectionResultTest extends TestCase
{
    #[TestDox('Construction with objects array creates valid instance')]
    public function testConstruction()
    {
        $objects = [
            new DetectedObject('person', 0.95, 10.0, 20.0, 100.0, 200.0),
            new DetectedObject('car', 0.85, 50.0, 60.0, 150.0, 160.0),
        ];

        $result = new ObjectDetectionResult($objects);

        $this->assertSame($objects, $result->getObjects());
        $this->assertCount(2, $result->getObjects());
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new ObjectDetectionResult([]);

        $this->assertSame([], $result->getObjects());
        $this->assertCount(0, $result->getObjects());
    }
}
