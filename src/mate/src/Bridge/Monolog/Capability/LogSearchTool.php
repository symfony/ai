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

    #[McpTool('monolog-search', 'Search log entries by text term with optional level, channel, environment, and date filters')]
    public function search(
        string $term,
        ?string $level = null,
        ?string $channel = null,
        ?string $environment = null,
        ?string $from = null,
        ?string $to = null,
        int $limit = 100,
    ): string {
        $criteria = new SearchCriteria(
            term: $term,
            level: $level,
            channel: $channel,
            from: $this->parseDate($from),
            to: $this->parseDate($to),
            limit: $limit,
        );

        return Toon::encode($this->collectResults($criteria, $environment));
    }

    #[McpTool('monolog-search-regex', 'Search log entries using a regex pattern')]
    public function searchRegex(
        string $pattern,
        ?string $level = null,
        ?string $channel = null,
        ?string $environment = null,
        int $limit = 100,
    ): string {
        // Ensure pattern has delimiters
        if (!str_starts_with($pattern, '/') && !str_starts_with($pattern, '#')) {
            $pattern = '/'.$pattern.'/i';
        }

        $criteria = new SearchCriteria(
            regex: $pattern,
            level: $level,
            channel: $channel,
            limit: $limit,
        );

        return Toon::encode($this->collectResults($criteria, $environment));
    }

    #[McpTool('monolog-context-search', 'Search logs by context field value')]
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

        return Toon::encode($this->collectResults($criteria, $environment));
    }

    #[McpTool('monolog-tail', 'Get the last N log entries')]
    public function tail(int $lines = 50, ?string $level = null, ?string $environment = null): string
    {
        $entries = $this->reader->tail($lines, $level, $environment);

        return Toon::encode(array_values(array_map(static fn ($entry) => $entry->toArray(), $entries)));
    }

    #[McpTool('monolog-list-files', 'List available log files, optionally filtered by environment')]
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

        return Toon::encode($result);
    }

    #[McpTool('monolog-list-channels', 'List all log channels found in log files')]
    public function listChannels(): string
    {
        return Toon::encode($this->reader->getUniqueChannels());
    }

    /**
     * Get log entries by level (e.g., all ERROR logs).
     */
    #[McpTool('monolog-by-level', 'Get log entries filtered by level (DEBUG, INFO, WARNING, ERROR, etc.)')]
    public function byLevel(string $level, ?string $environment = null, int $limit = 100): string
    {
        $criteria = new SearchCriteria(
            level: $level,
            limit: $limit,
        );

        return Toon::encode($this->collectResults($criteria, $environment));
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
