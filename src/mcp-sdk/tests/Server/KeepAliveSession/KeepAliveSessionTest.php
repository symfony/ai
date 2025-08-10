<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Tests\Server\KeepAliveSession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Server\KeepAliveSession\KeepAliveSession;
use Symfony\Component\Clock\MockClock;

#[Small]
#[CoversClass(KeepAliveSession::class)]
class KeepAliveSessionTest extends TestCase
{
    #[TestDox('Does not call the callback before start or before interval elapsed')]
    public function testNoTickBeforeStartOrInterval()
    {
        $session = new KeepAliveSession(new MockClock(), new \DateInterval('PT1S'));

        $called = 0;
        $callback = function () use (&$called): void { ++$called; };

        // Not started yet
        $session->tick($callback);
        $this->assertSame(0, $called);

        // Start but before interval
        $session->start();
        $session->tick($callback);
        $this->assertSame(0, $called);
    }

    #[TestDox('Calls the callback after the interval has elapsed once started')]
    public function testTickAfterInterval()
    {
        $clock = new MockClock();
        $session = new KeepAliveSession($clock, new \DateInterval('PT1S'));

        $called = 0;
        $callback = function () use (&$called): void { ++$called; };

        $session->start();
        $clock->sleep(1.001);
        $session->tick($callback);

        $this->assertSame(1, $called);
    }

    #[TestDox('Reschedules correctly and calls the callback on subsequent intervals')]
    public function testReschedulesAndTicksMultipleTimes()
    {
        $clock = new MockClock();
        $session = new KeepAliveSession($clock, new \DateInterval('PT1S'));

        $called = 0;
        $callback = function () use (&$called): void { ++$called; };

        $session->start();

        $clock->sleep(1.01);
        $session->tick($callback);

        $clock->sleep(1.01);
        $session->tick($callback);

        $this->assertSame(2, $called);
    }

    #[TestDox('Does not call the callback after stop()')]
    public function testNoTickAfterStop()
    {
        $clock = new MockClock();
        $session = new KeepAliveSession($clock, new \DateInterval('PT1S'));

        $called = 0;
        $callback = function () use (&$called): void { ++$called; };

        $session->start();
        $clock->sleep(1.01);
        $session->stop();
        $session->tick($callback);

        $this->assertSame(0, $called);
    }
}
