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
final class ScoreAnswer implements AnswerInterface
{
    /**
     * @param float              $score         The probability-weighted score across the levels, which can land between two of them
     * @param array<int, string> $legend        Every level index mapped back to its description
     * @param array<int, float>  $probabilities Every level index mapped to its probability
     * @param float              $confidence    How certain the model is, from 0 to 1
     */
    public function __construct(
        private readonly float $score,
        private readonly array $legend,
        private readonly array $probabilities,
        private readonly float $confidence,
    ) {
    }

    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @return array<int, string>
     */
    public function getLegend(): array
    {
        return $this->legend;
    }

    /**
     * @return array<int, float>
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
     * @param array{type: 'score', score: int|float, legend: array<int, string>, probabilities?: array<int, int|float>, confidence: int|float} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) $data['score'],
            $data['legend'],
            array_map(floatval(...), $data['probabilities'] ?? []),
            (float) $data['confidence'],
        );
    }
}
