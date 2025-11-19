<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace\Output;

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

        $this->assertSame($objects, $result->objects);
        $this->assertCount(2, $result->objects);
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new ObjectDetectionResult([]);

        $this->assertSame([], $result->objects);
        $this->assertCount(0, $result->objects);
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

        $this->assertCount(3, $result->objects);

        $this->assertSame('person', $result->objects[0]->label);
        $this->assertSame(0.95, $result->objects[0]->score);
        $this->assertSame(10.5, $result->objects[0]->xmin);
        $this->assertSame(20.5, $result->objects[0]->ymin);
        $this->assertSame(100.5, $result->objects[0]->xmax);
        $this->assertSame(200.5, $result->objects[0]->ymax);

        $this->assertSame('dog', $result->objects[1]->label);
        $this->assertSame(0.80, $result->objects[1]->score);

        $this->assertSame('car', $result->objects[2]->label);
        $this->assertSame(0.60, $result->objects[2]->score);
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = ObjectDetectionResult::fromArray([]);

        $this->assertCount(0, $result->objects);
        $this->assertSame([], $result->objects);
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

        $this->assertCount(1, $result->objects);
        $this->assertInstanceOf(DetectedObject::class, $result->objects[0]);
        $this->assertSame('bicycle', $result->objects[0]->label);
        $this->assertSame(0.99, $result->objects[0]->score);
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

        $this->assertSame('first', $result->objects[0]->label);
        $this->assertSame('second', $result->objects[1]->label);
        $this->assertSame('third', $result->objects[2]->label);
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

        $this->assertCount(3, $result->objects);

        // Normalized
        $this->assertSame(0.1, $result->objects[0]->xmin);
        $this->assertSame(0.9, $result->objects[0]->xmax);

        // Pixels
        $this->assertSame(100.0, $result->objects[1]->xmin);
        $this->assertSame(500.0, $result->objects[1]->xmax);

        // Negative
        $this->assertSame(-50.0, $result->objects[2]->xmin);
        $this->assertSame(50.0, $result->objects[2]->xmax);
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

        $this->assertCount(4, $result->objects);

        // Check multiple instances of same class
        $personCount = array_filter($result->objects, fn ($obj) => 'person' === $obj->label);
        $this->assertCount(2, $personCount);
    }
}
