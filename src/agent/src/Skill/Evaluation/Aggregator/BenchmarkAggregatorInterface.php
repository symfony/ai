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
use Symfony\AI\Agent\Skill\Evaluation\EvalRunResult;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface BenchmarkAggregatorInterface
{
    /**
     * @param EvalRunResult[] $withSkillResults
     * @param EvalRunResult[] $withoutSkillResults
     */
    public function aggregate(array $withSkillResults, array $withoutSkillResults): BenchmarkResult;
}
