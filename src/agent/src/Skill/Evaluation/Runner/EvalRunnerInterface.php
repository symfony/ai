<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation\Runner;

use Symfony\AI\Agent\Skill\Evaluation\EvalCase;
use Symfony\AI\Agent\Skill\Evaluation\EvalRunResult;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface EvalRunnerInterface
{
    public function run(EvalCase $evalCase): EvalRunResult;
}
