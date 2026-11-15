<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Decision\Question;

/**
 * A yes/no question, answered with the probability that the answer is yes.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class NoulQuestion implements QuestionInterface
{
    /**
     * @param string      $instructions The yes/no question to evaluate
     * @param string|null $true         What a yes (value near 1) means
     * @param string|null $false        What a no (value near 0) means
     */
    public function __construct(
        private readonly string $instructions,
        private readonly ?string $true = null,
        private readonly ?string $false = null,
    ) {
    }

    public function getInstructions(): string
    {
        return $this->instructions;
    }

    public function getTrue(): ?string
    {
        return $this->true;
    }

    public function getFalse(): ?string
    {
        return $this->false;
    }
}
