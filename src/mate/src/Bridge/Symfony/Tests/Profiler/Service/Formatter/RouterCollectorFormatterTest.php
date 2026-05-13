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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\RouterCollectorFormatter;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class RouterCollectorFormatterTest extends TestCase
{
    private RouterCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new RouterCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('router', $this->formatter->getName());
    }

    public function testFormatWithNoRedirect()
    {
        $collector = $this->createMock(RouterDataCollector::class);
        $collector->method('getRedirect')->willReturn(false);
        $collector->method('getTargetUrl')->willReturn(null);
        $collector->method('getTargetRoute')->willReturn(null);

        $result = $this->formatter->format($collector);

        $this->assertFalse($result['redirect']);
        $this->assertNull($result['target_url']);
        $this->assertNull($result['target_route']);
    }

    public function testFormatWithRedirect()
    {
        $collector = $this->createMock(RouterDataCollector::class);
        $collector->method('getRedirect')->willReturn(true);
        $collector->method('getTargetUrl')->willReturn('/dashboard');
        $collector->method('getTargetRoute')->willReturn('app_dashboard');

        $result = $this->formatter->format($collector);

        $this->assertTrue($result['redirect']);
        $this->assertSame('/dashboard', $result['target_url']);
        $this->assertSame('app_dashboard', $result['target_route']);
    }

    public function testGetSummaryWithNoRedirect()
    {
        $collector = $this->createMock(RouterDataCollector::class);
        $collector->method('getRedirect')->willReturn(false);
        $collector->method('getTargetRoute')->willReturn(null);

        $result = $this->formatter->getSummary($collector);

        $this->assertFalse($result['redirect']);
        $this->assertNull($result['target_route']);
        $this->assertArrayNotHasKey('target_url', $result);
    }

    public function testGetSummaryWithRedirect()
    {
        $collector = $this->createMock(RouterDataCollector::class);
        $collector->method('getRedirect')->willReturn(true);
        $collector->method('getTargetRoute')->willReturn('app_login');

        $result = $this->formatter->getSummary($collector);

        $this->assertTrue($result['redirect']);
        $this->assertSame('app_login', $result['target_route']);
        $this->assertArrayNotHasKey('target_url', $result);
    }
}
