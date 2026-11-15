<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Decision;

use Symfony\AI\Platform\Decision\Answer\AnswerInterface;
use Symfony\AI\Platform\Decision\Answer\Answers;
use Symfony\AI\Platform\Decision\Answer\ChoiceAnswer;
use Symfony\AI\Platform\Decision\Answer\NoulAnswer;
use Symfony\AI\Platform\Decision\Answer\ScoreAnswer;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * Converts the response of the OpenAI Decisions API into {@see Answers}, keyed by the name of their question.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConverter
{
    use HttpStatusErrorHandlingTrait;

    public function convert(RawResultInterface|RawHttpResult $result): ObjectResult
    {
        $httpResponse = $result->getObject();
        $this->throwOnHttpError($httpResponse);

        if (200 !== $httpResponse->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $httpResponse->getStatusCode(), $this->extractErrorMessage($httpResponse) ?? $httpResponse->getContent(false)));
        }

        $data = $result->getData();
        if (!isset($data['answers']) || !\is_array($data['answers'])) {
            throw new RuntimeException('Response does not contain answers.');
        }

        $answers = [];
        foreach ($data['answers'] as $answer) {
            if (!\is_array($answer) || !\is_string($answer['name'] ?? null)) {
                throw new RuntimeException('Every answer is expected to be an array with a "name".');
            }

            $answers[$answer['name']] = $this->convertAnswer($answer['name'], $answer);
        }

        return new ObjectResult(new Answers($answers));
    }

    /**
     * @param array<string, mixed> $answer
     */
    private function convertAnswer(string $name, array $answer): AnswerInterface
    {
        $type = $answer['type'] ?? null;

        if ('refusal' === $type) {
            throw new RuntimeException(\sprintf('The model refused to answer the question "%s".', $name));
        }

        $requiredKeys = match ($type) {
            'predicate' => ['probability'],
            'choice' => ['choice', 'probabilities', 'confidence'],
            'score' => ['score', 'probabilities', 'confidence'],
            default => throw new RuntimeException(\sprintf('Answer "%s" has an unsupported type "%s".', $name, \is_string($type) ? $type : get_debug_type($type))),
        };

        foreach ($requiredKeys as $key) {
            if (!isset($answer[$key])) {
                throw new RuntimeException(\sprintf('Answer "%s" of type "%s" is missing the "%s" key.', $name, $type, $key));
            }
        }

        if ('predicate' === $type) {
            return new NoulAnswer((float) $answer['probability']);
        }

        if (!\is_array($answer['probabilities'])) {
            throw new RuntimeException(\sprintf('Answer "%s" of type "%s" expects the "probabilities" key to be a list.', $name, $type));
        }

        $probabilities = [];
        $legend = [];
        foreach ($answer['probabilities'] as $probability) {
            if (!\is_array($probability) || !isset($probability['value'], $probability['probability'])) {
                throw new RuntimeException(\sprintf('Answer "%s" of type "%s" has a probability without "value" or "probability".', $name, $type));
            }

            if ('choice' === $type ? !\is_string($probability['value']) : !\is_int($probability['value'])) {
                throw new RuntimeException(\sprintf('Answer "%s" of type "%s" has a probability with an invalid value.', $name, $type));
            }

            $probabilities[$probability['value']] = (float) $probability['probability'];
            $legend[$probability['value']] = (string) ($probability['label'] ?? '');
        }

        if ('choice' === $type) {
            return new ChoiceAnswer((string) $answer['choice'], $probabilities, (float) $answer['confidence']);
        }

        return new ScoreAnswer((float) $answer['score'], $legend, $probabilities, (float) $answer['confidence']);
    }
}
