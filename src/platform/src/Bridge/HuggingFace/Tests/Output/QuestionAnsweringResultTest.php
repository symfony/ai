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

        $this->assertSame('Paris', $result->getAnswer());
        $this->assertSame(10, $result->getStartIndex());
        $this->assertSame(15, $result->getEndIndex());
        $this->assertSame(0.95, $result->getScore());
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

        $this->assertSame($answer, $result->getAnswer());
        $this->assertSame($startIndex, $result->getStartIndex());
        $this->assertSame($endIndex, $result->getEndIndex());
        $this->assertSame($score, $result->getScore());
    }

    #[TestDox('Special index and score values are handled correctly')]
    #[TestWith(['answer', 0, 0, 0.123456789])]
    #[TestWith(['answer', 1000, 2000, -0.5])]
    #[TestWith(['answer', -10, -5, 1.5])]
    #[TestWith(['answer', 50, 25, 0.0])] // end before start
    public function testSpecialValues(string $answer, int $startIndex, int $endIndex, float $score)
    {
        $result = new QuestionAnsweringResult($answer, $startIndex, $endIndex, $score);

        $this->assertSame($answer, $result->getAnswer());
        $this->assertSame($startIndex, $result->getStartIndex());
        $this->assertSame($endIndex, $result->getEndIndex());
        $this->assertSame($score, $result->getScore());
    }
}
