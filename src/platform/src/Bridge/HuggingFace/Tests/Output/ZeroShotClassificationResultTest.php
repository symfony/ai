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

        $this->assertSame($labels, $result->getLabels());
        $this->assertSame($scores, $result->getScores());
        $this->assertNull($result->getSequence());
    }

    #[TestDox('Construction with all parameters creates valid instance')]
    public function testConstructionWithAllParameters()
    {
        $labels = ['sports', 'politics', 'technology'];
        $scores = [0.7, 0.2, 0.1];
        $sequence = 'This is a test sequence';

        $result = new ZeroShotClassificationResult($labels, $scores, $sequence);

        $this->assertSame($labels, $result->getLabels());
        $this->assertSame($scores, $result->getScores());
        $this->assertSame($sequence, $result->getSequence());
    }

    /**
     * @param array<string> $labels
     * @param array<float>  $scores
     */
    #[TestDox('Constructor accepts various parameter combinations')]
    #[TestWith([['positive'], [1.0], null])]
    #[TestWith([['a', 'b', 'c'], [0.5, 0.3, 0.2], 'test'])]
    #[TestWith([[], [], ''])]
    #[TestWith([['label1', 'label2'], [0.8, 0.2], 'Long sequence with multiple words and punctuation!'])]
    public function testConstructorWithDifferentValues(array $labels, array $scores, ?string $sequence)
    {
        $result = new ZeroShotClassificationResult($labels, $scores, $sequence);

        $this->assertSame($labels, $result->getLabels());
        $this->assertSame($scores, $result->getScores());
        $this->assertSame($sequence, $result->getSequence());
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

        $result = new ZeroShotClassificationResult($labels, $scores, $sequence);

        $this->assertSame($sequence, $result->getSequence());
    }
}
