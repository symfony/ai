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
use Symfony\AI\Platform\Bridge\HuggingFace\Output\QuestionAnsweringResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class QuestionAnsweringResultTest extends TestCase
{
    #[TestDox('Construction with required parameters creates valid instance')]
    public function testConstruction()
    {
        $result = new QuestionAnsweringResult('Paris', 10, 15, 0.95);

        $this->assertSame('Paris', $result->answer);
        $this->assertSame(10, $result->startIndex);
        $this->assertSame(15, $result->endIndex);
        $this->assertSame(0.95, $result->score);
    }

    #[TestDox('Constructor accepts various parameter combinations')]
    #[TestWith(['short', 0, 5, 0.8])]
    #[TestWith(['The capital is Paris', 15, 20, 0.99])]
    #[TestWith(['', 0, 0, 0.0])]
    #[TestWith(['very long answer that might span multiple words and sentences', 50, 120, 1.0])]
    #[TestWith(['42', 100, 102, 0.5])]
    #[TestWith(['émoji 🎉 answer', 25, 40, 0.75])]
    #[TestWith(['special-chars_123!@#', 0, 18, 0.65])]
    public function testConstructorWithDifferentValues(string $answer, int $startIndex, int $endIndex, float $score)
    {
        $result = new QuestionAnsweringResult($answer, $startIndex, $endIndex, $score);

        $this->assertSame($answer, $result->answer);
        $this->assertSame($startIndex, $result->startIndex);
        $this->assertSame($endIndex, $result->endIndex);
        $this->assertSame($score, $result->score);
    }

    #[TestDox('fromArray creates instance from array data')]
    public function testFromArray()
    {
        $data = [
            'answer' => 'Machine learning',
            'start' => 25,
            'end' => 40,
            'score' => 0.87,
        ];

        $result = QuestionAnsweringResult::fromArray($data);

        $this->assertSame('Machine learning', $result->answer);
        $this->assertSame(25, $result->startIndex);
        $this->assertSame(40, $result->endIndex);
        $this->assertSame(0.87, $result->score);
    }

    #[TestDox('fromArray handles various data formats')]
    #[TestWith([['answer' => 'Yes', 'start' => 0, 'end' => 3, 'score' => 1.0]])]
    #[TestWith([['answer' => 'No answer found', 'start' => -1, 'end' => -1, 'score' => 0.0]])]
    #[TestWith([['answer' => '2023', 'start' => 100, 'end' => 104, 'score' => 0.95]])]
    #[TestWith([['answer' => 'The quick brown fox', 'start' => 0, 'end' => 19, 'score' => 0.88]])]
    public function testFromArrayWithVariousData(array $data)
    {
        $result = QuestionAnsweringResult::fromArray($data);

        $this->assertSame($data['answer'], $result->answer);
        $this->assertSame($data['start'], $result->startIndex);
        $this->assertSame($data['end'], $result->endIndex);
        $this->assertSame($data['score'], $result->score);
    }

    #[TestDox('Special index and score values are handled correctly')]
    #[TestWith(['answer', 0, 0, 0.123456789])]
    #[TestWith(['answer', 1000, 2000, -0.5])]
    #[TestWith(['answer', -10, -5, 1.5])]
    #[TestWith(['answer', 50, 25, 0.0])] // end before start
    public function testSpecialValues(string $answer, int $startIndex, int $endIndex, float $score)
    {
        $result = new QuestionAnsweringResult($answer, $startIndex, $endIndex, $score);

        $this->assertSame($answer, $result->answer);
        $this->assertSame($startIndex, $result->startIndex);
        $this->assertSame($endIndex, $result->endIndex);
        $this->assertSame($score, $result->score);
    }
}
