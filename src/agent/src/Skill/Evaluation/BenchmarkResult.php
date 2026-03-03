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
 * Aggregated benchmark results comparing with-skill vs without-skill runs.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BenchmarkResult
{
    public function __construct(
        private readonly BenchmarkStatistic $withSkillPassRate,
        private readonly BenchmarkStatistic $withoutSkillPassRate,
        private readonly BenchmarkStatistic $withSkillTime,
        private readonly BenchmarkStatistic $withoutSkillTime,
        private readonly BenchmarkStatistic $withSkillTokens,
        private readonly BenchmarkStatistic $withoutSkillTokens,
    ) {
    }

    public function getWithSkillPassRate(): BenchmarkStatistic
    {
        return $this->withSkillPassRate;
    }

    public function getWithoutSkillPassRate(): BenchmarkStatistic
    {
        return $this->withoutSkillPassRate;
    }

    public function getWithSkillTime(): BenchmarkStatistic
    {
        return $this->withSkillTime;
    }

    public function getWithoutSkillTime(): BenchmarkStatistic
    {
        return $this->withoutSkillTime;
    }

    public function getWithSkillTokens(): BenchmarkStatistic
    {
        return $this->withSkillTokens;
    }

    public function getWithoutSkillTokens(): BenchmarkStatistic
    {
        return $this->withoutSkillTokens;
    }

    /**
     * @return array{pass_rate: float, time_ms: float, tokens: float}
     */
    public function getDelta(): array
    {
        return [
            'pass_rate' => $this->withSkillPassRate->getMean() - $this->withoutSkillPassRate->getMean(),
            'time_ms' => $this->withSkillTime->getMean() - $this->withoutSkillTime->getMean(),
            'tokens' => $this->withSkillTokens->getMean() - $this->withoutSkillTokens->getMean(),
        ];
    }

    /**
     * @return array{with_skill: array{pass_rate: array{mean: float, stddev: float}, time: array{mean: float, stddev: float}, tokens: array{mean: float, stddev: float}}, without_skill: array{pass_rate: array{mean: float, stddev: float}, time: array{mean: float, stddev: float}, tokens: array{mean: float, stddev: float}}, delta: array{pass_rate: float, time_ms: float, tokens: float}}
     */
    public function toArray(): array
    {
        return [
            'with_skill' => [
                'pass_rate' => $this->withSkillPassRate->toArray(),
                'time' => $this->withSkillTime->toArray(),
                'tokens' => $this->withSkillTokens->toArray(),
            ],
            'without_skill' => [
                'pass_rate' => $this->withoutSkillPassRate->toArray(),
                'time' => $this->withoutSkillTime->toArray(),
                'tokens' => $this->withoutSkillTokens->toArray(),
            ],
            'delta' => $this->getDelta(),
        ];
    }
}
