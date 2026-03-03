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
use Symfony\AI\Agent\Skill\Evaluation\GradingResult;
use Symfony\AI\Agent\Skill\Evaluation\TimingResult;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages workspace directories and files for evaluation runs.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkspaceManager implements WorkspaceManagerInterface
{
    public function __construct(
        private readonly string $workspacePath,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function initializeIteration(int $iteration): string
    {
        $dir = $this->workspacePath.'/iteration-'.$iteration;
        $this->filesystem->mkdir($dir);

        return $dir;
    }

    public function getEvalDirectory(int $iteration, string $evalName, string $configuration): string
    {
        $dir = $this->workspacePath.'/iteration-'.$iteration.'/eval-'.$evalName.'/'.$configuration.'/outputs';
        $this->filesystem->mkdir($dir);

        return \dirname($dir);
    }

    public function saveTimingResult(string $evalDir, TimingResult $timingResult): void
    {
        $this->filesystem->dumpFile(
            $evalDir.'/timing.json',
            json_encode($timingResult->toArray(), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR),
        );
    }

    public function saveGradingResult(string $evalDir, GradingResult $gradingResult): void
    {
        $this->filesystem->dumpFile(
            $evalDir.'/grading.json',
            json_encode($gradingResult->toArray(), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR),
        );
    }

    public function saveBenchmarkResult(int $iteration, BenchmarkResult $benchmarkResult): void
    {
        $dir = $this->workspacePath.'/iteration-'.$iteration;
        $this->filesystem->mkdir($dir);

        $this->filesystem->dumpFile(
            $dir.'/benchmark.json',
            json_encode($benchmarkResult->toArray(), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR),
        );
    }

    public function saveOutput(string $evalDir, string $output): void
    {
        $this->filesystem->dumpFile($evalDir.'/outputs/output.txt', $output);
    }
}
