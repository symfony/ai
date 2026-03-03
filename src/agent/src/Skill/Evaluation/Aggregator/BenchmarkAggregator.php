<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation\Aggregator;

use Symfony\AI\Agent\Skill\Evaluation\BenchmarkResult;
use Symfony\AI\Agent\Skill\Evaluation\BenchmarkStatistic;
use Symfony\AI\Agent\Skill\Evaluation\EvalRunResult;

/**
 * Computes aggregate statistics from evaluation run results.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BenchmarkAggregator implements BenchmarkAggregatorInterface
{
    public function aggregate(array $withSkillResults, array $withoutSkillResults): BenchmarkResult
    {
        return new BenchmarkResult(
            $this->computeStatistic($this->extractPassRates($withSkillResults)),
            $this->computeStatistic($this->extractPassRates($withoutSkillResults)),
            $this->computeStatistic($this->extractDurations($withSkillResults)),
            $this->computeStatistic($this->extractDurations($withoutSkillResults)),
            $this->computeStatistic($this->extractTokens($withSkillResults)),
            $this->computeStatistic($this->extractTokens($withoutSkillResults)),
        );
    }

    /**
     * @param float[] $values
     */
    private function computeStatistic(array $values): BenchmarkStatistic
    {
        if ([] === $values) {
            return new BenchmarkStatistic(0.0, 0.0);
        }

        $count = \count($values);
        $mean = array_sum($values) / $count;

        if (1 === $count) {
            return new BenchmarkStatistic($mean, 0.0);
        }

        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $variance /= $count;

        return new BenchmarkStatistic($mean, sqrt($variance));
    }

    /**
     * @param EvalRunResult[] $results
     *
     * @return float[]
     */
    private function extractPassRates(array $results): array
    {
        $rates = [];

        foreach ($results as $result) {
            $grading = $result->getGrading();
            if (null !== $grading) {
                $rates[] = $grading->getSummary()['pass_rate'];
            }
        }

        return $rates;
    }

    /**
     * @param EvalRunResult[] $results
     *
     * @return float[]
     */
    private function extractDurations(array $results): array
    {
        return array_map(
            static fn (EvalRunResult $r): float => (float) $r->getTiming()->getDurationMs(),
            $results,
        );
    }

    /**
     * @param EvalRunResult[] $results
     *
     * @return float[]
     */
    private function extractTokens(array $results): array
    {
        return array_map(
            static fn (EvalRunResult $r): float => (float) $r->getTiming()->getTotalTokens(),
            $results,
        );
    }
}
