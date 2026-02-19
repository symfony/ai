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
use Symfony\AI\Evaluator\AbstractScorer;
use Symfony\AI\Evaluator\ScorerInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\Clock\MonotonicClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type ScorerData array{
 *     name: string,
 *     score: float,
 *     reason: ?string,
 *     scored_at: \DateTimeImmutable
 * }
 */
final class TraceableScorer implements ScorerInterface
{
    /**
     * @var array{
     *     name: string,
     *     score: float,
     *     reason: ?string,
     *     scored_at: \DateTimeImmutable
     * }
     */
    public array $data;

    public function __construct(
        private readonly ScorerInterface $scorer,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function score(DeferredResult $deferredResult, array $options = []): float
    {
        $score = $this->scorer->score($deferredResult, $options);

        $this->data = [
            'name' => $this->scorer::class,
            'score' => $score,
            'reason' => $this->scorer instanceof AbstractScorer ? $this->scorer->getReason() : null,
            'scored_at' => $this->clock->now(),
        ];

        return $score;
    }
}
