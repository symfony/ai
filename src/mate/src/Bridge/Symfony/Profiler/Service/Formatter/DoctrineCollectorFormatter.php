<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Formats Doctrine collector data.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<DoctrineDataCollector>
 */
final class DoctrineCollectorFormatter implements CollectorFormatterInterface
{
    private const MAX_QUERIES = 50;
    private const MAX_PARAM_LENGTH = 100;

    public function getName(): string
    {
        return 'db';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof DoctrineDataCollector);

        $queries = $this->flattenQueries($collector);
        $truncated = \count($queries) > self::MAX_QUERIES;
        $queries = \array_slice($queries, 0, self::MAX_QUERIES);

        return [
            'query_count' => $collector->getQueryCount(),
            'total_time_ms' => round($collector->getTime() * 1000, 2),
            'connections' => array_keys($collector->getConnections()),
            'queries' => $queries,
            'queries_truncated' => $truncated,
            'duplicate_queries' => $this->detectDuplicateQueries($collector),
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof DoctrineDataCollector);

        return [
            'query_count' => $collector->getQueryCount(),
            'total_time_ms' => round($collector->getTime() * 1000, 2),
            'duplicate_query_count' => \count($this->detectDuplicateQueries($collector)),
        ];
    }

    /**
     * @return list<array{connection: string, sql: string, params: mixed, time_ms: float}>
     */
    private function flattenQueries(DoctrineDataCollector $collector): array
    {
        $flattened = [];

        foreach ($collector->getQueries() as $connection => $queries) {
            foreach ($queries as $query) {
                $flattened[] = [
                    'connection' => $connection,
                    'sql' => $query['sql'] ?? '',
                    'params' => $this->truncateParams($query['params'] ?? null),
                    'time_ms' => round(($query['executionMS'] ?? 0.0) * 1000, 2),
                ];
            }
        }

        return $flattened;
    }

    /**
     * @return list<array{sql: string, count: int, total_time_ms: float}>
     */
    private function detectDuplicateQueries(DoctrineDataCollector $collector): array
    {
        /** @var array<string, array{count: int, total_time_ms: float}> $grouped */
        $grouped = [];

        foreach ($collector->getQueries() as $queries) {
            foreach ($queries as $query) {
                $sql = $query['sql'] ?? '';
                if (!isset($grouped[$sql])) {
                    $grouped[$sql] = ['count' => 0, 'total_time_ms' => 0.0];
                }

                ++$grouped[$sql]['count'];
                $grouped[$sql]['total_time_ms'] += ($query['executionMS'] ?? 0.0) * 1000;
            }
        }

        $duplicates = [];
        foreach ($grouped as $sql => $data) {
            if ($data['count'] <= 1) {
                continue;
            }

            $duplicates[] = [
                'sql' => $sql,
                'count' => $data['count'],
                'total_time_ms' => round($data['total_time_ms'], 2),
            ];
        }

        return $duplicates;
    }

    private function truncateParams(mixed $params): mixed
    {
        if (\is_string($params) && \strlen($params) > self::MAX_PARAM_LENGTH) {
            return substr($params, 0, self::MAX_PARAM_LENGTH).'...';
        }

        if (\is_array($params)) {
            return array_map(fn (mixed $param): mixed => $this->truncateParams($param), $params);
        }

        return $params;
    }
}
