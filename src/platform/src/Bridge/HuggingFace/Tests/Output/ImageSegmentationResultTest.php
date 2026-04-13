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

    #[TestDox('fromArray creates instance with ImageSegment objects')]
    public function testFromArray()
    {
        $data = [
            ['label' => 'person', 'score' => 0.95, 'mask' => 'person_mask_data'],
            ['label' => 'dog', 'score' => 0.80, 'mask' => 'dog_mask_data'],
            ['label' => 'background', 'score' => 0.60, 'mask' => 'background_mask_data'],
        ];

        $result = ImageSegmentationResult::fromArray($data);

        $this->assertCount(3, $result->getSegments());

        $this->assertSame('person', $result->getSegments()[0]->getLabel());
        $this->assertSame(0.95, $result->getSegments()[0]->getScore());
        $this->assertSame('person_mask_data', $result->getSegments()[0]->getMask());

        $this->assertSame('dog', $result->getSegments()[1]->getLabel());
        $this->assertSame(0.80, $result->getSegments()[1]->getScore());
        $this->assertSame('dog_mask_data', $result->getSegments()[1]->getMask());

        $this->assertSame('background', $result->getSegments()[2]->getLabel());
        $this->assertSame(0.60, $result->getSegments()[2]->getScore());
        $this->assertSame('background_mask_data', $result->getSegments()[2]->getMask());
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = ImageSegmentationResult::fromArray([]);

        $this->assertCount(0, $result->getSegments());
        $this->assertSame([], $result->getSegments());
    }

    #[TestDox('fromArray with single segment')]
    public function testFromArrayWithSingleSegment()
    {
        $data = [
            ['label' => 'object', 'score' => 0.99, 'mask' => 'single_mask'],
        ];

        $result = ImageSegmentationResult::fromArray($data);

        $this->assertCount(1, $result->getSegments());
        $this->assertInstanceOf(ImageSegment::class, $result->getSegments()[0]);
        $this->assertSame('object', $result->getSegments()[0]->getLabel());
        $this->assertSame(0.99, $result->getSegments()[0]->getScore());
        $this->assertSame('single_mask', $result->getSegments()[0]->getMask());
    }

    #[TestDox('fromArray preserves order of segments')]
    public function testFromArrayPreservesOrder()
    {
        $data = [
            ['label' => 'first', 'score' => 0.9, 'mask' => 'mask1'],
            ['label' => 'second', 'score' => 0.8, 'mask' => 'mask2'],
            ['label' => 'third', 'score' => 0.7, 'mask' => 'mask3'],
        ];

        $result = ImageSegmentationResult::fromArray($data);

        $this->assertSame('first', $result->getSegments()[0]->getLabel());
        $this->assertSame('second', $result->getSegments()[1]->getLabel());
        $this->assertSame('third', $result->getSegments()[2]->getLabel());
    }

    #[TestDox('fromArray handles various data formats')]
    public function testFromArrayWithVariousFormats()
    {
        $data = [
            ['label' => '', 'score' => 0.0, 'mask' => ''],
            ['label' => 'UPPERCASE', 'score' => 1.0, 'mask' => 'BASE64=='],
            ['label' => 'special-chars_123', 'score' => 0.5, 'mask' => 'data:image/png;base64,iVBORw0K...'],
            ['label' => 'unicode_标签', 'score' => 0.12345, 'mask' => 'very_long_long_long_long_long_long_long_long_long_long_long_long_long_long_long_long_long_long_long_long_mask'],
        ];

        $result = ImageSegmentationResult::fromArray($data);

        $this->assertCount(4, $result->getSegments());

        // Test empty values
        $this->assertSame('', $result->getSegments()[0]->getLabel());
        $this->assertSame(0.0, $result->getSegments()[0]->getScore());
        $this->assertSame('', $result->getSegments()[0]->getMask());

        // Test uppercase and base64
        $this->assertSame('UPPERCASE', $result->getSegments()[1]->getLabel());
        $this->assertSame(1.0, $result->getSegments()[1]->getScore());
        $this->assertSame('BASE64==', $result->getSegments()[1]->getMask());

        // Test special characters and data URI
        $this->assertSame('special-chars_123', $result->getSegments()[2]->getLabel());
        $this->assertSame(0.5, $result->getSegments()[2]->getScore());
        $this->assertStringStartsWith('data:image/png', $result->getSegments()[2]->getMask());

        // Test unicode and long mask
        $this->assertSame('unicode_标签', $result->getSegments()[3]->getLabel());
        $this->assertSame(0.12345, $result->getSegments()[3]->getScore());
        $this->assertStringContainsString('long_', $result->getSegments()[3]->getMask());
    }

    #[TestDox('fromArray handles typical segmentation results')]
    public function testFromArrayWithTypicalSegmentationData()
    {
        // Typical panoptic segmentation result
        $data = [
            ['label' => 'person-1', 'score' => 0.98, 'mask' => 'iVBORw0KGgoAAAANSUhEUgA...'],
            ['label' => 'person-2', 'score' => 0.97, 'mask' => 'iVBORw0KGgoAAAANSUhEUgB...'],
            ['label' => 'sky', 'score' => 0.95, 'mask' => 'iVBORw0KGgoAAAANSUhEUgC...'],
            ['label' => 'road', 'score' => 0.93, 'mask' => 'iVBORw0KGgoAAAANSUhEUgD...'],
            ['label' => 'building', 'score' => 0.89, 'mask' => 'iVBORw0KGgoAAAANSUhEUgE...'],
        ];

        $result = ImageSegmentationResult::fromArray($data);

        $this->assertCount(5, $result->getSegments());

        // Verify it handles instance segmentation (numbered instances)
        $this->assertSame('person-1', $result->getSegments()[0]->getLabel());
        $this->assertSame('person-2', $result->getSegments()[1]->getLabel());

        // Verify it handles semantic segmentation (class labels)
        $this->assertSame('sky', $result->getSegments()[2]->getLabel());
        $this->assertSame('road', $result->getSegments()[3]->getLabel());
        $this->assertSame('building', $result->getSegments()[4]->getLabel());
    }
}
