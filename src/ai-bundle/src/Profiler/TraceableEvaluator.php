<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Psr\Clock\ClockInterface;
use Symfony\AI\Evaluator\EvaluatorInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\Clock\MonotonicClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type EvaluatorData array{
 *     score: float,
 *     evaluated_at: \DateTimeImmutable,
 * }
 */
final class TraceableEvaluator implements EvaluatorInterface
{
    public array $data = [];

    public function __construct(
        private readonly EvaluatorInterface $evaluator,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function evaluate(DeferredResult $deferredResult, array $options = []): float
    {
        $score = $this->evaluator->evaluate($deferredResult, $options);

        $this->data = [
            'score' => $score,
            'evaluated_at' => $this->clock->now(),
        ];

        return $score;
    }
}
