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
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ImageSegment;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class ImageSegmentTest extends TestCase
{
    #[TestDox('Construction with all parameters creates valid instance')]
    public function testConstruction()
    {
        $segment = new ImageSegment(
            label: 'person',
            score: 0.95,
            mask: 'base64_encoded_mask_data'
        );

        $this->assertSame('person', $segment->label);
        $this->assertSame(0.95, $segment->score);
        $this->assertSame('base64_encoded_mask_data', $segment->mask);
    }

    #[TestDox('Construction with null score creates valid instance')]
    public function testConstructionWithNullScore()
    {
        $segment = new ImageSegment(
            label: 'background',
            score: null,
            mask: 'mask_data'
        );

        $this->assertSame('background', $segment->label);
        $this->assertNull($segment->score);
        $this->assertSame('mask_data', $segment->mask);
    }

    #[TestDox('Constructor accepts various parameter combinations')]
    #[TestWith(['person', 0.9, 'mask'])]
    #[TestWith(['UPPERCASE_LABEL', 0.8, 'base64_encoded_mask_/+='])]
    #[TestWith(['label-with-dashes', 0.7, 'simple_mask'])]
    #[TestWith(['label_with_underscores', 0.6, ''])]
    #[TestWith(['label with spaces', 0.5, "mask\nwith\nnewlines"])]
    #[TestWith(['', 0.4, 'mask with special chars !@#$%^&*()'])]
    #[TestWith(['123numeric', 0.0, 'mask'])]
    #[TestWith(['spécial_caractères', 1.0, 'mask'])]
    #[TestWith(['🎨', null, 'mask'])]
    #[TestWith(['label', 0.000001, 'very_long_mask_data'])]
    #[TestWith(['label', 0.999999, 'mask'])]
    public function testConstructorWithVariousParameterCombinations(string $label, ?float $score, string $mask)
    {
        $segment = new ImageSegment($label, $score, $mask);

        $this->assertSame($label, $segment->label);
        $this->assertSame($score, $segment->score);
        $this->assertSame($mask, $segment->mask);
    }

    #[TestDox('Typical base64 mask patterns')]
    #[TestWith(['object', 0.9, 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'iVBORw0KGgo', 'small base64 encoded mask'])]
    #[TestWith(['none', null, '', '', 'empty mask'])]
    #[TestWith(['region', 0.8, 'data:image/png;base64,iVBORw0KGgo...', 'data:image/png', 'URL-style mask reference'])]
    public function testTypicalBase64Masks(
        string $label,
        ?float $score,
        string $mask,
        string $expectedStart,
        string $description,
    ) {
        $segment = new ImageSegment($label, $score, $mask);

        if ('' === $expectedStart) {
            $this->assertSame('', $segment->mask);
        } else {
            $this->assertStringStartsWith($expectedStart, $segment->mask);
        }
    }
}
