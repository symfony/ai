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
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;

/**
 * Formats event dispatcher collector data.
 *
 * Reports called, not-called, and orphaned events per dispatcher,
 * with execution time in milliseconds.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<EventDataCollector>
 */
final class EventCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'events';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof EventDataCollector);

        $data = $collector->getData();
        if (\is_object($data) && method_exists($data, 'getValue')) {
            $data = $data->getValue(true);
        }

        $rawDispatchers = $data['dispatchers'] ?? [];
        if (\is_object($rawDispatchers) && method_exists($rawDispatchers, 'getValue')) {
            $rawDispatchers = $rawDispatchers->getValue(true);
        }

        $dispatchers = [];
        $totalCalled = 0;
        $totalNotCalled = 0;
        $totalOrphaned = 0;

        foreach ($rawDispatchers as $name => $dispatcherData) {
            if (\is_object($dispatcherData) && method_exists($dispatcherData, 'getValue')) {
                $dispatcherData = $dispatcherData->getValue(true);
            }

            $calledListeners = $this->extractListeners($dispatcherData['called_listeners'] ?? [], true);
            $notCalledListeners = $this->extractListeners($dispatcherData['not_called_listeners'] ?? [], false);
            $orphanedEvents = $this->extractOrphanedEvents($dispatcherData['orphaned_events'] ?? []);

            $totalCalled += \count($calledListeners);
            $totalNotCalled += \count($notCalledListeners);
            $totalOrphaned += \count($orphanedEvents);

            $dispatchers[] = [
                'name' => $name,
                'called_listener_count' => \count($calledListeners),
                'not_called_listener_count' => \count($notCalledListeners),
                'orphaned_event_count' => \count($orphanedEvents),
                'called_listeners' => $calledListeners,
                'not_called_listeners' => $notCalledListeners,
                'orphaned_events' => $orphanedEvents,
            ];
        }

        return [
            'total_called_listener_count' => $totalCalled,
            'total_not_called_listener_count' => $totalNotCalled,
            'total_orphaned_event_count' => $totalOrphaned,
            'dispatchers' => $dispatchers,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof EventDataCollector);

        $data = $collector->getData();
        if (\is_object($data) && method_exists($data, 'getValue')) {
            $data = $data->getValue(true);
        }

        $rawDispatchers = $data['dispatchers'] ?? [];
        if (\is_object($rawDispatchers) && method_exists($rawDispatchers, 'getValue')) {
            $rawDispatchers = $rawDispatchers->getValue(true);
        }

        $totalCalled = 0;
        $totalNotCalled = 0;
        $totalOrphaned = 0;

        foreach ($rawDispatchers as $dispatcherData) {
            if (\is_object($dispatcherData) && method_exists($dispatcherData, 'getValue')) {
                $dispatcherData = $dispatcherData->getValue(true);
            }

            $called = $dispatcherData['called_listeners'] ?? [];
            if (\is_object($called) && method_exists($called, 'getValue')) {
                $called = $called->getValue(true);
            }

            $notCalled = $dispatcherData['not_called_listeners'] ?? [];
            if (\is_object($notCalled) && method_exists($notCalled, 'getValue')) {
                $notCalled = $notCalled->getValue(true);
            }

            $orphaned = $dispatcherData['orphaned_events'] ?? [];
            if (\is_object($orphaned) && method_exists($orphaned, 'getValue')) {
                $orphaned = $orphaned->getValue(true);
            }

            $totalCalled += \is_array($called) ? \count($called) : 0;
            $totalNotCalled += \is_array($notCalled) ? \count($notCalled) : 0;
            $totalOrphaned += \is_array($orphaned) ? \count($orphaned) : 0;
        }

        return [
            'total_called_listener_count' => $totalCalled,
            'total_not_called_listener_count' => $totalNotCalled,
            'total_orphaned_event_count' => $totalOrphaned,
            'dispatcher_count' => \is_array($rawDispatchers) ? \count($rawDispatchers) : 0,
        ];
    }

    /**
     * @param mixed $listeners
     *
     * @return list<array<string, mixed>>
     */
    private function extractListeners(mixed $listeners, bool $withTime): array
    {
        if (\is_object($listeners) && method_exists($listeners, 'getValue')) {
            $listeners = $listeners->getValue(true);
        }

        if (!\is_array($listeners)) {
            return [];
        }

        $result = [];
        foreach ($listeners as $listener) {
            if (\is_object($listener) && method_exists($listener, 'getValue')) {
                $listener = $listener->getValue(true);
            }

            if (!\is_array($listener)) {
                continue;
            }

            $entry = [
                'event' => $listener['event'] ?? null,
                'pretty' => $listener['pretty'] ?? null,
                'priority' => $listener['priority'] ?? null,
            ];

            if ($withTime) {
                $time = $listener['time'] ?? null;
                $entry['time_ms'] = null !== $time ? round((float) $time * 1000, 2) : null;
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param mixed $events
     *
     * @return string[]
     */
    private function extractOrphanedEvents(mixed $events): array
    {
        if (\is_object($events) && method_exists($events, 'getValue')) {
            $events = $events->getValue(true);
        }

        if (!\is_array($events)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $e): mixed => \is_string($e) ? $e : null,
            $events,
        )));
    }
}
