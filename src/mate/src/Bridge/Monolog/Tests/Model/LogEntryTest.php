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

    public function testToArrayRedactsSensitiveContextAndExtra()
    {
        $entry = new LogEntry(
            new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            'security',
            'INFO',
            'Authentication attempt',
            [
                'user_id' => 7,
                'author' => 'jane',
                'password' => 'hunter2',
                'access_token' => 'tok-secret',
                'nested' => ['jwt' => 'jwt-secret', 'note' => 'visible'],
            ],
            ['session_id' => 'sess-secret', 'host' => 'web-1'],
        );

        $array = $entry->toArray();

        // Non-sensitive keys (incl. the over-matchable `author`/`host`) survive.
        $this->assertSame(7, $array['context']['user_id']);
        $this->assertSame('jane', $array['context']['author']);
        $this->assertSame('visible', $array['context']['nested']['note']);
        $this->assertSame('web-1', $array['extra']['host']);
        // Sensitive keys are redacted, recursively, in both context and extra.
        $this->assertSame('***REDACTED***', $array['context']['password']);
        $this->assertSame('***REDACTED***', $array['context']['access_token']);
        $this->assertSame('***REDACTED***', $array['context']['nested']['jwt']);
        $this->assertSame('***REDACTED***', $array['extra']['session_id']);

        $serialized = json_encode($array);
        $this->assertStringNotContainsString('hunter2', $serialized);
        $this->assertStringNotContainsString('tok-secret', $serialized);
        $this->assertStringNotContainsString('jwt-secret', $serialized);
        $this->assertStringNotContainsString('sess-secret', $serialized);
    }

    public function testContextRemainsSearchableDespiteRedaction()
    {
        $entry = new LogEntry(
            new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            'security',
            'INFO',
            'token issued',
            ['access_token' => 'tok-secret'],
        );

        // Searching still matches the real (unredacted) value...
        $this->assertTrue($entry->hasContextValue('access_token', 'tok-secret'));
        // ...but the AI-facing output is redacted.
        $this->assertSame('***REDACTED***', $entry->toArray()['context']['access_token']);
    }
}
