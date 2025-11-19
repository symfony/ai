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
use Symfony\AI\Platform\Bridge\HuggingFace\Output\SentenceSimilarityResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class SentenceSimilarityResultTest extends TestCase
{
    #[TestDox('Construction with similarities array creates valid instance')]
    public function testConstruction()
    {
        $similarities = [0.95, 0.87, 0.12];
        $result = new SentenceSimilarityResult($similarities);

        $this->assertSame($similarities, $result->similarities);
        $this->assertCount(3, $result->similarities);
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new SentenceSimilarityResult([]);

        $this->assertSame([], $result->similarities);
        $this->assertCount(0, $result->similarities);
    }

    #[TestDox('Constructor accepts various similarity arrays')]
    #[TestWith([[0.5]])]
    #[TestWith([[0.0, 1.0]])]
    #[TestWith([[0.95, 0.87, 0.12, 0.01]])]
    #[TestWith([[0.123456789, 0.987654321]])]
    #[TestWith([[-0.5, 2.0, 0.0]])] // Edge cases with negative and above 1.0
    #[TestWith([[0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0]])] // Many values
    public function testConstructorWithDifferentArrays(array $similarities)
    {
        $result = new SentenceSimilarityResult($similarities);

        $this->assertSame($similarities, $result->similarities);
        $this->assertCount(\count($similarities), $result->similarities);
    }

    #[TestDox('fromArray creates instance from array data')]
    public function testFromArray()
    {
        $data = [0.92, 0.78, 0.45, 0.23];

        $result = SentenceSimilarityResult::fromArray($data);

        $this->assertSame($data, $result->similarities);
        $this->assertCount(4, $result->similarities);
        $this->assertSame(0.92, $result->similarities[0]);
        $this->assertSame(0.78, $result->similarities[1]);
        $this->assertSame(0.45, $result->similarities[2]);
        $this->assertSame(0.23, $result->similarities[3]);
    }

    #[TestDox('fromArray handles various data formats')]
    #[TestWith([[]])] // Empty array
    #[TestWith([[0.999]])] // Single high similarity
    #[TestWith([[0.001]])] // Single low similarity
    #[TestWith([[0.5, 0.5, 0.5]])] // Equal similarities
    #[TestWith([[1.0, 0.9, 0.8, 0.7, 0.6]])] // Descending order
    #[TestWith([[0.1, 0.3, 0.2, 0.5, 0.4]])] // Random order
    #[TestWith([[0.0, 0.25, 0.5, 0.75, 1.0]])] // Regular intervals
    public function testFromArrayWithVariousData(array $data)
    {
        $result = SentenceSimilarityResult::fromArray($data);

        $this->assertSame($data, $result->similarities);
        $this->assertCount(\count($data), $result->similarities);

        foreach ($data as $index => $similarity) {
            $this->assertSame($similarity, $result->similarities[$index]);
        }
    }

    #[TestDox('Special float values are handled correctly')]
    #[TestWith([[0.0, 1.0, 0.5]])] // Boundary values
    #[TestWith([[-1.0, 2.0]])] // Outside normal range
    #[TestWith([[0.999999999, 0.000000001]])] // Very precise values
    #[TestWith([[0.33333333333333, 0.66666666666667]])] // Repeating decimals
    public function testSpecialFloatValues(array $similarities)
    {
        $result1 = new SentenceSimilarityResult($similarities);
        $result2 = SentenceSimilarityResult::fromArray($similarities);

        foreach ($similarities as $index => $expected) {
            $this->assertSame($expected, $result1->similarities[$index]);
            $this->assertSame($expected, $result2->similarities[$index]);
        }
    }
}
