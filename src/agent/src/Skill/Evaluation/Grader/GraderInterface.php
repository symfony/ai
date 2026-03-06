<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation\Grader;

use Symfony\AI\Agent\Skill\Evaluation\GradingResult;

/**
 * Grades agent output against expected assertions.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface GraderInterface
{
    /**
     * @param string[] $assertions
     */
    public function grade(string $output, array $assertions, string $expectedOutput): GradingResult;
}
