<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\EventCollectorFormatter;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class EventCollectorFormatterTest extends TestCase
{
    private EventCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new EventCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('events', $this->formatter->getName());
    }

    public function testFormatWithNoDispatchers()
    {
        $collector = $this->createMock(EventDataCollector::class);
        $collector->method('getData')->willReturn(['dispatchers' => []]);

        $result = $this->formatter->format($collector);

        $this->assertSame(0, $result['total_called_listener_count']);
        $this->assertSame(0, $result['total_not_called_listener_count']);
        $this->assertSame(0, $result['total_orphaned_event_count']);
        $this->assertSame([], $result['dispatchers']);
    }

    public function testFormatWithSingleDispatcher()
    {
        $collector = $this->createMock(EventDataCollector::class);
        $collector->method('getData')->willReturn([
            'dispatchers' => [
                'event_dispatcher' => [
                    'called_listeners' => [
                        ['event' => 'kernel.request', 'pretty' => 'MyListener::onRequest', 'priority' => 0, 'time' => 0.005],
                    ],
                    'not_called_listeners' => [
                        ['event' => 'kernel.exception', 'pretty' => 'MyListener::onException', 'priority' => 0],
                    ],
                    'orphaned_events' => ['app.unused_event'],
                ],
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['total_called_listener_count']);
        $this->assertSame(1, $result['total_not_called_listener_count']);
        $this->assertSame(1, $result['total_orphaned_event_count']);
        $this->assertCount(1, $result['dispatchers']);

        $dispatcher = $result['dispatchers'][0];
        $this->assertSame('event_dispatcher', $dispatcher['name']);
        $this->assertSame(1, $dispatcher['called_listener_count']);
        $this->assertSame(1, $dispatcher['not_called_listener_count']);
        $this->assertSame(1, $dispatcher['orphaned_event_count']);

        $calledListener = $dispatcher['called_listeners'][0];
        $this->assertSame('kernel.request', $calledListener['event']);
        $this->assertSame('MyListener::onRequest', $calledListener['pretty']);
        $this->assertSame(5.0, $calledListener['time_ms']);

        $notCalledListener = $dispatcher['not_called_listeners'][0];
        $this->assertSame('kernel.exception', $notCalledListener['event']);
        $this->assertArrayNotHasKey('time_ms', $notCalledListener);

        $this->assertSame(['app.unused_event'], $dispatcher['orphaned_events']);
    }

    public function testFormatSumsTotalsAcrossMultipleDispatchers()
    {
        $collector = $this->createMock(EventDataCollector::class);
        $collector->method('getData')->willReturn([
            'dispatchers' => [
                'dispatcher_a' => [
                    'called_listeners' => [
                        ['event' => 'foo', 'pretty' => 'A::foo', 'priority' => 0, 'time' => 0.001],
                    ],
                    'not_called_listeners' => [],
                    'orphaned_events' => [],
                ],
                'dispatcher_b' => [
                    'called_listeners' => [
                        ['event' => 'bar', 'pretty' => 'B::bar', 'priority' => 0, 'time' => 0.002],
                        ['event' => 'baz', 'pretty' => 'B::baz', 'priority' => 0, 'time' => null],
                    ],
                    'not_called_listeners' => [
                        ['event' => 'qux', 'pretty' => 'B::qux', 'priority' => 0],
                    ],
                    'orphaned_events' => ['x', 'y'],
                ],
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame(3, $result['total_called_listener_count']);
        $this->assertSame(1, $result['total_not_called_listener_count']);
        $this->assertSame(2, $result['total_orphaned_event_count']);
        $this->assertCount(2, $result['dispatchers']);

        $dispatcherB = $result['dispatchers'][1];
        $this->assertNull($dispatcherB['called_listeners'][1]['time_ms']);
    }

    public function testFormatHandlesDataObjects()
    {
        $orphanedData = $this->createMock(Data::class);
        $orphanedData->method('getValue')->with(true)->willReturn(['kernel.finish_request']);

        $notCalledData = $this->createMock(Data::class);
        $notCalledData->method('getValue')->with(true)->willReturn([]);

        $calledData = $this->createMock(Data::class);
        $calledData->method('getValue')->with(true)->willReturn([
            ['event' => 'kernel.response', 'pretty' => 'L::onResponse', 'priority' => 0, 'time' => 0.003],
        ]);

        $dispatcherInner = $this->createMock(Data::class);
        $dispatcherInner->method('getValue')->with(true)->willReturn([
            'called_listeners' => $calledData,
            'not_called_listeners' => $notCalledData,
            'orphaned_events' => $orphanedData,
        ]);

        $dispatchers = $this->createMock(Data::class);
        $dispatchers->method('getValue')->with(true)->willReturn([
            'main' => $dispatcherInner,
        ]);

        $rawData = $this->createMock(Data::class);
        $rawData->method('getValue')->with(true)->willReturn([
            'dispatchers' => $dispatchers,
        ]);

        $collector = $this->createMock(EventDataCollector::class);
        $collector->method('getData')->willReturn($rawData);

        $result = $this->formatter->format($collector);

        $this->assertSame(1, $result['total_called_listener_count']);
        $this->assertSame(0, $result['total_not_called_listener_count']);
        $this->assertSame(1, $result['total_orphaned_event_count']);
        $this->assertSame('kernel.finish_request', $result['dispatchers'][0]['orphaned_events'][0]);
        $this->assertSame(3.0, $result['dispatchers'][0]['called_listeners'][0]['time_ms']);
    }

    public function testGetSummaryCountsDispatchers()
    {
        $collector = $this->createMock(EventDataCollector::class);
        $collector->method('getData')->willReturn([
            'dispatchers' => [
                'a' => ['called_listeners' => [['event' => 'e1', 'pretty' => 'L::a', 'priority' => 0, 'time' => 0.0]], 'not_called_listeners' => [], 'orphaned_events' => []],
                'b' => ['called_listeners' => [], 'not_called_listeners' => [], 'orphaned_events' => ['x']],
            ],
        ]);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(1, $result['total_called_listener_count']);
        $this->assertSame(0, $result['total_not_called_listener_count']);
        $this->assertSame(1, $result['total_orphaned_event_count']);
        $this->assertSame(2, $result['dispatcher_count']);
        $this->assertArrayNotHasKey('dispatchers', $result);
    }
}
