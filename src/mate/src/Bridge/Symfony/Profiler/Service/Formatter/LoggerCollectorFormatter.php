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

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;

/**
 * Formats logger collector data.
 *
 * Reports log counts by severity and individual log entries (capped at MAX_LOGS)
 * with message and context extracted from VarDumper Data objects.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<LoggerDataCollector>
 */
final class LoggerCollectorFormatter implements CollectorFormatterInterface
{
    private const MAX_LOGS = 100;

    public function getName(): string
    {
        return 'logger';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof LoggerDataCollector);

        $processedLogs = $collector->getProcessedLogs();
        $truncated = \count($processedLogs) > self::MAX_LOGS;
        $processedLogs = \array_slice($processedLogs, 0, self::MAX_LOGS);

        return [
            'error_count' => $collector->countErrors(),
            'warning_count' => $collector->countWarnings(),
            'deprecation_count' => $collector->countDeprecations(),
            'scream_count' => $collector->countScreams(),
            'logs' => $this->formatLogs($processedLogs),
            'logs_truncated' => $truncated,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof LoggerDataCollector);

        return [
            'error_count' => $collector->countErrors(),
            'warning_count' => $collector->countWarnings(),
            'deprecation_count' => $collector->countDeprecations(),
            'scream_count' => $collector->countScreams(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     *
     * @return list<array<string, mixed>>
     */
    private function formatLogs(array $logs): array
    {
        $formatted = [];
        foreach ($logs as $log) {
            $message = $log['message'] ?? null;
            if (\is_object($message) && method_exists($message, 'getValue')) {
                $message = $message->getValue();
            }

            $context = $log['context'] ?? null;
            if (\is_object($context) && method_exists($context, 'getValue')) {
                $context = $context->getValue(true);
            }

            $formatted[] = [
                'type' => $log['type'] ?? null,
                'timestamp' => $log['timestamp'] ?? null,
                'priority' => $log['priority'] ?? null,
                'priority_name' => $log['priorityName'] ?? null,
                'channel' => $log['channel'] ?? null,
                'message' => $message,
                'context' => $context,
                'error_count' => $log['errorCount'] ?? null,
            ];
        }

        return $formatted;
    }
}
