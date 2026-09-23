<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Monolog\Capability;

use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Bridge\Monolog\Model\SearchCriteria;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogReader;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\InvalidArgumentException;

/**
 * Tools for searching and analyzing Monolog log files.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LogSearchTool
{
    public function __construct(
        private LogReader $reader,
    ) {
    }

    /**
     * @param string|null $term          Text to search for in log messages, or a regex pattern when $regex is true; omit to filter by level/channel/date only
     * @param bool        $regex         When true, treat $term as a regular expression pattern
     * @param string|null $level         Filter by log level: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
     * @param string|null $channel       Filter by Monolog channel name (e.g. app, security, doctrine)
     * @param string|null $environment   Filter by Symfony environment (e.g. dev, prod, test)
     * @param string|null $from          Start date filter, any PHP-parseable date string (e.g. 2024-01-01, -1 hour, yesterday)
     * @param string|null $to            End date filter, any PHP-parseable date string
     * @param int         $limit         Maximum number of entries to return
     * @param string|null $kernelContext Filter by kernel context (e.g. the APP_ID of a multi-kernel application), only relevant when multiple log directories are configured
     */
    #[MateTool(name: 'monolog-search', title: 'Log Search', description: 'Search log entries by text or regex pattern. Supports filtering by log level, channel, environment, and date range. Use empty string for term to match all entries when using filters only. When multiple kernel contexts are configured, entries carry a kernel_context field and can be narrowed with the kernelContext parameter. The response also carries total_matched (the number of returned entries, so you do not have to count them) and truncated (true when total_matched equals limit, meaning more matches may exist beyond the page): raise limit and search again to see if truncated turns false.')]
    public function search(
        ?string $term = null,
        bool $regex = false,
        ?string $level = null,
        ?string $channel = null,
        ?string $environment = null,
        ?string $from = null,
        ?string $to = null,
        int $limit = 100,
        ?string $kernelContext = null,
    ): string {
        if ($regex) {
            if (null === $term) {
                throw new InvalidArgumentException('The "term" parameter is required when "regex" is true.');
            }

            $pattern = $term;
            if (!str_starts_with($pattern, '/') && !str_starts_with($pattern, '#')) {
                $pattern = '/'.$pattern.'/i';
            }

            $criteria = new SearchCriteria(
                regex: $pattern,
                level: $level,
                channel: $channel,
                from: $this->parseDate($from),
                to: $this->parseDate($to),
                limit: $limit,
            );
        } else {
            $criteria = new SearchCriteria(
                term: '' === $term ? null : $term,
                level: $level,
                channel: $channel,
                from: $this->parseDate($from),
                to: $this->parseDate($to),
                limit: $limit,
            );
        }

        return ResponseEncoder::encodeUntrusted($this->collectResults($criteria, $environment, $kernelContext));
    }

    /**
     * @param string      $key           The context field name to search for (e.g. user_id, exception, order_id)
     * @param string      $value         The value to match in the context field
     * @param string|null $level         Filter by log level: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
     * @param string|null $environment   Filter by Symfony environment (e.g. dev, prod, test)
     * @param int         $limit         Maximum number of entries to return
     * @param string|null $kernelContext Filter by kernel context (e.g. the APP_ID of a multi-kernel application), only relevant when multiple log directories are configured
     */
    #[MateTool(name: 'monolog-context-search', title: 'Log Context Search', description: 'Search log entries by structured context data. Finds entries where a specific context key contains the given value. The response also carries total_matched (the number of returned entries, so you do not have to count them) and truncated (true when total_matched equals limit, meaning more matches may exist beyond the page): raise limit and search again to see if truncated turns false.')]
    public function searchContext(
        string $key,
        string $value,
        ?string $level = null,
        ?string $environment = null,
        int $limit = 100,
        ?string $kernelContext = null,
    ): string {
        $criteria = new SearchCriteria(
            level: $level,
            contextKey: $key,
            contextValue: $value,
            limit: $limit,
        );

        return ResponseEncoder::encodeUntrusted($this->collectResults($criteria, $environment, $kernelContext));
    }

    /**
     * @param int         $limit         Number of most recent log entries to return
     * @param string|null $level         Filter by log level: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
     * @param string|null $environment   Filter by Symfony environment (e.g. dev, prod, test)
     * @param string|null $channel       Filter by Monolog channel name (e.g. app, security, doctrine)
     * @param string|null $kernelContext Filter by kernel context (e.g. the APP_ID of a multi-kernel application), only relevant when multiple log directories are configured
     */
    #[MateTool(name: 'monolog-tail', title: 'Log Tail', description: 'Get the most recent log entries. Reads from the end of log files, optionally filtered by level, environment, and channel. When multiple kernel contexts are configured, the most recent entries of every context are merged. tail only reads the newest log file per context: the response also carries total_matched (the match count within the file(s) actually read, not the whole log directory) and truncated (true when total_matched exceeds what entries returns): the length of entries alone is not that count when truncated is true, raise limit instead.')]
    public function tail(int $limit = 50, ?string $level = null, ?string $environment = null, ?string $channel = null, ?string $kernelContext = null): string
    {
        $result = $this->reader->tail($limit, $level, $environment, $channel, $kernelContext);

        return ResponseEncoder::encodeUntrusted([
            'entries' => array_values(array_map(static fn ($entry) => $entry->toArray(), $result['entries'])),
            'total_matched' => $result['total_matched'],
            'truncated' => $result['truncated'],
        ]);
    }

    /**
     * @param string|null $environment   Filter log files by Symfony environment (e.g. dev, prod, test)
     * @param string|null $kernelContext Filter by kernel context (e.g. the APP_ID of a multi-kernel application), only relevant when multiple log directories are configured
     */
    #[MateTool(name: 'monolog-list-files', title: 'List Log Files', description: 'List available log files with metadata (name, path, size, last modified). Use to discover which logs exist before searching. When multiple kernel contexts are configured, files carry a kernel_context field.')]
    public function listFiles(?string $environment = null, ?string $kernelContext = null): string
    {
        $files = null !== $environment
            ? $this->reader->getLogFilesForEnvironment($environment, $kernelContext)
            : $this->reader->getLogFiles($kernelContext);
        $result = [];

        foreach ($files as $file) {
            $entry = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file) ?: 0,
                'modified' => date(\DateTimeInterface::ATOM, filemtime($file) ?: 0),
            ];

            $context = $this->reader->getKernelContext($file);
            if (null !== $context) {
                $entry['kernel_context'] = $context;
            }

            $result[] = $entry;
        }

        return ResponseEncoder::encodeUntrusted(['files' => $result]);
    }

    /**
     * @param string|null $kernelContext Filter by kernel context (e.g. the APP_ID of a multi-kernel application), only relevant when multiple log directories are configured
     */
    #[MateTool(name: 'monolog-list-channels', title: 'List Log Channels', description: 'List all unique Monolog channel names found across log files (e.g. app, security, doctrine).')]
    public function listChannels(?string $kernelContext = null): string
    {
        return ResponseEncoder::encodeUntrusted(['channels' => $this->reader->getUniqueChannels($kernelContext)]);
    }

    /**
     * @return array{entries: list<array<string, mixed>>, total_matched: int, truncated: bool}
     */
    private function collectResults(SearchCriteria $criteria, ?string $environment = null, ?string $kernelContext = null): array
    {
        $entries = [];

        $generator = null !== $environment
            ? $this->reader->readForEnvironment($environment, $criteria, $kernelContext)
            : $this->reader->readAll($criteria, $kernelContext);

        foreach ($generator as $entry) {
            $entries[] = $entry->toArray();
        }

        // total_matched is the size of the returned page, not an exact total across the
        // whole log directory: reading still stops at $limit.
        return [
            'entries' => $entries,
            'total_matched' => \count($entries),
            'truncated' => \count($entries) === $criteria->getLimit(),
        ];
    }

    private function parseDate(?string $date): ?\DateTimeImmutable
    {
        if (null === $date || '' === $date) {
            return null;
        }

        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }
    }
}
