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
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class ChoiceQuestionTest extends TestCase
{
    public function testItSerializes()
    {
        $question = new ChoiceQuestion('Which team should handle this?', [
            'billing' => 'Payments, invoicing, refunds',
            'technical' => 'Bugs, outages, integrations',
            'other' => null,
        ]);

        $this->assertSame([
            'type' => 'choice',
            'instructions' => 'Which team should handle this?',
            'criteria' => [
                'billing' => 'Payments, invoicing, refunds',
                'technical' => 'Bugs, outages, integrations',
                'other' => null,
            ],
        ], $question->jsonSerialize());
    }

    public function testItThrowsExceptionWithoutOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A choice question requires at least one option.');

        new ChoiceQuestion('Which team should handle this?', []);
    }
}
