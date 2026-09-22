<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests\Answer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ScoreAnswer;

final class ScoreAnswerTest extends TestCase
{
    public function testItIsCreatedFromArray()
    {
        $answer = ScoreAnswer::fromArray([
            'type' => 'score',
            'score' => 1.43,
            'confidence' => 0.35,
            'legend' => ['0' => 'Cosmetic', '1' => 'Workaround exists', '2' => 'Blocking'],
            'probabilities' => ['0' => 0.0, '1' => 0.57, '2' => 0.43],
        ]);

        $this->assertSame(1.43, $answer->getScore());
        $this->assertSame([0 => 'Cosmetic', 1 => 'Workaround exists', 2 => 'Blocking'], $answer->getLegend());
        $this->assertSame([0 => 0.0, 1 => 0.57, 2 => 0.43], $answer->getProbabilities());
        $this->assertSame(0.35, $answer->getConfidence());
    }

    public function testItConvertsIntegerNumbersToFloats()
    {
        $answer = ScoreAnswer::fromArray([
            'type' => 'score',
            'score' => 1,
            'confidence' => 1,
            'legend' => ['0' => 'Calm', '1' => 'Frustrated'],
            'probabilities' => ['0' => 0, '1' => 1],
        ]);

        $this->assertSame(1.0, $answer->getScore());
        $this->assertSame([0 => 0.0, 1 => 1.0], $answer->getProbabilities());
        $this->assertSame(1.0, $answer->getConfidence());
    }

    public function testItExposesTheValuesItIsConstructedWith()
    {
        $answer = new ScoreAnswer(1.6, ['Calm', 'Frustrated', 'Very angry'], [0.05, 0.3, 0.65], 0.78);

        $this->assertSame(1.6, $answer->getScore());
        $this->assertSame(['Calm', 'Frustrated', 'Very angry'], $answer->getLegend());
        $this->assertSame([0.05, 0.3, 0.65], $answer->getProbabilities());
        $this->assertSame(0.78, $answer->getConfidence());
    }
}
