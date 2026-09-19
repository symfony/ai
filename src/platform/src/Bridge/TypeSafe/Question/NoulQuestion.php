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

/**
 * A yes/no question, answered with the probability that the answer is yes.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class NoulQuestion implements QuestionInterface
{
    /**
     * @param string|array<mixed> $instructions The yes/no question to evaluate
     * @param string|null         $true         What a yes (value near 1) means
     * @param string|null         $false        What a no (value near 0) means
     */
    public function __construct(
        private readonly string|array $instructions,
        private readonly ?string $true = null,
        private readonly ?string $false = null,
    ) {
    }

    /**
     * @return array{type: 'noul', instructions: string|array<mixed>, criteria?: array{true?: string, false?: string}}
     */
    public function jsonSerialize(): array
    {
        $question = [
            'type' => 'noul',
            'instructions' => $this->instructions,
        ];

        $criteria = [];
        if (null !== $this->true) {
            $criteria['true'] = $this->true;
        }

        if (null !== $this->false) {
            $criteria['false'] = $this->false;
        }

        if ([] !== $criteria) {
            $question['criteria'] = $criteria;
        }

        return $question;
    }
}
