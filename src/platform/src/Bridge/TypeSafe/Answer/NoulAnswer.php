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
final class NoulAnswer implements AnswerInterface
{
    /**
     * @param float $probability The probability that the answer is yes, from 0 (no) to 1 (yes)
     */
    public function __construct(
        private readonly float $probability,
    ) {
    }

    public function getProbability(): float
    {
        return $this->probability;
    }

    /**
     * @param float $threshold The probability from which the answer counts as a yes
     */
    public function isTrue(float $threshold = 0.5): bool
    {
        return $this->probability >= $threshold;
    }

    /**
     * @param array{type: 'noul', noul: int|float} $data
     */
    public static function fromArray(array $data): self
    {
        return new self((float) $data['noul']);
    }
}
