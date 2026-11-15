<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decision\Contract\InputNormalizer;
use Symfony\AI\Platform\Bridge\Decision\Contract\QuestionNormalizer;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Model;

final class QuestionNormalizerTest extends TestCase
{
    public function testItSupportsQuestionsForDecisionModelsOnly()
    {
        $normalizer = new QuestionNormalizer();
        $question = new NoulQuestion('Urgent?');

        $this->assertTrue($normalizer->supportsNormalization($question, context: [Contract::CONTEXT_MODEL => new Model('jev-latest', [Capability::DECISION])]));
        $this->assertFalse($normalizer->supportsNormalization($question, context: [Contract::CONTEXT_MODEL => new Model('llama3')]));
    }

    public function testTheContractNormalizesTheQuestions()
    {
        $contract = Contract::create([new InputNormalizer(), new QuestionNormalizer()]);

        $questions = $contract->createQuestionsOption([
            'is_urgent' => new NoulQuestion('Does this convey urgency?', 'Explicitly time-sensitive', 'No urgency expressed'),
            'is_bug' => new NoulQuestion('Is this a software defect?', 'Broken behavior'),
            'department' => new ChoiceQuestion('Which team should handle this?', ['billing' => 'Payments', 'technical' => null]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Very angry']),
            'line' => new ChoiceQuestion('Which line answers the query?', ['The first line', 'The second line']),
        ], new Model('jev-latest', [Capability::DECISION]));

        $this->assertJsonStringEqualsJsonString(<<<'JSON'
            {
                "is_urgent": {"type": "noul", "instructions": "Does this convey urgency?", "criteria": {"true": "Explicitly time-sensitive", "false": "No urgency expressed"}},
                "is_bug": {"type": "noul", "instructions": "Is this a software defect?", "criteria": {"true": "Broken behavior"}},
                "department": {"type": "choice", "instructions": "Which team should handle this?", "criteria": {"billing": "Payments", "technical": null}},
                "frustration": {"type": "score", "instructions": "How frustrated is the customer?", "criteria": ["Calm", "Very angry"]},
                "line": {"type": "choice", "instructions": "Which line answers the query?", "criteria": {"0": "The first line", "1": "The second line"}}
            }
            JSON, json_encode($questions, \JSON_THROW_ON_ERROR));
    }
}
