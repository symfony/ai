<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests\Question;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ScoreQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class ScoreQuestionTest extends TestCase
{
    public function testItSerializes()
    {
        $question = new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Frustrated', 'Very angry']);

        $this->assertSame([
            'type' => 'score',
            'instructions' => 'How frustrated is the customer?',
            'criteria' => ['Calm', 'Frustrated', 'Very angry'],
        ], $question->jsonSerialize());
    }

    public function testItSerializesLevelsAsList()
    {
        /* @phpstan-ignore argument.type */
        $question = new ScoreQuestion('How frustrated is the customer?', [3 => 'Calm', 7 => 'Very angry']);

        $this->assertSame(['Calm', 'Very angry'], $question->jsonSerialize()['criteria']);
    }

    public function testItThrowsExceptionWithLessThanTwoLevels()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A score question requires at least two levels.');

        new ScoreQuestion('How frustrated is the customer?', ['Calm']);
    }
}
