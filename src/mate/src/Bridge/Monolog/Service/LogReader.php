<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Monolog\Service;

use Symfony\AI\Mate\Bridge\Monolog\Exception\LogFileNotFoundException;
use Symfony\AI\Mate\Bridge\Monolog\Model\LogEntry;
use Symfony\AI\Mate\Bridge\Monolog\Model\SearchCriteria;

/**
 * Reads and parses log files from a directory.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LogReader
{
    /**
     * @var array<string|int, string>
     */
    private readonly array $logDirs;

    /**
     * @param string|array<string, string> $logDir A single log directory, or a map of context name to log
     *                                             directory for multi-kernel (APP_ID) applications
     */
    public function __construct(
        private LogParser $parser,
        string|array $logDir,
    ) {
        $this->logDirs = \is_string($logDir) ? [0 => $logDir] : $logDir;
    }

    /**
     * @return string[]
     */
    public function getLogFiles(?string $kernelContext = null): array
    {
        return $this->collectLogFiles($this->resolveLogDirs($kernelContext));
    }

    /**
     * @return string[]
     */
    public function getLogFilesForEnvironment(string $environment, ?string $kernelContext = null): array
    {
        return $this->filterForEnvironment($this->getLogFiles($kernelContext), $environment);
    }

    /**
     * @return \Generator<LogEntry>
     */
    public function readAll(?SearchCriteria $criteria = null, ?string $kernelContext = null): \Generator
    {
        $files = $this->getLogFiles($kernelContext);

        yield from $this->readFiles($files, $criteria);
    }

    /**
     * @return \Generator<LogEntry>
     */
    public function readForEnvironment(string $environment, ?SearchCriteria $criteria = null, ?string $kernelContext = null): \Generator
    {
        $files = $this->getLogFilesForEnvironment($environment, $kernelContext);

        yield from $this->readFiles($files, $criteria);
    }

    /**
     * @return \Generator<LogEntry>
     */
    public function readFile(string $filePath, ?SearchCriteria $criteria = null): \Generator
    {
        if (!file_exists($filePath)) {
            throw new LogFileNotFoundException(\sprintf('Log file not found: "%s"', $filePath));
        }

        yield from $this->readFiles([$filePath], $criteria);
    }

    /**
     * @param string[] $files
     *
     * @return \Generator<LogEntry>
     */
    public function readFiles(array $files, ?SearchCriteria $criteria = null): \Generator
    {
        $count = 0;
        $limit = null !== $criteria ? $criteria->getLimit() : \PHP_INT_MAX;
        $offset = null !== $criteria ? $criteria->getOffset() : 0;
        $skipped = 0;

        foreach ($files as $file) {
            if ($count >= $limit) {
                return;
            }

            if (!file_exists($file) || !is_readable($file)) {
                continue;
            }

            $handle = fopen($file, 'r');
            if (false === $handle) {
                continue;
            }

            try {
                $lineNumber = 0;
                $relativePath = $this->getRelativePath($file);
                $fileContext = $this->getKernelContext($file);

                while (false !== ($line = fgets($handle))) {
                    ++$lineNumber;

                    $entry = $this->parser->parse($line, $relativePath, $lineNumber, $fileContext);
                    if (null === $entry) {
                        continue;
                    }

                    if (null !== $criteria && !$criteria->matches($entry)) {
                        continue;
                    }

                    if ($skipped < $offset) {
                        ++$skipped;
                        continue;
                    }

                    yield $entry;
                    ++$count;

                    if ($count >= $limit) {
                        return;
                    }
                }
            } finally {
                fclose($handle);
            }
        }
    }

    /**
     * Returns the most recent entries of the newest log file of every configured context.
     *
     * @return LogEntry[]
     */
    public function tail(int $lines = 50, ?string $level = null, ?string $environment = null, ?string $channel = null, ?string $kernelContext = null): array
    {
        $entriesPerContext = [];

        foreach ($this->resolveLogDirs($kernelContext) as $context => $dir) {
            $files = $this->collectLogFiles([$context => $dir]);
            if (null !== $environment) {
                $files = $this->filterForEnvironment($files, $environment);
            }

            if ([] === $files) {
                continue;
            }

            $file = $files[0];
            if (!file_exists($file) || !is_readable($file)) {
                continue;
            }

            $entriesPerContext[] = $this->tailFromFile($file, $lines, $level, $channel);
        }

        if ([] === $entriesPerContext) {
            return [];
        }

        if (1 === \count($entriesPerContext)) {
            return $entriesPerContext[0];
        }

        $entries = array_merge(...$entriesPerContext);
        usort($entries, static fn (LogEntry $a, LogEntry $b) => $a->getDatetime() <=> $b->getDatetime());

        return \array_slice($entries, -$lines);
    }

    /**
     * @return string[]
     */
    public function getUniqueChannels(?string $kernelContext = null): array
    {
        $channels = [];

        foreach ($this->readAll(null, $kernelContext) as $entry) {
            $channels[$entry->getChannel()] = true;
        }

        return array_keys($channels);
    }

    /**
     * The context a log file belongs to, or null when a single log directory is configured.
     */
    public function getKernelContext(string $filePath): ?string
    {
        $logDir = $this->findLogDir($filePath);
        if (null === $logDir) {
            return null;
        }

        return \is_string($logDir[0]) ? $logDir[0] : null;
    }

    /**
     * @return LogEntry[]
     */
    private function tailFromFile(string $file, int $lines, ?string $level = null, ?string $channel = null): array
    {
        $handle = fopen($file, 'r');
        if (false === $handle) {
            return [];
        }

        try {
            $buffer = [];
            $lineNumber = 0;
            $relativePath = $this->getRelativePath($file);
            $fileContext = $this->getKernelContext($file);

            while (false !== ($line = fgets($handle))) {
                ++$lineNumber;
                $buffer[] = ['line' => $line, 'number' => $lineNumber];

                // Keep buffer size at 2x the requested lines to account for filtered entries
                if (\count($buffer) > $lines * 2) {
                    array_shift($buffer);
                }
            }

            $entries = [];
            for ($i = \count($buffer) - 1; $i >= 0 && \count($entries) < $lines; --$i) {
                $entry = $this->parser->parse($buffer[$i]['line'], $relativePath, $buffer[$i]['number'], $fileContext);
                if (null === $entry) {
                    continue;
                }

                if (null !== $level && strtoupper($level) !== $entry->getLevel()) {
                    continue;
                }

                if (null !== $channel && strtolower($channel) !== strtolower($entry->getChannel())) {
                    continue;
                }

                $entries[] = $entry;
            }

            return array_reverse($entries);
        } finally {
            fclose($handle);
        }
    }

    private function getRelativePath(string $filePath): string
    {
        $logDir = $this->findLogDir($filePath);
        if (null === $logDir) {
            return basename($filePath);
        }

        return ltrim(substr($filePath, \strlen($logDir[1])), '/\\');
    }

    /**
     * Returns the configured directory a file belongs to, the longest match wins so nested
     * context directories are resolved to the most specific one.
     *
     * @return array{0: string|int, 1: string}|null
     */
    private function findLogDir(string $filePath): ?array
    {
        $matchedContext = null;
        $matchedDir = null;

        foreach ($this->logDirs as $context => $dir) {
            if (!str_starts_with($filePath, $dir)) {
                continue;
            }

            if (null === $matchedDir || \strlen($dir) > \strlen($matchedDir)) {
                $matchedContext = $context;
                $matchedDir = $dir;
            }
        }

        if (null === $matchedDir || null === $matchedContext) {
            return null;
        }

        return [$matchedContext, $matchedDir];
    }

    /**
     * @param string[] $files
     *
     * @return string[]
     */
    private function filterForEnvironment(array $files, string $environment): array
    {
        return array_values(array_filter($files, static function (string $file) use ($environment) {
            $filename = basename($file);

            // Match files like dev.log, prod.log, test.log
            // Or files containing the environment name like app_dev.log
            return str_contains($filename, $environment);
        }));
    }

    /**
     * @param array<string|int, string> $logDirs
     *
     * @return string[]
     */
    private function collectLogFiles(array $logDirs): array
    {
        $allFiles = [];

        foreach ($logDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir.'/*.log');
            if (false === $files) {
                continue;
            }

            foreach ($files as $file) {
                $allFiles[] = $file;
            }
        }

        usort($allFiles, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $allFiles;
    }

    /**
     * @return array<string|int, string>
     */
    private function resolveLogDirs(?string $kernelContext): array
    {
        if (null === $kernelContext || '' === $kernelContext) {
            return $this->logDirs;
        }

        $logDirs = [];
        foreach ($this->logDirs as $context => $dir) {
            if ($context === $kernelContext) {
                $logDirs[$context] = $dir;
            }
        }

        return $logDirs;
    }
}
