<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class EvaluationTest extends TestCase
{
    public function testItSerializesStateAndQuestions()
    {
        $evaluation = new Evaluation('My payouts have been failing for 3 days.', [
            'is_urgent' => new NoulQuestion('Does this convey urgency?'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]),
        ]);

        $this->assertSame([
            'state' => 'My payouts have been failing for 3 days.',
            'questions' => [
                'is_urgent' => ['type' => 'noul', 'instructions' => 'Does this convey urgency?'],
                'department' => ['type' => 'choice', 'instructions' => 'Which team should handle this?', 'criteria' => ['billing' => 'Payments', 'technical' => null]],
            ],
        ], $evaluation->jsonSerialize());
    }

    public function testItAcceptsStructuredState()
    {
        $state = [['role' => 'customer', 'text' => 'Where is my refund?']];

        $evaluation = new Evaluation($state, ['is_urgent' => new NoulQuestion('Does this convey urgency?')]);

        $this->assertSame($state, $evaluation->jsonSerialize()['state']);
    }

    public function testItThrowsExceptionWithoutQuestions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An evaluation requires at least one question.');

        new Evaluation('state', []);
    }

    public function testItThrowsExceptionForInvalidQuestion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Question "is_urgent" must be an instance of');

        /* @phpstan-ignore argument.type */
        new Evaluation('state', ['is_urgent' => 'Does this convey urgency?']);
    }
}
