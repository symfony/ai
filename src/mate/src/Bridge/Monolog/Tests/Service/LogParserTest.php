<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Monolog\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogParser;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LogParserTest extends TestCase
{
    private LogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LogParser();
    }

    public function testParseLineFormat()
    {
        $line = '[2024-01-15 10:30:45] app.ERROR: Database connection failed {"exception":"PDOException"} {"retry":3}';

        $entry = $this->parser->parse($line);

        $this->assertNotNull($entry);
        $this->assertSame('2024-01-15', $entry->getDatetime()->format('Y-m-d'));
        $this->assertSame('10:30:45', $entry->getDatetime()->format('H:i:s'));
        $this->assertSame('app', $entry->getChannel());
        $this->assertSame('ERROR', $entry->getLevel());
        $this->assertSame('Database connection failed', $entry->getMessage());
        $this->assertSame(['exception' => 'PDOException'], $entry->getContext());
        $this->assertSame(['retry' => 3], $entry->getExtra());
    }

    public function testParseLineFormatWithoutContext()
    {
        $line = '[2024-01-15 10:30:45] app.INFO: Simple message [] []';

        $entry = $this->parser->parse($line);

        $this->assertNotNull($entry);
        $this->assertSame('app', $entry->getChannel());
        $this->assertSame('INFO', $entry->getLevel());
        $this->assertSame('Simple message', $entry->getMessage());
        $this->assertSame([], $entry->getContext());
        $this->assertSame([], $entry->getExtra());
    }

    public function testParseJsonFormat()
    {
        $line = '{"datetime":"2024-01-15T11:00:00+00:00","channel":"app","level":"INFO","message":"Test message","context":{"key":"value"},"extra":{}}';

        $entry = $this->parser->parse($line);

        $this->assertNotNull($entry);
        $this->assertSame('2024-01-15', $entry->getDatetime()->format('Y-m-d'));
        $this->assertSame('app', $entry->getChannel());
        $this->assertSame('INFO', $entry->getLevel());
        $this->assertSame('Test message', $entry->getMessage());
        $this->assertSame(['key' => 'value'], $entry->getContext());
        $this->assertSame([], $entry->getExtra());
    }

    public function testParseJsonFormatWithNumericLevel()
    {
        $line = '{"datetime":"2024-01-15T11:00:00+00:00","channel":"app","level":400,"message":"Error occurred","context":{},"extra":{}}';

        $entry = $this->parser->parse($line);

        $this->assertNotNull($entry);
        $this->assertSame('ERROR', $entry->getLevel());
    }

    public function testParseEmptyLine()
    {
        $entry = $this->parser->parse('');

        $this->assertNull($entry);
    }

    public function testParseInvalidLine()
    {
        $entry = $this->parser->parse('This is not a valid log line');

        $this->assertNull($entry);
    }

    public function testParseInvalidJson()
    {
        $entry = $this->parser->parse('{invalid json}');

        $this->assertNull($entry);
    }

    public function testParseWithSourceFileAndLineNumber()
    {
        $line = '[2024-01-15 10:30:45] app.INFO: Test message [] []';

        $entry = $this->parser->parse($line, 'dev.log', 42);

        $this->assertNotNull($entry);
        $this->assertSame('dev.log', $entry->getSourceFile());
        $this->assertSame(42, $entry->getLineNumber());
    }

    public function testParseLineFormatWithTimezone()
    {
        $line = '[2024-01-15T10:30:45+01:00] app.INFO: Message with timezone [] []';

        $entry = $this->parser->parse($line);

        $this->assertNotNull($entry);
        $this->assertSame('app', $entry->getChannel());
        $this->assertSame('INFO', $entry->getLevel());
    }

    public function testParseLineFormatWithMilliseconds()
    {
        $line = '[2024-01-15 10:30:45.123456] app.DEBUG: Message with microseconds [] []';

        $entry = $this->parser->parse($line);

        $this->assertNotNull($entry);
        $this->assertSame('DEBUG', $entry->getLevel());
    }
}
