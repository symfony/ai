<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation;

/**
 * Represents a mean/standard deviation pair for a benchmark metric.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BenchmarkStatistic
{
    public function __construct(
        private readonly float $mean,
        private readonly float $stddev,
    ) {
    }

    public function getMean(): float
    {
        return $this->mean;
    }

    public function getStddev(): float
    {
        return $this->stddev;
    }

    /**
     * @return array{mean: float, stddev: float}
     */
    public function toArray(): array
    {
        return [
            'mean' => $this->mean,
            'stddev' => $this->stddev,
        ];
    }
}
