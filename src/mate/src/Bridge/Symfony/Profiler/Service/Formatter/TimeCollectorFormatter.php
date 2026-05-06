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
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;

/**
 * Formats time collector data for AI consumption.
 *
 * Exposes total request duration, initialization time, and individual
 * stopwatch events to help diagnose slow requests.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<TimeDataCollector>
 */
final class TimeCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'time';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof TimeDataCollector);

        $events = [];
        foreach ($collector->getEvents() as $name => $event) {
            if ('__section__' === $name) {
                continue;
            }
            $events[] = [
                'name' => $name,
                'category' => $event->getCategory(),
                'duration_ms' => round($event->getDuration(), 2),
                'memory' => $event->getMemory(),
            ];
        }

        usort($events, static fn (array $a, array $b) => $b['duration_ms'] <=> $a['duration_ms']);

        return [
            'duration_ms' => round($collector->getDuration(), 2),
            'init_time_ms' => round($collector->getInitTime(), 2),
            'stopwatch_installed' => $collector->isStopwatchInstalled(),
            'events' => $events,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof TimeDataCollector);

        return [
            'duration_ms' => round($collector->getDuration(), 2),
            'init_time_ms' => round($collector->getInitTime(), 2),
        ];
    }
}
