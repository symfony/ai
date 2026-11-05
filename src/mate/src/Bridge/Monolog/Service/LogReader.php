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
    /** Decompressed bytes read from one `.log.gz`; guards against a compression bomb. */
    private const COMPRESSED_BYTES = 256 * 1024 * 1024;

    /** Longest line taken from a `.log.gz`; the rest of a longer line is discarded. */
    private const COMPRESSED_LINE_BYTES = 1024 * 1024;

    /**
     * @var array<string|int, string>
     */
    private readonly array $logDirs;

    /**
     * Compressed files the last read stopped in at the size cap.
     *
     * @var string[]
     */
    private array $unfinished = [];

    /**
     * @param string|array<string, string> $logDir A single log directory, or a map of context name to log
     *                                             directory for multi-kernel (APP_ID) applications
     */
    public function __construct(
        private LogParser $parser,
        string|array $logDir,
        private int $compressedBytes = self::COMPRESSED_BYTES,
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
     * The compressed rotations (`*.log.gz`) of the log directories, as left by a nightly compression
     * job. They are read after the plain files, and listed separately from them.
     *
     * @return string[]
     */
    public function getCompressedLogFiles(?string $environment = null, ?string $kernelContext = null): array
    {
        $files = $this->collectLogFiles($this->resolveLogDirs($kernelContext), ['/*.log.gz', '/*.log.*.gz']);

        return null !== $environment ? $this->filterForEnvironment($files, $environment) : $files;
    }

    /**
     * Compressed rotations that cannot be read at all because zlib is missing. Relative to their log
     * directory, like the `source_file` of an entry.
     *
     * @return string[]
     */
    public function getUnreadableLogFiles(?string $environment = null, ?string $kernelContext = null): array
    {
        return \function_exists('gzopen') ? [] : array_map($this->getRelativePath(...), $this->getCompressedLogFiles($environment, $kernelContext));
    }

    /**
     * Log files the last read did not (fully) read: the unreadable ones, and the compressed files it
     * stopped in at the size cap.
     *
     * @return string[]
     */
    public function getSkippedLogFiles(?string $environment = null, ?string $kernelContext = null): array
    {
        return array_values(array_unique([...$this->getUnreadableLogFiles($environment, $kernelContext), ...$this->unfinished]));
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
        $files = [...$this->getLogFiles($kernelContext), ...$this->readableCompressedFiles(null, $kernelContext)];

        yield from $this->readFiles($files, $criteria);
    }

    /**
     * @return \Generator<LogEntry>
     */
    public function readForEnvironment(string $environment, ?SearchCriteria $criteria = null, ?string $kernelContext = null): \Generator
    {
        $files = [...$this->getLogFilesForEnvironment($environment, $kernelContext), ...$this->readableCompressedFiles($environment, $kernelContext)];

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
        $this->unfinished = [];

        foreach ($files as $file) {
            if ($count >= $limit) {
                return;
            }

            if (!file_exists($file) || !is_readable($file)) {
                continue;
            }

            $compressed = str_ends_with($file, '.gz');
            $handle = fopen($compressed ? 'compress.zlib://'.$file : $file, 'r');
            if (false === $handle) {
                continue;
            }

            try {
                $lineNumber = 0;
                $bytes = 0;
                $relativePath = $this->getRelativePath($file);
                $fileContext = $this->getKernelContext($file);

                while (false !== ($line = $compressed ? $this->readCompressedLine($handle, $bytes) : fgets($handle))) {
                    ++$lineNumber;

                    if ($compressed && $bytes > $this->compressedBytes) {
                        $this->unfinished[] = $relativePath;

                        break;
                    }

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
     * @return array{entries: LogEntry[], total_matched: int, truncated: bool}
     */
    public function tail(int $limit = 50, ?string $level = null, ?string $environment = null, ?string $channel = null, ?string $kernelContext = null): array
    {
        $entriesPerContext = [];
        $totalMatched = 0;

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

            $tailed = $this->tailFromFile($file, $limit, $level, $channel);
            $entriesPerContext[] = $tailed['entries'];
            $totalMatched += $tailed['total_matched'];
        }

        if ([] === $entriesPerContext) {
            return ['entries' => [], 'total_matched' => 0, 'truncated' => false];
        }

        if (1 === \count($entriesPerContext)) {
            $entries = $entriesPerContext[0];
        } else {
            $entries = array_merge(...$entriesPerContext);
            usort($entries, static fn (LogEntry $a, LogEntry $b) => $a->getDatetime() <=> $b->getDatetime());
            $entries = \array_slice($entries, -$limit);
        }

        return [
            'entries' => $entries,
            'total_matched' => $totalMatched,
            // Merging contexts can discard entries a single context's tail already kept,
            // so truncation is judged against what is actually returned, not against $limit.
            'truncated' => $totalMatched > \count($entries),
        ];
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
     * @return array{entries: LogEntry[], total_matched: int}
     */
    private function tailFromFile(string $file, int $limit, ?string $level = null, ?string $channel = null): array
    {
        $handle = fopen($file, 'r');
        if (false === $handle) {
            return ['entries' => [], 'total_matched' => 0];
        }

        try {
            $entries = [];
            $totalMatched = 0;
            $lineNumber = 0;
            $relativePath = $this->getRelativePath($file);
            $fileContext = $this->getKernelContext($file);

            while (false !== ($line = fgets($handle))) {
                ++$lineNumber;

                $entry = $this->parser->parse($line, $relativePath, $lineNumber, $fileContext);
                if (null === $entry) {
                    continue;
                }

                if (null !== $level && strtoupper($level) !== $entry->getLevel()) {
                    continue;
                }

                if (null !== $channel && strtolower($channel) !== strtolower($entry->getChannel())) {
                    continue;
                }

                ++$totalMatched;

                // Keep only the most recent $limit matches, so an earlier match is never
                // dropped based on where raw lines happen to fall near the end of the file.
                $entries[] = $entry;
                if (\count($entries) > $limit) {
                    array_shift($entries);
                }
            }

            return ['entries' => $entries, 'total_matched' => $totalMatched];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return string[]
     */
    private function readableCompressedFiles(?string $environment, ?string $kernelContext): array
    {
        return \function_exists('gzopen') ? $this->getCompressedLogFiles($environment, $kernelContext) : [];
    }

    /**
     * @param resource $handle
     * @param int      $bytes  Decompressed bytes read so far, including the discarded ones
     */
    private function readCompressedLine($handle, int &$bytes): string|false
    {
        $line = fgets($handle, self::COMPRESSED_LINE_BYTES);
        if (false !== $line) {
            $bytes += \strlen($line);
        }

        // Discard the rest of a line longer than the cap, so one huge line cannot fill the memory.
        while (false !== $line && !str_ends_with($line, "\n") && !feof($handle) && \strlen($line) >= self::COMPRESSED_LINE_BYTES - 1 && $bytes <= $this->compressedBytes) {
            $rest = fgets($handle, self::COMPRESSED_LINE_BYTES);
            if (false === $rest) {
                break;
            }

            $bytes += \strlen($rest);
            if (str_ends_with($rest, "\n")) {
                break;
            }
        }

        return $line;
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
     * Directories are compared with a trailing separator boundary so a configured directory
     * like "/var/log/web" does not also match a sibling directory like "/var/log/website".
     *
     * @return array{0: string|int, 1: string}|null
     */
    private function findLogDir(string $filePath): ?array
    {
        $matchedContext = null;
        $matchedDir = null;

        foreach ($this->logDirs as $context => $dir) {
            $normalizedDir = rtrim($dir, '/\\');

            if ($filePath !== $normalizedDir && !str_starts_with($filePath, $normalizedDir.'/') && !str_starts_with($filePath, $normalizedDir.'\\')) {
                continue;
            }

            if (null === $matchedDir || \strlen($normalizedDir) > \strlen($matchedDir)) {
                $matchedContext = $context;
                $matchedDir = $normalizedDir;
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
     * @param string[]                  $patterns
     *
     * @return string[]
     */
    private function collectLogFiles(array $logDirs, array $patterns = ['/*.log']): array
    {
        $allFiles = [];

        foreach ($logDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                $found = glob($dir.$pattern);
                if (false === $found) {
                    continue;
                }

                foreach ($found as $file) {
                    $allFiles[] = $file;
                }
            }
        }

        usort($allFiles, static function (string $a, string $b): int {
            $result = filemtime($b) <=> filemtime($a);

            if (0 !== $result) {
                return $result;
            }

            // filemtime() only has 1-second resolution, so same-second rotated files tie
            // here. Break by basename descending: Monolog's rotation naming keeps the
            // unsuffixed/current file lexically greatest, so it still sorts first.
            return basename($b) <=> basename($a);
        });

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
