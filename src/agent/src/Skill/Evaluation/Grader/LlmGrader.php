<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation\Grader;

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Skill\Evaluation\AssertionResult;
use Symfony\AI\Agent\Skill\Evaluation\GradingResult;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Grades eval assertions using an LLM for evidence-based evaluation.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LlmGrader implements GraderInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
    ) {
    }

    public function grade(string $output, array $assertions, string $expectedOutput): GradingResult
    {
        $assertionResults = [];

        foreach ($assertions as $assertion) {
            $assertionResults[] = $this->gradeAssertion($output, $assertion, $expectedOutput);
        }

        return new GradingResult($assertionResults);
    }

    private function gradeAssertion(string $output, string $assertion, string $expectedOutput): AssertionResult
    {
        $systemPrompt = <<<'PROMPT'
You are a strict evaluation grader. Your task is to determine whether an agent's output satisfies a given assertion.

Rules:
- Evaluate ONLY the specific assertion provided.
- Base your judgment strictly on evidence found in the output.
- If the output partially satisfies the assertion, it should be marked as failed.
- Respond with valid JSON only, no other text.

Response format:
{"passed": true/false, "evidence": "Brief explanation of why the assertion passed or failed"}
PROMPT;

        $userPrompt = \sprintf(
            "Expected output:\n%s\n\nActual output:\n%s\n\nAssertion to evaluate:\n%s",
            $expectedOutput,
            $output,
            $assertion,
        );

        $messages = new MessageBag(
            Message::forSystem($systemPrompt),
            Message::ofUser($userPrompt),
        );

        $responseText = $this->platform->invoke($this->model, $messages)->asText();

        $data = json_decode($responseText, true);

        if (!\is_array($data) || !isset($data['passed'], $data['evidence'])) {
            throw new RuntimeException(\sprintf('Malformed grading response from LLM: "%s".', $responseText));
        }

        return new AssertionResult($assertion, (bool) $data['passed'], (string) $data['evidence']);
    }
}
