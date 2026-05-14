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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\TimeCollectorFormatter;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TimeCollectorFormatterTest extends TestCase
{
    private TimeCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TimeCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('time', $this->formatter->getName());
    }

    public function testFormat()
    {
        $event = $this->createMock(StopwatchEvent::class);
        $event->method('getCategory')->willReturn('section');
        $event->method('getDuration')->willReturn(42.5);
        $event->method('getMemory')->willReturn(1024 * 1024);

        $collector = $this->createMock(TimeDataCollector::class);
        $collector->method('getDuration')->willReturn(120.0);
        $collector->method('getInitTime')->willReturn(15.3);
        $collector->method('isStopwatchInstalled')->willReturn(true);
        $collector->method('getEvents')->willReturn(['kernel.handle' => $event]);

        $result = $this->formatter->format($collector);

        $this->assertSame(120.0, $result['duration_ms']);
        $this->assertSame(15.3, $result['init_time_ms']);
        $this->assertTrue($result['stopwatch_installed']);
        $this->assertCount(1, $result['events']);
        $this->assertSame('kernel.handle', $result['events'][0]['name']);
        $this->assertSame('section', $result['events'][0]['category']);
        $this->assertSame(42.5, $result['events'][0]['duration_ms']);
        $this->assertSame(1024 * 1024, $result['events'][0]['memory']);
    }

    public function testFormatExcludesSectionWrapper()
    {
        $sectionEvent = $this->createMock(StopwatchEvent::class);
        $sectionEvent->method('getCategory')->willReturn('default');
        $sectionEvent->method('getDuration')->willReturn(200.0);
        $sectionEvent->method('getMemory')->willReturn(0);

        $realEvent = $this->createMock(StopwatchEvent::class);
        $realEvent->method('getCategory')->willReturn('section');
        $realEvent->method('getDuration')->willReturn(50.0);
        $realEvent->method('getMemory')->willReturn(0);

        $collector = $this->createMock(TimeDataCollector::class);
        $collector->method('getDuration')->willReturn(200.0);
        $collector->method('getInitTime')->willReturn(5.0);
        $collector->method('isStopwatchInstalled')->willReturn(true);
        $collector->method('getEvents')->willReturn([
            '__section__' => $sectionEvent,
            'kernel.handle' => $realEvent,
        ]);

        $result = $this->formatter->format($collector);

        $this->assertCount(1, $result['events']);
        $this->assertSame('kernel.handle', $result['events'][0]['name']);
    }

    public function testFormatSortsEventsByDurationDescending()
    {
        $fastEvent = $this->createMock(StopwatchEvent::class);
        $fastEvent->method('getCategory')->willReturn('section');
        $fastEvent->method('getDuration')->willReturn(10.0);
        $fastEvent->method('getMemory')->willReturn(0);

        $slowEvent = $this->createMock(StopwatchEvent::class);
        $slowEvent->method('getCategory')->willReturn('section');
        $slowEvent->method('getDuration')->willReturn(80.0);
        $slowEvent->method('getMemory')->willReturn(0);

        $collector = $this->createMock(TimeDataCollector::class);
        $collector->method('getDuration')->willReturn(100.0);
        $collector->method('getInitTime')->willReturn(10.0);
        $collector->method('isStopwatchInstalled')->willReturn(true);
        $collector->method('getEvents')->willReturn([
            'kernel.response' => $fastEvent,
            'kernel.handle' => $slowEvent,
        ]);

        $result = $this->formatter->format($collector);

        $this->assertSame('kernel.handle', $result['events'][0]['name']);
        $this->assertSame('kernel.response', $result['events'][1]['name']);
    }

    public function testFormatWithNoEvents()
    {
        $collector = $this->createMock(TimeDataCollector::class);
        $collector->method('getDuration')->willReturn(0.0);
        $collector->method('getInitTime')->willReturn(0.0);
        $collector->method('isStopwatchInstalled')->willReturn(false);
        $collector->method('getEvents')->willReturn([]);

        $result = $this->formatter->format($collector);

        $this->assertSame(0.0, $result['duration_ms']);
        $this->assertFalse($result['stopwatch_installed']);
        $this->assertSame([], $result['events']);
    }

    public function testGetSummary()
    {
        $collector = $this->createMock(TimeDataCollector::class);
        $collector->method('getDuration')->willReturn(98.76);
        $collector->method('getInitTime')->willReturn(12.34);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(98.76, $result['duration_ms']);
        $this->assertSame(12.34, $result['init_time_ms']);
        $this->assertArrayNotHasKey('events', $result);
        $this->assertArrayNotHasKey('stopwatch_installed', $result);
    }
}
