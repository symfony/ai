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
 * Picks one option out of a defined set, answered with the chosen option and the probability of each.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class ChoiceQuestion implements QuestionInterface
{
    /**
     * @param string|array<mixed>           $instructions What the model should decide
     * @param array<array-key, string|null> $options      Every option mapped to its description, or to null when the option needs no extra detail
     */
    public function __construct(
        private readonly string|array $instructions,
        private readonly array $options,
    ) {
        if ([] === $options) {
            throw new InvalidArgumentException('A choice question requires at least one option.');
        }
    }

    /**
     * @return array{type: 'choice', instructions: string|array<mixed>, criteria: array<array-key, string|null>}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'choice',
            'instructions' => $this->instructions,
            'criteria' => $this->options,
        ];
    }
}
