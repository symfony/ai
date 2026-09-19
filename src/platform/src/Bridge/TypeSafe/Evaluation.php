<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe;

use Symfony\AI\Platform\Bridge\TypeSafe\Question\QuestionInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * The input of a Jev invocation: a state, and the questions to answer about it.
 *
 * Jev ingests the state once and answers every question against it in parallel,
 * which makes batching questions into a single evaluation both faster and cheaper.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class Evaluation implements \JsonSerializable
{
    /**
     * @param string|array<mixed>              $state     The content to evaluate, as plain text or structured data
     * @param array<string, QuestionInterface> $questions The questions, keyed by the identifier their answer is returned under
     */
    public function __construct(
        private readonly string|array $state,
        private readonly array $questions,
    ) {
        if ([] === $questions) {
            throw new InvalidArgumentException('An evaluation requires at least one question.');
        }

        foreach ($questions as $id => $question) {
            if (!$question instanceof QuestionInterface) {
                throw new InvalidArgumentException(\sprintf('Question "%s" must be an instance of "%s", "%s" given.', $id, QuestionInterface::class, get_debug_type($question)));
            }
        }
    }

    /**
     * @return array{state: string|array<mixed>, questions: array<string, array<string, mixed>>}
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state,
            'questions' => array_map(
                static fn (QuestionInterface $question): array => $question->jsonSerialize(),
                $this->questions,
            ),
        ];
    }
}
