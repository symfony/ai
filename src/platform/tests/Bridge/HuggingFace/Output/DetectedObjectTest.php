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
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\DetectedObject;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class DetectedObjectTest extends TestCase
{
    #[TestDox('Construction with all parameters creates valid instance')]
    public function testConstruction()
    {
        $object = new DetectedObject(
            label: 'person',
            score: 0.95,
            xmin: 10.5,
            ymin: 20.5,
            xmax: 100.5,
            ymax: 200.5
        );

        $this->assertSame('person', $object->label);
        $this->assertSame(0.95, $object->score);
        $this->assertSame(10.5, $object->xmin);
        $this->assertSame(20.5, $object->ymin);
        $this->assertSame(100.5, $object->xmax);
        $this->assertSame(200.5, $object->ymax);
    }

    #[TestDox('Constructor accepts various bounding box coordinates')]
    #[TestWith(['car', 0.88, 0.0, 0.0, 50.0, 50.0])]
    #[TestWith(['dog', 0.75, -10.0, -10.0, 10.0, 10.0])]
    #[TestWith(['tree', 0.6, 100.5, 200.5, 300.5, 400.5])]
    #[TestWith(['building', 0.99, 0.0, 0.0, 1920.0, 1080.0])]
    #[TestWith(['', 0.5, 1.0, 2.0, 3.0, 4.0])]
    public function testConstructorWithVariousValues(
        string $label,
        float $score,
        float $xmin,
        float $ymin,
        float $xmax,
        float $ymax,
    ) {
        $object = new DetectedObject($label, $score, $xmin, $ymin, $xmax, $ymax);

        $this->assertSame($label, $object->label);
        $this->assertSame($score, $object->score);
        $this->assertSame($xmin, $object->xmin);
        $this->assertSame($ymin, $object->ymin);
        $this->assertSame($xmax, $object->xmax);
        $this->assertSame($ymax, $object->ymax);
    }

    #[TestDox('Constructor handles various edge cases and patterns')]
    #[TestWith(['person', 0.0, 0.0, 0.0, 1.0, 1.0])] // Zero score
    #[TestWith(['UPPERCASE_LABEL', 1.0, 0.0, 0.0, 1.0, 1.0])] // Perfect score
    #[TestWith(['label-with-dashes', 0.000001, 0.0, 0.0, 1.0, 1.0])] // Very low score
    #[TestWith(['label_with_underscores', 0.999999, 0.0, 0.0, 1.0, 1.0])] // Very high score
    #[TestWith(['label with spaces', 0.5, 0.0, 0.0, 1.0, 1.0])] // Space in label
    #[TestWith(['123', 0.8, 0.0, 0.0, 1.0, 1.0])] // Numeric label
    #[TestWith(['', 0.3, 0.0, 0.0, 1.0, 1.0])] // Empty label
    #[TestWith(['véhicule', 0.7, 0.0, 0.0, 1.0, 1.0])] // Accented characters
    #[TestWith(['🚗', 0.9, 0.0, 0.0, 1.0, 1.0])] // Emoji label
    public function testConstructorWithEdgeCasesAndPatterns(string $label, float $score, float $xmin, float $ymin, float $xmax, float $ymax)
    {
        $object = new DetectedObject($label, $score, $xmin, $ymin, $xmax, $ymax);

        $this->assertSame($label, $object->label);
        $this->assertSame($score, $object->score);
        $this->assertSame($xmin, $object->xmin);
        $this->assertSame($ymin, $object->ymin);
        $this->assertSame($xmax, $object->xmax);
        $this->assertSame($ymax, $object->ymax);
    }

    #[TestDox('Bounding box can represent different coordinate systems')]
    #[TestWith(['object', 0.9, 0.1, 0.2, 0.9, 0.8, 'normalized coordinates (0-1)'])]
    #[TestWith(['object', 0.9, 100.0, 200.0, 500.0, 600.0, 'pixel coordinates'])]
    #[TestWith(['object', 0.9, -50.0, -100.0, 50.0, 100.0, 'negative coordinates (possible in some coordinate systems)'])]
    public function testBoundingBoxCoordinateSystems(
        string $label,
        float $score,
        float $xmin,
        float $ymin,
        float $xmax,
        float $ymax,
        string $description,
    ) {
        $object = new DetectedObject($label, $score, $xmin, $ymin, $xmax, $ymax);

        $this->assertSame($xmin, $object->xmin);
        $this->assertSame($ymin, $object->ymin);
        $this->assertSame($xmax, $object->xmax);
        $this->assertSame($ymax, $object->ymax);
    }
}
