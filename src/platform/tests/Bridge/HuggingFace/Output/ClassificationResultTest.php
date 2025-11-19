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
use Symfony\AI\Platform\Bridge\HuggingFace\Output\Classification;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ClassificationResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class ClassificationResultTest extends TestCase
{
    #[TestDox('Construction with classifications array creates valid instance')]
    public function testConstruction()
    {
        $classifications = [
            new Classification('positive', 0.9),
            new Classification('negative', 0.1),
        ];

        $result = new ClassificationResult($classifications);

        $this->assertSame($classifications, $result->classifications);
        $this->assertCount(2, $result->classifications);
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new ClassificationResult([]);

        $this->assertSame([], $result->classifications);
        $this->assertCount(0, $result->classifications);
    }

    #[TestDox('fromArray creates instance with Classification objects')]
    public function testFromArray()
    {
        $data = [
            ['label' => 'positive', 'score' => 0.95],
            ['label' => 'negative', 'score' => 0.03],
            ['label' => 'neutral', 'score' => 0.02],
        ];

        $result = ClassificationResult::fromArray($data);

        $this->assertCount(3, $result->classifications);

        $this->assertSame('positive', $result->classifications[0]->label);
        $this->assertSame(0.95, $result->classifications[0]->score);

        $this->assertSame('negative', $result->classifications[1]->label);
        $this->assertSame(0.03, $result->classifications[1]->score);

        $this->assertSame('neutral', $result->classifications[2]->label);
        $this->assertSame(0.02, $result->classifications[2]->score);
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = ClassificationResult::fromArray([]);

        $this->assertCount(0, $result->classifications);
        $this->assertSame([], $result->classifications);
    }

    #[TestDox('fromArray with single classification')]
    public function testFromArrayWithSingleClassification()
    {
        $data = [
            ['label' => 'confident', 'score' => 0.99],
        ];

        $result = ClassificationResult::fromArray($data);

        $this->assertCount(1, $result->classifications);
        $this->assertInstanceOf(Classification::class, $result->classifications[0]);
        $this->assertSame('confident', $result->classifications[0]->label);
        $this->assertSame(0.99, $result->classifications[0]->score);
    }

    #[TestDox('fromArray preserves order of classifications')]
    public function testFromArrayPreservesOrder()
    {
        $data = [
            ['label' => 'first', 'score' => 0.5],
            ['label' => 'second', 'score' => 0.3],
            ['label' => 'third', 'score' => 0.2],
        ];

        $result = ClassificationResult::fromArray($data);

        $this->assertSame('first', $result->classifications[0]->label);
        $this->assertSame('second', $result->classifications[1]->label);
        $this->assertSame('third', $result->classifications[2]->label);
    }

    #[TestDox('fromArray handles various label formats')]
    #[TestWith([['label' => '', 'score' => 0.5]])]
    #[TestWith([['label' => 'UPPERCASE', 'score' => 0.5]])]
    #[TestWith([['label' => 'with-dashes', 'score' => 0.5]])]
    #[TestWith([['label' => 'with_underscores', 'score' => 0.5]])]
    #[TestWith([['label' => 'with spaces', 'score' => 0.5]])]
    #[TestWith([['label' => '123numeric', 'score' => 0.5]])]
    public function testFromArrayWithVariousLabelFormats(array $classification)
    {
        $result = ClassificationResult::fromArray([$classification]);

        $this->assertCount(1, $result->classifications);
        $this->assertSame($classification['label'], $result->classifications[0]->label);
        $this->assertSame($classification['score'], $result->classifications[0]->score);
    }
}
