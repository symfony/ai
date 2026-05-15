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
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;

/**
 * Formats memory collector data for AI consumption.
 *
 * Exposes peak memory usage and the configured memory limit to help
 * diagnose memory-intensive requests.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<MemoryDataCollector>
 */
final class MemoryCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'memory';
    }

    /**
     * @return array{
     *     memory_bytes: int,
     *     memory_mb: float,
     *     memory_limit_bytes: int|float,
     *     memory_limit_mb: float|string,
     *     usage_percent: float|null,
     * }
     */
    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof MemoryDataCollector);

        $memoryBytes = $collector->getMemory();
        $limitBytes = $collector->getMemoryLimit();

        if ($limitBytes > 0) {
            $usagePercent = round($memoryBytes / $limitBytes * 100, 2);
            $limitMb = round($limitBytes / 1024 / 1024, 2);
        } else {
            $usagePercent = null;
            $limitMb = 'unlimited';
        }

        return [
            'memory_bytes' => $memoryBytes,
            'memory_mb' => round($memoryBytes / 1024 / 1024, 2),
            'memory_limit_bytes' => $limitBytes,
            'memory_limit_mb' => $limitMb,
            'usage_percent' => $usagePercent,
        ];
    }

    /**
     * @return array{
     *     memory_mb: float,
     *     memory_limit_mb: float|string,
     *     usage_percent: float|null,
     * }
     */
    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof MemoryDataCollector);

        $memoryBytes = $collector->getMemory();
        $limitBytes = $collector->getMemoryLimit();

        if ($limitBytes > 0) {
            $usagePercent = round($memoryBytes / $limitBytes * 100, 2);
            $limitMb = round($limitBytes / 1024 / 1024, 2);
        } else {
            $usagePercent = null;
            $limitMb = 'unlimited';
        }

        return [
            'memory_mb' => round($memoryBytes / 1024 / 1024, 2),
            'memory_limit_mb' => $limitMb,
            'usage_percent' => $usagePercent,
        ];
    }
}
