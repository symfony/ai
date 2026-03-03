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
 * Aggregates assertion results into a grading summary.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GradingResult implements GradingResultInterface
{
    /**
     * @param AssertionResult[] $assertionResults
     */
    public function __construct(
        private readonly array $assertionResults,
    ) {
    }

    public function getAssertionResults(): array
    {
        return $this->assertionResults;
    }

    public function getSummary(): array
    {
        $total = \count($this->assertionResults);
        $passed = 0;

        foreach ($this->assertionResults as $result) {
            if ($result->isPassed()) {
                ++$passed;
            }
        }

        return [
            'passed' => $passed,
            'failed' => $total - $passed,
            'total' => $total,
            'pass_rate' => 0 === $total ? 0.0 : $passed / $total,
        ];
    }

    public function toArray(): array
    {
        return [
            'assertions' => array_map(
                static fn (AssertionResult $r): array => $r->toArray(),
                $this->assertionResults,
            ),
            'summary' => $this->getSummary(),
        ];
    }
}
