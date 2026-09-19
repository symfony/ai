<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Answer;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * The answers of an evaluation, keyed by the identifier of the question they belong to.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class Answers
{
    /**
     * @param array<string, AnswerInterface> $answers
     */
    public function __construct(
        private readonly array $answers,
    ) {
    }

    /**
     * @return array<string, AnswerInterface>
     */
    public function all(): array
    {
        return $this->answers;
    }

    public function has(string $id): bool
    {
        return isset($this->answers[$id]);
    }

    public function get(string $id): AnswerInterface
    {
        if (!isset($this->answers[$id])) {
            throw new InvalidArgumentException(\sprintf('There is no answer for question "%s".', $id));
        }

        return $this->answers[$id];
    }

    public function getNoul(string $id): NoulAnswer
    {
        return $this->getTyped($id, NoulAnswer::class);
    }

    public function getChoice(string $id): ChoiceAnswer
    {
        return $this->getTyped($id, ChoiceAnswer::class);
    }

    public function getScore(string $id): ScoreAnswer
    {
        return $this->getTyped($id, ScoreAnswer::class);
    }

    /**
     * @template T of AnswerInterface
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function getTyped(string $id, string $class): AnswerInterface
    {
        $answer = $this->get($id);

        if (!$answer instanceof $class) {
            throw new InvalidArgumentException(\sprintf('The answer for question "%s" is of type "%s", "%s" expected.', $id, $answer::class, $class));
        }

        return $answer;
    }
}
