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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\MemoryCollectorFormatter;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MemoryCollectorFormatterTest extends TestCase
{
    private MemoryCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MemoryCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('memory', $this->formatter->getName());
    }

    public function testFormat()
    {
        $collector = $this->createMock(MemoryDataCollector::class);
        $collector->method('getMemory')->willReturn(16 * 1024 * 1024); // 16 MB
        $collector->method('getMemoryLimit')->willReturn(128 * 1024 * 1024); // 128 MB

        $result = $this->formatter->format($collector);

        $this->assertSame(16 * 1024 * 1024, $result['memory_bytes']);
        $this->assertSame(16.0, $result['memory_mb']);
        $this->assertSame(128 * 1024 * 1024, $result['memory_limit_bytes']);
        $this->assertSame(128.0, $result['memory_limit_mb']);
        $this->assertSame(12.5, $result['usage_percent']);
    }

    public function testFormatWithUnlimitedMemory()
    {
        $collector = $this->createMock(MemoryDataCollector::class);
        $collector->method('getMemory')->willReturn(8 * 1024 * 1024); // 8 MB
        $collector->method('getMemoryLimit')->willReturn(-1);

        $result = $this->formatter->format($collector);

        $this->assertSame(8 * 1024 * 1024, $result['memory_bytes']);
        $this->assertSame(8.0, $result['memory_mb']);
        $this->assertSame(-1, $result['memory_limit_bytes']);
        $this->assertSame('unlimited', $result['memory_limit_mb']);
        $this->assertNull($result['usage_percent']);
    }

    public function testGetSummary()
    {
        $collector = $this->createMock(MemoryDataCollector::class);
        $collector->method('getMemory')->willReturn(32 * 1024 * 1024); // 32 MB
        $collector->method('getMemoryLimit')->willReturn(256 * 1024 * 1024); // 256 MB

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(32.0, $result['memory_mb']);
        $this->assertSame(256.0, $result['memory_limit_mb']);
        $this->assertSame(12.5, $result['usage_percent']);
        $this->assertArrayNotHasKey('memory_bytes', $result);
        $this->assertArrayNotHasKey('memory_limit_bytes', $result);
    }

    public function testGetSummaryWithUnlimitedMemory()
    {
        $collector = $this->createMock(MemoryDataCollector::class);
        $collector->method('getMemory')->willReturn(4 * 1024 * 1024); // 4 MB
        $collector->method('getMemoryLimit')->willReturn(-1);

        $result = $this->formatter->getSummary($collector);

        $this->assertSame(4.0, $result['memory_mb']);
        $this->assertSame('unlimited', $result['memory_limit_mb']);
        $this->assertNull($result['usage_percent']);
    }
}
