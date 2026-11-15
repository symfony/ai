<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Decision;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Contract\GeminiContract;
use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Bridge\Gemini\Decision\QuestionNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Decision\Question\ScoreQuestion;

final class QuestionNormalizerTest extends TestCase
{
    public function testItSupportsQuestionsForDecisionModelsOnly()
    {
        $normalizer = new QuestionNormalizer();
        $question = new NoulQuestion('Urgent?');

        $this->assertTrue($normalizer->supportsNormalization($question, context: [Contract::CONTEXT_MODEL => new Decision('gemini-decision-1')]));
        $this->assertFalse($normalizer->supportsNormalization($question, context: [Contract::CONTEXT_MODEL => new Gemini('gemini-2.5-flash')]));
    }

    public function testTheContractNormalizesTheQuestions()
    {
        $questions = GeminiContract::create()->createQuestionsOption([
            'is_urgent' => new NoulQuestion('Does this convey urgency?', 'Explicitly time-sensitive', 'No urgency expressed'),
            'is_bug' => new NoulQuestion('Is this a software defect?'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Very angry']),
        ], new Decision('gemini-decision-1', [Capability::DECISION]));

        $this->assertJsonStringEqualsJsonString(<<<'JSON'
            {
                "is_urgent": {"type": "noul", "instructions": "Does this convey urgency?", "criteria": {"true_description": "Explicitly time-sensitive", "false_description": "No urgency expressed"}},
                "is_bug": {"type": "noul", "instructions": "Is this a software defect?"},
                "department": {"type": "choice", "instructions": "Which team should handle this?", "criteria": {"billing": "Payments", "technical": ""}},
                "frustration": {"type": "score", "instructions": "How frustrated is the customer?", "criteria": ["Calm", "Very angry"]}
            }
            JSON, json_encode($questions, \JSON_THROW_ON_ERROR));
    }
}
