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
use Symfony\AI\Platform\Bridge\HuggingFace\Output\ZeroShotClassificationResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class ZeroShotClassificationResultTest extends TestCase
{
    #[TestDox('Construction with required parameters creates valid instance')]
    public function testConstruction()
    {
        $labels = ['positive', 'negative'];
        $scores = [0.9, 0.1];

        $result = new ZeroShotClassificationResult($labels, $scores);

        $this->assertSame($labels, $result->labels);
        $this->assertSame($scores, $result->scores);
        $this->assertNull($result->sequence);
    }

    #[TestDox('Construction with all parameters creates valid instance')]
    public function testConstructionWithAllParameters()
    {
        $labels = ['sports', 'politics', 'technology'];
        $scores = [0.7, 0.2, 0.1];
        $sequence = 'This is a test sequence';

        $result = new ZeroShotClassificationResult($labels, $scores, $sequence);

        $this->assertSame($labels, $result->labels);
        $this->assertSame($scores, $result->scores);
        $this->assertSame($sequence, $result->sequence);
    }

    #[TestDox('Constructor accepts various parameter combinations')]
    #[TestWith([['positive'], [1.0], null])]
    #[TestWith([['a', 'b', 'c'], [0.5, 0.3, 0.2], 'test'])]
    #[TestWith([[], [], ''])]
    #[TestWith([['label1', 'label2'], [0.8, 0.2], 'Long sequence with multiple words and punctuation!'])]
    public function testConstructorWithDifferentValues(array $labels, array $scores, ?string $sequence)
    {
        $result = new ZeroShotClassificationResult($labels, $scores, $sequence);

        $this->assertSame($labels, $result->labels);
        $this->assertSame($scores, $result->scores);
        $this->assertSame($sequence, $result->sequence);
    }

    #[TestDox('fromArray creates instance with required fields')]
    public function testFromArrayWithRequiredFields()
    {
        $data = [
            'labels' => ['positive', 'negative'],
            'scores' => [0.85, 0.15],
        ];

        $result = ZeroShotClassificationResult::fromArray($data);

        $this->assertSame(['positive', 'negative'], $result->labels);
        $this->assertSame([0.85, 0.15], $result->scores);
        $this->assertNull($result->sequence);
    }

    #[TestDox('fromArray creates instance with all fields')]
    public function testFromArrayWithAllFields()
    {
        $data = [
            'labels' => ['sports', 'politics', 'entertainment'],
            'scores' => [0.6, 0.3, 0.1],
            'sequence' => 'The match was exciting to watch',
        ];

        $result = ZeroShotClassificationResult::fromArray($data);

        $this->assertSame(['sports', 'politics', 'entertainment'], $result->labels);
        $this->assertSame([0.6, 0.3, 0.1], $result->scores);
        $this->assertSame('The match was exciting to watch', $result->sequence);
    }

    #[TestDox('fromArray handles optional sequence field correctly')]
    #[TestWith([['labels' => ['a', 'b'], 'scores' => [0.7, 0.3]]])]
    #[TestWith([['labels' => ['test'], 'scores' => [1.0], 'sequence' => '']])]
    #[TestWith([['labels' => ['x', 'y'], 'scores' => [0.5, 0.5], 'sequence' => 'Test sequence']])]
    public function testFromArrayWithOptionalSequence(array $data)
    {
        $result = ZeroShotClassificationResult::fromArray($data);

        $this->assertSame($data['labels'], $result->labels);
        $this->assertSame($data['scores'], $result->scores);
        $this->assertSame($data['sequence'] ?? null, $result->sequence);
    }

    #[TestDox('fromArray handles various label formats')]
    #[TestWith([['', 'empty', 'UPPERCASE', 'lowercase']])]
    #[TestWith([['with-dashes', 'with_underscores', 'with spaces', '123numeric']])]
    #[TestWith([['émoji 🎉', 'special-chars_123!@#']])]
    #[TestWith([['very_long_label_that_might_be_used_in_some_classification_tasks']])]
    public function testFromArrayWithVariousLabelFormats(array $labels)
    {
        $scores = array_fill(0, \count($labels), 1.0 / \count($labels));
        $data = ['labels' => $labels, 'scores' => $scores];

        $result = ZeroShotClassificationResult::fromArray($data);

        $this->assertSame($labels, $result->labels);
        $this->assertCount(\count($labels), $result->labels);
    }

    #[TestDox('fromArray handles various score formats')]
    #[TestWith([['labels' => ['a'], 'scores' => [0.0]]])]
    #[TestWith([['labels' => ['a'], 'scores' => [1.0]]])]
    #[TestWith([['labels' => ['a', 'b'], 'scores' => [0.123456789, 0.876543211]]])]
    #[TestWith([['labels' => ['a', 'b'], 'scores' => [-0.1, 1.1]]])] // Edge case
    #[TestWith([['labels' => ['a', 'b', 'c'], 'scores' => [0.5, 0.3, 0.2]]])]
    public function testFromArrayWithVariousScoreFormats(array $data)
    {
        $result = ZeroShotClassificationResult::fromArray($data);

        $this->assertSame($data['scores'], $result->scores);
        $this->assertCount(\count($data['scores']), $result->scores);

        foreach ($data['scores'] as $index => $expectedScore) {
            $this->assertSame($expectedScore, $result->scores[$index]);
        }
    }

    #[TestDox('Special sequence values are handled correctly')]
    #[TestWith([''])] // Empty string
    #[TestWith(['0'])] // String zero
    #[TestWith(['false'])] // String false
    #[TestWith(['null'])] // String null
    #[TestWith(['Multi\nline\nsequence'])] // Multiline string
    #[TestWith(['Sequence with émoji 🎉 and special chars!@#'])] // Unicode and special chars
    #[TestWith(['Very long sequence that might contain lots of text and details about the classification task and could potentially be quite lengthy'])]
    public function testSpecialSequenceValues(string $sequence)
    {
        $labels = ['test'];
        $scores = [1.0];

        $result1 = new ZeroShotClassificationResult($labels, $scores, $sequence);
        $result2 = ZeroShotClassificationResult::fromArray([
            'labels' => $labels,
            'scores' => $scores,
            'sequence' => $sequence,
        ]);

        $this->assertSame($sequence, $result1->sequence);
        $this->assertSame($sequence, $result2->sequence);
    }
}
