<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision;

use Symfony\AI\Platform\Bridge\Decision\Answer\AnswerInterface;
use Symfony\AI\Platform\Bridge\Decision\Answer\Answers;
use Symfony\AI\Platform\Bridge\Decision\Answer\ChoiceAnswer;
use Symfony\AI\Platform\Bridge\Decision\Answer\NoulAnswer;
use Symfony\AI\Platform\Bridge\Decision\Answer\ScoreAnswer;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Builds the {@see Answers} of a decision model from the decoded body of its response,
 * which providers (TypeSafe's Jev, Ollama's decision models) share.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class AnswersConverter
{
    /**
     * @param array<string, mixed> $data The decoded response body
     */
    public function convert(array $data): Answers
    {
        if (!isset($data['answers']) || !\is_array($data['answers'])) {
            throw new RuntimeException('Response does not contain answers.');
        }

        $answers = [];
        foreach ($data['answers'] as $id => $answer) {
            if (!\is_array($answer)) {
                throw new RuntimeException(\sprintf('Answer "%s" is expected to be an array, "%s" given.', $id, get_debug_type($answer)));
            }

            $answers[$id] = $this->convertAnswer((string) $id, $answer);
        }

        return new Answers($answers);
    }

    /**
     * @param array<string, mixed> $answer
     */
    private function convertAnswer(string $id, array $answer): AnswerInterface
    {
        $type = $answer['type'] ?? null;

        [$requiredKeys, $arrayKeys] = match ($type) {
            'noul' => [['noul'], []],
            'choice' => [['choice', 'probabilities', 'confidence'], ['probabilities']],
            'score' => [['score', 'legend', 'probabilities', 'confidence'], ['legend', 'probabilities']],
            default => [[], []],
        };

        foreach ($requiredKeys as $key) {
            if (!isset($answer[$key])) {
                throw new RuntimeException(\sprintf('Answer "%s" of type "%s" is missing the "%s" key.', $id, $type, $key));
            }
        }

        foreach ($arrayKeys as $key) {
            if (isset($answer[$key]) && !\is_array($answer[$key])) {
                throw new RuntimeException(\sprintf('Answer "%s" of type "%s" expects the "%s" key to be an array, "%s" given.', $id, $type, $key, get_debug_type($answer[$key])));
            }
        }

        if ('choice' === $type && !\is_string($answer['choice'])) {
            throw new RuntimeException(\sprintf('Answer "%s" of type "choice" expects the "choice" key to be a string, "%s" given.', $id, get_debug_type($answer['choice'])));
        }

        return match ($type) {
            'noul' => NoulAnswer::fromArray($answer),
            'choice' => ChoiceAnswer::fromArray($answer),
            'score' => ScoreAnswer::fromArray($answer),
            default => throw new RuntimeException(\sprintf('Answer "%s" has an unsupported type "%s".', $id, \is_string($type) ? $type : get_debug_type($type))),
        };
    }
}
