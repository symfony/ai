<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\AnswersConverter;
use Symfony\AI\Platform\Exception\RuntimeException;

final class AnswersConverterTest extends TestCase
{
    public function testItConvertsAllTypesOfAnswers()
    {
        $answers = (new AnswersConverter())->convert([
            'answers' => [
                'is_urgent' => ['type' => 'noul', 'noul' => 0.92],
                'department' => ['type' => 'choice', 'choice' => 'technical', 'probabilities' => ['billing' => 0.08, 'technical' => 0.92], 'confidence' => 0.8],
                'frustration' => ['type' => 'score', 'score' => 1.6, 'legend' => ['0' => 'Calm', '1' => 'Angry'], 'probabilities' => ['0' => 0.4, '1' => 0.6], 'confidence' => 0.2],
            ],
        ]);

        $this->assertSame(0.92, $answers->getNoul('is_urgent')->getProbability());
        $this->assertSame('technical', $answers->getChoice('department')->getChoice());
        $this->assertSame(1.6, $answers->getScore('frustration')->getScore());
    }

    public function testItRequiresAnswers()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain answers.');

        (new AnswersConverter())->convert(['model' => 'foo']);
    }

    public function testItRejectsAnAnswerOfAnUnsupportedType()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "is_urgent" has an unsupported type "boolean".');

        (new AnswersConverter())->convert(['answers' => ['is_urgent' => ['type' => 'boolean']]]);
    }

    public function testItRejectsAnIncompleteAnswer()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Answer "department" of type "choice" is missing the "probabilities" key.');

        (new AnswersConverter())->convert(['answers' => ['department' => ['type' => 'choice', 'choice' => 'a', 'confidence' => 1.0]]]);
    }
}
