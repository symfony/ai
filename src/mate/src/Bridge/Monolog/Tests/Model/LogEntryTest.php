<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Monolog\Tests\Model;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Monolog\Model\LogEntry;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LogEntryTest extends TestCase
{
    public function testMatchesRegexMatches()
    {
        $entry = new LogEntry(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'), 'app', 'ERROR', 'Database connection failed');

        $this->assertTrue($entry->matchesRegex('/connection/i'));
        $this->assertFalse($entry->matchesRegex('/timeout/i'));
    }

    public function testMatchesRegexDoesNotHangOnPathologicalPattern()
    {
        $entry = new LogEntry(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'), 'app', 'ERROR', str_repeat('a', 41));

        $start = microtime(true);
        $result = $entry->matchesRegex('/^(a+)+$/');
        $elapsed = microtime(true) - $start;

        $this->assertFalse($result);
        $this->assertLessThan(1.0, $elapsed, 'ReDoS pattern must not stall preg_match');
    }
}
