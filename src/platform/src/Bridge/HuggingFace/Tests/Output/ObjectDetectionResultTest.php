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

    #[TestDox('fromArray creates instance with DetectedObject objects')]
    public function testFromArray()
    {
        $data = [
            [
                'label' => 'person',
                'score' => 0.95,
                'box' => ['xmin' => 10.5, 'ymin' => 20.5, 'xmax' => 100.5, 'ymax' => 200.5],
            ],
            [
                'label' => 'dog',
                'score' => 0.80,
                'box' => ['xmin' => 150.0, 'ymin' => 100.0, 'xmax' => 250.0, 'ymax' => 300.0],
            ],
            [
                'label' => 'car',
                'score' => 0.60,
                'box' => ['xmin' => 300.0, 'ymin' => 50.0, 'xmax' => 500.0, 'ymax' => 150.0],
            ],
        ];

        $result = ObjectDetectionResult::fromArray($data);

        $this->assertCount(3, $result->getObjects());

        $this->assertSame('person', $result->getObjects()[0]->getLabel());
        $this->assertSame(0.95, $result->getObjects()[0]->getScore());
        $this->assertSame(10.5, $result->getObjects()[0]->getXmin());
        $this->assertSame(20.5, $result->getObjects()[0]->getYmin());
        $this->assertSame(100.5, $result->getObjects()[0]->getXmax());
        $this->assertSame(200.5, $result->getObjects()[0]->getYmax());

        $this->assertSame('dog', $result->getObjects()[1]->getLabel());
        $this->assertSame(0.80, $result->getObjects()[1]->getScore());

        $this->assertSame('car', $result->getObjects()[2]->getLabel());
        $this->assertSame(0.60, $result->getObjects()[2]->getScore());
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = ObjectDetectionResult::fromArray([]);

        $this->assertCount(0, $result->getObjects());
        $this->assertSame([], $result->getObjects());
    }

    #[TestDox('fromArray with single detection')]
    public function testFromArrayWithSingleDetection()
    {
        $data = [
            [
                'label' => 'bicycle',
                'score' => 0.99,
                'box' => ['xmin' => 0.0, 'ymin' => 0.0, 'xmax' => 50.0, 'ymax' => 50.0],
            ],
        ];

        $result = ObjectDetectionResult::fromArray($data);

        $this->assertCount(1, $result->getObjects());
        $this->assertInstanceOf(DetectedObject::class, $result->getObjects()[0]);
        $this->assertSame('bicycle', $result->getObjects()[0]->getLabel());
        $this->assertSame(0.99, $result->getObjects()[0]->getScore());
    }

    #[TestDox('fromArray preserves order of detections')]
    public function testFromArrayPreservesOrder()
    {
        $data = [
            ['label' => 'first', 'score' => 0.9, 'box' => ['xmin' => 1.0, 'ymin' => 1.0, 'xmax' => 2.0, 'ymax' => 2.0]],
            ['label' => 'second', 'score' => 0.8, 'box' => ['xmin' => 3.0, 'ymin' => 3.0, 'xmax' => 4.0, 'ymax' => 4.0]],
            ['label' => 'third', 'score' => 0.7, 'box' => ['xmin' => 5.0, 'ymin' => 5.0, 'xmax' => 6.0, 'ymax' => 6.0]],
        ];

        $result = ObjectDetectionResult::fromArray($data);

        $this->assertSame('first', $result->getObjects()[0]->getLabel());
        $this->assertSame('second', $result->getObjects()[1]->getLabel());
        $this->assertSame('third', $result->getObjects()[2]->getLabel());
    }

    #[TestDox('fromArray handles various coordinate systems')]
    public function testFromArrayWithVariousCoordinateSystems()
    {
        $data = [
            // Normalized coordinates (0-1 range)
            ['label' => 'normalized', 'score' => 0.9, 'box' => ['xmin' => 0.1, 'ymin' => 0.2, 'xmax' => 0.9, 'ymax' => 0.8]],
            // Pixel coordinates
            ['label' => 'pixels', 'score' => 0.8, 'box' => ['xmin' => 100.0, 'ymin' => 200.0, 'xmax' => 500.0, 'ymax' => 600.0]],
            // Negative coordinates
            ['label' => 'negative', 'score' => 0.7, 'box' => ['xmin' => -50.0, 'ymin' => -100.0, 'xmax' => 50.0, 'ymax' => 100.0]],
        ];

        $result = ObjectDetectionResult::fromArray($data);

        $this->assertCount(3, $result->getObjects());

        // Normalized
        $this->assertSame(0.1, $result->getObjects()[0]->getXmin());
        $this->assertSame(0.9, $result->getObjects()[0]->getXmax());

        // Pixels
        $this->assertSame(100.0, $result->getObjects()[1]->getXmin());
        $this->assertSame(500.0, $result->getObjects()[1]->getXmax());

        // Negative
        $this->assertSame(-50.0, $result->getObjects()[2]->getXmin());
        $this->assertSame(50.0, $result->getObjects()[2]->getXmax());
    }

    #[TestDox('fromArray handles typical YOLO-style detections')]
    public function testFromArrayWithTypicalYOLOData()
    {
        $data = [
            ['label' => 'person', 'score' => 0.92, 'box' => ['xmin' => 342.0, 'ymin' => 198.0, 'xmax' => 428.0, 'ymax' => 436.0]],
            ['label' => 'person', 'score' => 0.88, 'box' => ['xmin' => 123.0, 'ymin' => 234.0, 'xmax' => 234.0, 'ymax' => 456.0]],
            ['label' => 'bicycle', 'score' => 0.85, 'box' => ['xmin' => 234.0, 'ymin' => 345.0, 'xmax' => 456.0, 'ymax' => 567.0]],
            ['label' => 'dog', 'score' => 0.76, 'box' => ['xmin' => 567.0, 'ymin' => 234.0, 'xmax' => 678.0, 'ymax' => 345.0]],
        ];

        $result = ObjectDetectionResult::fromArray($data);

        $this->assertCount(4, $result->getObjects());

        // Check multiple instances of same class
        $personCount = array_filter($result->getObjects(), static fn ($obj) => 'person' === $obj->getLabel());
        $this->assertCount(2, $personCount);
    }
}
