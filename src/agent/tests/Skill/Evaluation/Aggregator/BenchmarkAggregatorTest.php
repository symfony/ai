<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill\Evaluation\Aggregator;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Skill\Evaluation\Aggregator\BenchmarkAggregator;
use Symfony\AI\Agent\Skill\Evaluation\AssertionResult;
use Symfony\AI\Agent\Skill\Evaluation\EvalCase;
use Symfony\AI\Agent\Skill\Evaluation\EvalRunResult;
use Symfony\AI\Agent\Skill\Evaluation\GradingResult;
use Symfony\AI\Agent\Skill\Evaluation\TimingResult;

final class BenchmarkAggregatorTest extends TestCase
{
    public function testAggregateWithKnownValues()
    {
        $aggregator = new BenchmarkAggregator();

        $withSkill = [
            $this->createResult(200, 1000, 1.0),
            $this->createResult(300, 2000, 0.5),
        ];

        $withoutSkill = [
            $this->createResult(100, 800, 0.5),
            $this->createResult(150, 1200, 0.5),
        ];

        $result = $aggregator->aggregate($withSkill, $withoutSkill);

        // With skill: pass rates [1.0, 0.5] -> mean 0.75
        $this->assertEqualsWithDelta(0.75, $result->getWithSkillPassRate()->getMean(), 0.001);

        // Without skill: pass rates [0.5, 0.5] -> mean 0.5
        $this->assertEqualsWithDelta(0.5, $result->getWithoutSkillPassRate()->getMean(), 0.001);

        // With skill: durations [1000, 2000] -> mean 1500
        $this->assertEqualsWithDelta(1500.0, $result->getWithSkillTime()->getMean(), 0.001);

        // With skill: tokens [200, 300] -> mean 250
        $this->assertEqualsWithDelta(250.0, $result->getWithSkillTokens()->getMean(), 0.001);
    }

    public function testDeltaComputation()
    {
        $aggregator = new BenchmarkAggregator();

        $withSkill = [$this->createResult(200, 1000, 1.0)];
        $withoutSkill = [$this->createResult(100, 500, 0.5)];

        $result = $aggregator->aggregate($withSkill, $withoutSkill);
        $delta = $result->getDelta();

        $this->assertEqualsWithDelta(0.5, $delta['pass_rate'], 0.001);
        $this->assertEqualsWithDelta(500.0, $delta['time_ms'], 0.001);
        $this->assertEqualsWithDelta(100.0, $delta['tokens'], 0.001);
    }

    public function testAggregateWithEmptyResults()
    {
        $aggregator = new BenchmarkAggregator();

        $result = $aggregator->aggregate([], []);

        $this->assertEqualsWithDelta(0.0, $result->getWithSkillPassRate()->getMean(), 0.001);
        $this->assertEqualsWithDelta(0.0, $result->getWithoutSkillPassRate()->getMean(), 0.001);
    }

    public function testStddevComputation()
    {
        $aggregator = new BenchmarkAggregator();

        // Tokens [100, 300] -> mean 200, variance ((100-200)^2 + (300-200)^2)/2 = 10000, stddev = 100
        $results = [
            $this->createResult(100, 500, 1.0),
            $this->createResult(300, 500, 1.0),
        ];

        $result = $aggregator->aggregate($results, $results);

        $this->assertEqualsWithDelta(100.0, $result->getWithSkillTokens()->getStddev(), 0.001);
    }

    private function createResult(int $tokens, int $durationMs, float $passRate): EvalRunResult
    {
        $evalCase = new EvalCase(1, 'prompt', 'expected');
        $timing = new TimingResult($tokens, $durationMs);

        $assertionResults = [];
        if ($passRate >= 1.0) {
            $assertionResults[] = new AssertionResult('test', true, 'passed');
        } elseif ($passRate > 0.0) {
            $assertionResults[] = new AssertionResult('test1', true, 'passed');
            $assertionResults[] = new AssertionResult('test2', false, 'failed');
        } else {
            $assertionResults[] = new AssertionResult('test', false, 'failed');
        }

        $grading = new GradingResult($assertionResults);

        return new EvalRunResult($evalCase, 'output', $timing, $grading);
    }
}
