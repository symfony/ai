<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Bridge\Knowledge\Exception\SyncFailedException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Clones (or pulls) a Git repository into a target directory.
 *
 * Uses a shallow clone by default to keep cache size and clone time small.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class GitFetcher
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
        private int $timeoutSeconds = 300,
    ) {
    }

    /**
     * Ensures $targetDir contains an up-to-date checkout of $repoUrl on $branch.
     *
     * - When the directory does not exist, performs a shallow `git clone`.
     * - When it exists and is a git checkout, runs `git fetch` + hard reset to origin.
     */
    public function fetch(string $repoUrl, string $branch, string $targetDir): void
    {
        if (!is_dir($targetDir.'/.git')) {
            $this->clone($repoUrl, $branch, $targetDir);

            return;
        }

        $this->pull($branch, $targetDir);
    }

    private function clone(string $repoUrl, string $branch, string $targetDir): void
    {
        $parent = \dirname($targetDir);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new SyncFailedException(\sprintf('Could not create cache directory "%s".', $parent));
        }

        $this->logger->info('Cloning knowledge repository', [
            'repo' => $repoUrl,
            'branch' => $branch,
            'target' => $targetDir,
        ]);

        $this->run([
            'git', 'clone',
            '--depth', '1',
            '--branch', $branch,
            '--single-branch',
            $repoUrl,
            $targetDir,
        ], \dirname($targetDir));
    }

    private function pull(string $branch, string $targetDir): void
    {
        $this->logger->info('Updating knowledge repository', [
            'branch' => $branch,
            'target' => $targetDir,
        ]);

        $this->run(['git', 'fetch', '--depth', '1', 'origin', $branch], $targetDir);
        $this->run(['git', 'reset', '--hard', 'origin/'.$branch], $targetDir);
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command, string $cwd): void
    {
        $process = new Process($command, $cwd, null, null, $this->timeoutSeconds);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new SyncFailedException(\sprintf('Git command failed: %s', $e->getMessage()), 0, $e);
        }
    }
}
