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
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;

final class NoulQuestionTest extends TestCase
{
    public function testItSerializesWithoutCriteria()
    {
        $question = new NoulQuestion('Does this convey urgency?');

        $this->assertSame(['type' => 'noul', 'instructions' => 'Does this convey urgency?'], $question->jsonSerialize());
    }

    public function testItSerializesWithCriteria()
    {
        $question = new NoulQuestion('Does this convey urgency?', 'Explicitly time-sensitive', 'No urgency expressed');

        $this->assertSame([
            'type' => 'noul',
            'instructions' => 'Does this convey urgency?',
            'criteria' => ['true' => 'Explicitly time-sensitive', 'false' => 'No urgency expressed'],
        ], $question->jsonSerialize());
    }

    public function testItSerializesWithOnlyOneCriterion()
    {
        $question = new NoulQuestion('Does this convey urgency?', false: 'No urgency expressed');

        $this->assertSame(['false' => 'No urgency expressed'], $question->jsonSerialize()['criteria']);
    }

    public function testItSerializesStructuredInstructions()
    {
        $instructions = ['question' => 'Does this convey urgency?', 'examples' => ['ASAP', 'today']];

        $question = new NoulQuestion($instructions);

        $this->assertSame($instructions, $question->jsonSerialize()['instructions']);
    }
}
