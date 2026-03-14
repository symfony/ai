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
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface GradingResultInterface
{
    /**
     * @return AssertionResult[]
     */
    public function getAssertionResults(): array;

    /**
     * @return array{passed: int, failed: int, total: int, pass_rate: float}
     */
    public function getSummary(): array;

    /**
     * @return array{assertions: list<array{text: string, passed: bool, evidence: string}>, summary: array{passed: int, failed: int, total: int, pass_rate: float}}
     */
    public function toArray(): array;
}
