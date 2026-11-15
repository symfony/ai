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
use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class ChoiceQuestionTest extends TestCase
{
    public function testItExposesItsOptions()
    {
        $question = new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]);

        $this->assertSame('Which team should handle this?', $question->getInstructions());
        $this->assertSame(['billing' => 'Payments', 'technical' => null], $question->getOptions());
    }

    public function testItThrowsExceptionWithLessThanTwoOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A choice question requires at least two options.');

        new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments']);
    }
}
