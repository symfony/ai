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
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ChoiceAnswer;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\NoulAnswer;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ScoreAnswer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class AnswersTest extends TestCase
{
    public function testItGivesAccessToAnswers()
    {
        $noul = new NoulAnswer(0.92);
        $choice = new ChoiceAnswer('technical', ['billing' => 0.15, 'technical' => 0.85], 0.82);
        $score = new ScoreAnswer(1.6, ['Calm', 'Frustrated', 'Very angry'], [0.05, 0.3, 0.65], 0.78);

        $answers = new Answers(['is_urgent' => $noul, 'department' => $choice, 'frustration' => $score]);

        $this->assertTrue($answers->has('is_urgent'));
        $this->assertFalse($answers->has('unknown'));
        $this->assertSame($noul, $answers->get('is_urgent'));
        $this->assertSame($noul, $answers->getNoul('is_urgent'));
        $this->assertSame($choice, $answers->getChoice('department'));
        $this->assertSame($score, $answers->getScore('frustration'));
        $this->assertSame(['is_urgent' => $noul, 'department' => $choice, 'frustration' => $score], $answers->all());
    }

    public function testItThrowsExceptionForUnknownQuestion()
    {
        $answers = new Answers(['is_urgent' => new NoulAnswer(0.92)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no answer for question "department".');

        $answers->get('department');
    }

    public function testItThrowsExceptionForUnexpectedAnswerType()
    {
        $answers = new Answers(['is_urgent' => new NoulAnswer(0.92)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The answer for question "is_urgent" is of type "%s", "%s" expected.', NoulAnswer::class, ChoiceAnswer::class));

        $answers->getChoice('is_urgent');
    }
}
