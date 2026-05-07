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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\LoggerCollectorFormatter;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LoggerCollectorFormatterTest extends TestCase
{
    private LoggerCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new LoggerCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('logger', $this->formatter->getName());
    }

    public function testGetSummary()
    {
        $collector = $this->createMock(LoggerDataCollector::class);
        $collector->method('countErrors')->willReturn(2);
        $collector->method('countWarnings')->willReturn(3);
        $collector->method('countDeprecations')->willReturn(1);
        $collector->method('countScreams')->willReturn(0);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(2, $result['error_count']);
        $this->assertSame(3, $result['warning_count']);
        $this->assertSame(1, $result['deprecation_count']);
        $this->assertSame(0, $result['scream_count']);
        $this->assertArrayNotHasKey('logs', $result);
    }

    public function testFormatWithNoLogs()
    {
        $collector = $this->createMock(LoggerDataCollector::class);
        $collector->method('countErrors')->willReturn(0);
        $collector->method('countWarnings')->willReturn(0);
        $collector->method('countDeprecations')->willReturn(0);
        $collector->method('countScreams')->willReturn(0);
        $collector->method('getProcessedLogs')->willReturn([]);

        $result = $this->formatter->format($collector);

        $this->assertSame(0, $result['error_count']);
        $this->assertSame(0, $result['warning_count']);
        $this->assertSame(0, $result['deprecation_count']);
        $this->assertSame(0, $result['scream_count']);
        $this->assertSame([], $result['logs']);
        $this->assertFalse($result['logs_truncated']);
    }

    public function testFormatExtractsDataObjects()
    {
        $message = $this->createMock(Data::class);
        $message->method('getValue')->with()->willReturn('Something went wrong');

        $context = $this->createMock(Data::class);
        $context->method('getValue')->with(true)->willReturn(['key' => 'value']);

        $collector = $this->createMock(LoggerDataCollector::class);
        $collector->method('countErrors')->willReturn(1);
        $collector->method('countWarnings')->willReturn(0);
        $collector->method('countDeprecations')->willReturn(0);
        $collector->method('countScreams')->willReturn(0);
        $collector->method('getProcessedLogs')->willReturn([
            [
                'type' => 'error',
                'timestamp' => 1234567890.0,
                'priority' => 400,
                'priorityName' => 'ERROR',
                'channel' => 'app',
                'message' => $message,
                'context' => $context,
                'errorCount' => 1,
            ],
        ]);

        $result = $this->formatter->format($collector);

        $this->assertCount(1, $result['logs']);
        $log = $result['logs'][0];
        $this->assertSame('error', $log['type']);
        $this->assertSame('ERROR', $log['priority_name']);
        $this->assertSame('app', $log['channel']);
        $this->assertSame('Something went wrong', $log['message']);
        $this->assertSame(['key' => 'value'], $log['context']);
        $this->assertFalse($result['logs_truncated']);
    }

    public function testFormatTruncatesAt100Logs()
    {
        $logs = [];
        for ($i = 0; $i < 101; ++$i) {
            $logs[] = [
                'type' => 'debug',
                'timestamp' => 1234567890.0,
                'priority' => 100,
                'priorityName' => 'DEBUG',
                'channel' => 'app',
                'message' => 'msg '.$i,
                'context' => [],
                'errorCount' => 0,
            ];
        }

        $collector = $this->createMock(LoggerDataCollector::class);
        $collector->method('countErrors')->willReturn(0);
        $collector->method('countWarnings')->willReturn(0);
        $collector->method('countDeprecations')->willReturn(0);
        $collector->method('countScreams')->willReturn(0);
        $collector->method('getProcessedLogs')->willReturn($logs);

        $result = $this->formatter->format($collector);

        $this->assertCount(100, $result['logs']);
        $this->assertTrue($result['logs_truncated']);
    }
}
