<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Decision\Question\ScoreQuestion;

final class QuestionNormalizerTest extends TestCase
{
    public function testItNormalizesTheQuestions()
    {
        $this->assertSame([
            'is_bug' => ['type' => 'predicate', 'instructions' => 'Is this a software defect?'],
            'department' => ['type' => 'choice', 'instructions' => 'Which team should handle this?', 'choices' => [
                ['value' => 'billing', 'description' => 'Payments'],
                ['value' => 'technical'],
            ]],
            'frustration' => ['type' => 'score', 'instructions' => 'How frustrated is the customer?', 'levels' => [
                ['label' => 'Calm'],
                ['label' => 'Very angry'],
            ]],
        ], $this->normalize([
            'is_bug' => new NoulQuestion('Is this a software defect?'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Very angry']),
        ]));
    }

    public function testItMovesNoulCriteriaIntoThePredicateInstructions()
    {
        $questions = $this->normalize(['is_urgent' => new NoulQuestion('Does this convey urgency?', 'Explicitly time-sensitive', 'No urgency expressed')]);

        $this->assertSame("Does this convey urgency?\nTrue means: Explicitly time-sensitive\nFalse means: No urgency expressed", $questions['is_urgent']['instructions']);
    }

    public function testItSendsNumericOptionsAsStrings()
    {
        $questions = $this->normalize([new ChoiceQuestion('Which line answers the query?', ['The first line', null])]);

        $this->assertSame([['value' => '0', 'description' => 'The first line'], ['value' => '1']], $questions[0]['choices']);
    }

    /**
     * @param array<array-key, QuestionInterface> $questions
     *
     * @return array<array-key, mixed>
     */
    private function normalize(array $questions): array
    {
        return OpenAiContract::create()->createQuestionsOption($questions, new Gpt('gpt-6-luna', [Capability::DECISION]));
    }
}
