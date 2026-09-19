<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Question;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Rates the state along ordered levels, answered with a probability-weighted score across them.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class ScoreQuestion implements QuestionInterface
{
    /**
     * @param string|array<mixed> $instructions What the model should rate
     * @param list<string>        $levels       The description of every level, ordered from the lowest to the highest
     */
    public function __construct(
        private readonly string|array $instructions,
        private readonly array $levels,
    ) {
        if (\count($levels) < 2) {
            throw new InvalidArgumentException('A score question requires at least two levels.');
        }
    }

    /**
     * @return array{type: 'score', instructions: string|array<mixed>, criteria: list<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'score',
            'instructions' => $this->instructions,
            'criteria' => array_values($this->levels),
        ];
    }
}
