<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Decision\Question;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class ScoreQuestionTest extends TestCase
{
    public function testItExposesItsLevelsAsList()
    {
        /* @phpstan-ignore argument.type */
        $question = new ScoreQuestion('How frustrated is the customer?', [2 => 'Calm', 5 => 'Very angry']);

        $this->assertSame('How frustrated is the customer?', $question->getInstructions());
        $this->assertSame(['Calm', 'Very angry'], $question->getLevels());
    }

    public function testItThrowsExceptionWithLessThanTwoLevels()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A score question requires at least two levels.');

        new ScoreQuestion('How frustrated is the customer?', ['Calm']);
    }
}
