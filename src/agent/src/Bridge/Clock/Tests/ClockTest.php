<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Clock\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\Component\Clock\MockClock;

class ClockTest extends TestCase
{
    public function testInvokeReturnsCurrentDateTime()
    {
        $frozen = new MockClock('2024-06-01 12:00:00', new \DateTimeZone('UTC'));
        $clock = new Clock($frozen);
        $output = $clock();

        $this->assertSame('Current date is 2024-06-01 (YYYY-MM-DD) and the time is 12:00:00 (HH:MM:SS).', $output);
    }

    public function testInvokeWithCustomTimezone()
    {
        $frozen = new MockClock('2024-06-01 12:00:00', new \DateTimeZone('UTC'));
        $clock = new Clock($frozen, 'America/New_York');
        $output = $clock();

        $this->assertSame('Current date is 2024-06-01 (YYYY-MM-DD) and the time is 08:00:00 (HH:MM:SS).', $output);
    }
}
