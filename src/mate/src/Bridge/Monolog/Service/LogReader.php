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
    public function __construct(
        private LogParser $parser,
        private string $logDir,
    ) {
    }

    /**
     * @return string[]
     */
    public function getLogFiles(): array
    {
        if (!is_dir($this->logDir)) {
            return [];
        }

        $files = glob($this->logDir.'/*.log');
        if (false === $files) {
            return [];
        }

        usort($files, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $files;
    }

    /**
     * @return string[]
     */
    public function getLogFilesForEnvironment(string $environment): array
    {
        $files = $this->getLogFiles();

        return array_filter($files, static function (string $file) use ($environment) {
            $filename = basename($file);

            // Match files like dev.log, prod.log, test.log
            // Or files containing the environment name like app_dev.log
            return str_contains($filename, $environment);
        });
    }

    /**
     * @return \Generator<LogEntry>
     */
    public function readAll(?SearchCriteria $criteria = null): \Generator
    {
        $files = $this->getLogFiles();

        yield from $this->readFiles($files, $criteria);
    }

    /**
     * @return \Generator<LogEntry>
     */
    public function readForEnvironment(string $environment, ?SearchCriteria $criteria = null): \Generator
    {
        $files = $this->getLogFilesForEnvironment($environment);

        yield from $this->readFiles($files, $criteria);
    }

    /**
     * @return \Generator<LogEntry>
     */
    public function readFile(string $filePath, ?SearchCriteria $criteria = null): \Generator
    {
        if (!file_exists($filePath)) {
            throw LogFileNotFoundException::forPath($filePath);
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
        $limit = null !== $criteria ? $criteria->limit : \PHP_INT_MAX;
        $offset = null !== $criteria ? $criteria->offset : 0;
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

                while (false !== ($line = fgets($handle))) {
                    ++$lineNumber;

                    $entry = $this->parser->parse($line, $relativePath, $lineNumber);
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
     * @return LogEntry[]
     */
    public function tail(int $lines = 50, ?string $level = null, ?string $environment = null): array
    {
        $files = null !== $environment
            ? $this->getLogFilesForEnvironment($environment)
            : $this->getLogFiles();

        if ([] === $files) {
            return [];
        }

        $file = $files[0];
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }

        $entries = [];
        $allLines = file($file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (false === $allLines) {
            return [];
        }

        $relativePath = $this->getRelativePath($file);
        $totalLines = \count($allLines);

        for ($i = $totalLines - 1; $i >= 0 && \count($entries) < $lines; --$i) {
            $entry = $this->parser->parse($allLines[$i], $relativePath, $i + 1);
            if (null === $entry) {
                continue;
            }

            if (null !== $level && strtoupper($level) !== $entry->level) {
                continue;
            }

            $entries[] = $entry;
        }

        return array_reverse($entries);
    }

    /**
     * @return string[]
     */
    public function getUniqueChannels(): array
    {
        $channels = [];

        foreach ($this->readAll() as $entry) {
            $channels[$entry->channel] = true;
        }

        return array_keys($channels);
    }

    private function getRelativePath(string $filePath): string
    {
        if (str_starts_with($filePath, $this->logDir)) {
            return ltrim(substr($filePath, \strlen($this->logDir)), '/\\');
        }

        return basename($filePath);
    }
}
