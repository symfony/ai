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

use HelgeSverre\Toon\Toon;
use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\Mate\Bridge\Monolog\Model\SearchCriteria;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogReader;

/**
 * MCP tools for searching and analyzing Monolog log files.
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
     * @param string      $term        Text to search for in log messages, or a regex pattern when $regex is true
     * @param bool        $regex       When true, treat $term as a regular expression pattern
     * @param string|null $level       Filter by log level: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
     * @param string|null $channel     Filter by Monolog channel name (e.g. app, security, doctrine)
     * @param string|null $environment Filter by Symfony environment (e.g. dev, prod, test)
     * @param string|null $from        Start date filter, any PHP-parseable date string (e.g. 2024-01-01, -1 hour, yesterday)
     * @param string|null $to          End date filter, any PHP-parseable date string
     * @param int         $limit       Maximum number of entries to return
     */
    #[McpTool('monolog-search', 'Search log entries by text or regex pattern. Supports filtering by log level, channel, environment, and date range. Use empty string for term to match all entries when using filters only.')]
    public function search(
        string $term,
        bool $regex = false,
        ?string $level = null,
        ?string $channel = null,
        ?string $environment = null,
        ?string $from = null,
        ?string $to = null,
        int $limit = 100,
    ): string {
        if ($regex) {
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
                term: $term,
                level: $level,
                channel: $channel,
                from: $this->parseDate($from),
                to: $this->parseDate($to),
                limit: $limit,
            );
        }

        return Toon::encode(['entries' => $this->collectResults($criteria, $environment)]);
    }

    /**
     * @param string      $key         The context field name to search for (e.g. user_id, exception, order_id)
     * @param string      $value       The value to match in the context field
     * @param string|null $level       Filter by log level: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
     * @param string|null $environment Filter by Symfony environment (e.g. dev, prod, test)
     * @param int         $limit       Maximum number of entries to return
     */
    #[McpTool('monolog-context-search', 'Search log entries by structured context data. Finds entries where a specific context key contains the given value.')]
    public function searchContext(
        string $key,
        string $value,
        ?string $level = null,
        ?string $environment = null,
        int $limit = 100,
    ): string {
        $criteria = new SearchCriteria(
            level: $level,
            contextKey: $key,
            contextValue: $value,
            limit: $limit,
        );

        return Toon::encode(['entries' => $this->collectResults($criteria, $environment)]);
    }

    /**
     * @param int         $lines       Number of most recent log entries to return
     * @param string|null $level       Filter by log level: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
     * @param string|null $environment Filter by Symfony environment (e.g. dev, prod, test)
     */
    #[McpTool('monolog-tail', 'Get the most recent log entries. Reads from the end of log files, optionally filtered by level and environment.')]
    public function tail(int $lines = 50, ?string $level = null, ?string $environment = null): string
    {
        $entries = $this->reader->tail($lines, $level, $environment);

        return Toon::encode(['entries' => array_values(array_map(static fn ($entry) => $entry->toArray(), $entries))]);
    }

    /**
     * @param string|null $environment Filter log files by Symfony environment (e.g. dev, prod, test)
     */
    #[McpTool('monolog-list-files', 'List available log files with metadata (name, path, size, last modified). Use to discover which logs exist before searching.')]
    public function listFiles(?string $environment = null): string
    {
        $files = null !== $environment
            ? $this->reader->getLogFilesForEnvironment($environment)
            : $this->reader->getLogFiles();
        $result = [];

        foreach ($files as $file) {
            $result[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file) ?: 0,
                'modified' => date(\DateTimeInterface::ATOM, filemtime($file) ?: 0),
            ];
        }

        return Toon::encode(['files' => $result]);
    }

    #[McpTool('monolog-list-channels', 'List all unique Monolog channel names found across log files (e.g. app, security, doctrine).')]
    public function listChannels(): string
    {
        return Toon::encode(['channels' => $this->reader->getUniqueChannels()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectResults(SearchCriteria $criteria, ?string $environment = null): array
    {
        $results = [];

        $generator = null !== $environment
            ? $this->reader->readForEnvironment($environment, $criteria)
            : $this->reader->readAll($criteria);

        foreach ($generator as $entry) {
            $results[] = $entry->toArray();
        }

        return $results;
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
