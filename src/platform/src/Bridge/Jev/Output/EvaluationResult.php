<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev\Output;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Typed System One evaluation: one answer per question id.
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class EvaluationResult
{
    /**
     * @param array<string, NoulAnswer|ChoiceAnswer|ScoreAnswer> $answers
     */
    public function __construct(
        private readonly string $model,
        private readonly array $answers,
    ) {
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return array<string, NoulAnswer|ChoiceAnswer|ScoreAnswer>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    public function getAnswer(string $id): NoulAnswer|ChoiceAnswer|ScoreAnswer|null
    {
        return $this->answers[$id] ?? null;
    }

    public function getChoice(string $id): ?string
    {
        $answer = $this->answers[$id] ?? null;

        return $answer instanceof ChoiceAnswer ? $answer->getChoice() : null;
    }

    public function getNoul(string $id): ?float
    {
        $answer = $this->answers[$id] ?? null;

        return $answer instanceof NoulAnswer ? $answer->getNoul() : null;
    }

    public function getScore(string $id): ?float
    {
        $answer = $this->answers[$id] ?? null;

        return $answer instanceof ScoreAnswer ? $answer->getScore() : null;
    }

    public function getConfidence(string $id): ?float
    {
        $answer = $this->answers[$id] ?? null;

        return match (true) {
            $answer instanceof ChoiceAnswer, $answer instanceof ScoreAnswer => $answer->getConfidence(),
            default => null,
        };
    }

    /**
     * @return array<string, float>
     */
    public function getProbabilities(string $id): array
    {
        $answer = $this->answers[$id] ?? null;

        return match (true) {
            $answer instanceof ChoiceAnswer, $answer instanceof ScoreAnswer => $answer->getProbabilities(),
            default => [],
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $answers = [];
        foreach ($this->answers as $id => $answer) {
            $answers[$id] = $answer->toArray();
        }

        return $answers;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['answers']) || !\is_array($data['answers'])) {
            throw new RuntimeException('Response does not contain answers.');
        }

        $answers = [];
        foreach ($data['answers'] as $id => $answer) {
            if (!\is_array($answer)) {
                throw new RuntimeException(\sprintf('Answer "%s" is not an object.', $id));
            }

            $answers[(string) $id] = self::createAnswer($answer, (string) $id);
        }

        return new self((string) ($data['model'] ?? ''), $answers);
    }

    /**
     * @param array<string, mixed> $answer
     */
    private static function createAnswer(array $answer, string $id): NoulAnswer|ChoiceAnswer|ScoreAnswer
    {
        return match ($answer['type'] ?? null) {
            'noul' => NoulAnswer::fromArray($answer),
            'choice' => ChoiceAnswer::fromArray($answer),
            'score' => ScoreAnswer::fromArray($answer),
            default => throw new RuntimeException(\sprintf('Answer "%s" has an unsupported type "%s".', $id, $answer['type'] ?? 'missing')),
        };
    }
}
