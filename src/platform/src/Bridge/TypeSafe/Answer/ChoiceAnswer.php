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

/**
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class ChoiceAnswer implements AnswerInterface
{
    /**
     * @param string                  $choice        The option with the highest probability
     * @param array<array-key, float> $probabilities Every option mapped to its probability
     * @param float                   $confidence    How certain the model is, from 0 to 1
     */
    public function __construct(
        private readonly string $choice,
        private readonly array $probabilities,
        private readonly float $confidence,
    ) {
    }

    public function getChoice(): string
    {
        return $this->choice;
    }

    /**
     * @return array<array-key, float>
     */
    public function getProbabilities(): array
    {
        return $this->probabilities;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    /**
     * @param array{type: 'choice', choice: string, probabilities: array<array-key, int|float>, confidence: int|float} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['choice'],
            array_map(floatval(...), $data['probabilities']),
            (float) $data['confidence'],
        );
    }
}
