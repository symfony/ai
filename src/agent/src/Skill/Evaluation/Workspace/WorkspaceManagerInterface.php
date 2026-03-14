<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation\Workspace;

use Symfony\AI\Agent\Skill\Evaluation\BenchmarkResult;
use Symfony\AI\Agent\Skill\Evaluation\GradingResultInterface;
use Symfony\AI\Agent\Skill\Evaluation\TimingResult;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkspaceManagerInterface
{
    public function initializeIteration(int $iteration): string;

    public function getEvalDirectory(int $iteration, string $evalName, string $configuration): string;

    public function saveTimingResult(string $evalDir, TimingResult $timingResult): void;

    public function saveGradingResult(string $evalDir, GradingResultInterface $gradingResult): void;

    public function saveBenchmarkResult(int $iteration, BenchmarkResult $benchmarkResult): void;

    public function saveOutput(string $evalDir, string $output): void;
}
