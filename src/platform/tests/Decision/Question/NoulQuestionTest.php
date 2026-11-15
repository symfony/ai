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
use Symfony\AI\Platform\Decision\Question\NoulQuestion;

final class NoulQuestionTest extends TestCase
{
    public function testItHasNoCriteriaByDefault()
    {
        $question = new NoulQuestion('Does this convey urgency?');

        $this->assertSame('Does this convey urgency?', $question->getInstructions());
        $this->assertNull($question->getTrue());
        $this->assertNull($question->getFalse());
    }

    public function testItExposesItsCriteria()
    {
        $question = new NoulQuestion('Does this convey urgency?', 'Explicitly time-sensitive', 'No urgency expressed');

        $this->assertSame('Explicitly time-sensitive', $question->getTrue());
        $this->assertSame('No urgency expressed', $question->getFalse());
    }
}
