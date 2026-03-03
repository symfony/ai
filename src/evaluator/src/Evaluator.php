<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Evaluator;

use Symfony\AI\Platform\Result\DeferredResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Evaluator implements EvaluatorInterface
{
    /**
     * @var ScorerInterface[]
     */
    public function __construct(private readonly iterable $scorers)
    {
    }

    public function evaluate(DeferredResult $deferredResult, array $options = []): float
    {
        $score = 0.0;

        if ([] === $this->scorers) {
            return $score;
        }

        foreach ($this->scorers as $scorer) {
            $score += $scorer->score($deferredResult, $options);
        }

        if (0.0 === $score) {
            return $score;
        }

        return $score / \count($this->scorers);
    }
}
