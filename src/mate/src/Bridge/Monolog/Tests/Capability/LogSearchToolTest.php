<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Monolog\Tests\Capability;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Monolog\Capability\LogSearchTool;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogParser;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogReader;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LogSearchToolTest extends TestCase
{
    private string $fixturesDir;
    private LogSearchTool $tool;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__).'/Fixtures';
        $parser = new LogParser();
        $reader = new LogReader($parser, $this->fixturesDir);
        $this->tool = new LogSearchTool($reader);
    }

    public function testSearchByTextTerm()
    {
        $result = Toon::decode($this->tool->search('logged in'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);
        $this->assertCount(1, $result['entries']);
        $this->assertStringContainsString('User logged in', $result['entries'][0]['message']);
    }

    public function testSearchByTextTermReturnsEmptyWhenNotFound()
    {
        $result = Toon::decode($this->tool->search('nonexistent search term xyz'), DecodeOptions::lenient());

        $this->assertArrayHasKey('entries', $result);
        $this->assertEmpty($result['entries']);
    }

    public function testSearchByLevel()
    {
        $result = Toon::decode($this->tool->search('', level: 'ERROR'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);

        foreach ($result['entries'] as $entry) {
            $this->assertSame('ERROR', $entry['level']);
        }
    }

    public function testSearchByChannel()
    {
        $result = Toon::decode($this->tool->search('', channel: 'security'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);

        foreach ($result['entries'] as $entry) {
            $this->assertSame('security', $entry['channel']);
        }
    }

    public function testSearchWithLimit()
    {
        $result = Toon::decode($this->tool->search('', limit: 2));

        $this->assertArrayHasKey('entries', $result);
        $this->assertLessThanOrEqual(2, \count($result['entries']));
    }

    public function testSearchRegex()
    {
        $result = Toon::decode($this->tool->search('Database.*failed', regex: true));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);
        $this->assertStringContainsString('Database connection failed', $result['entries'][0]['message']);
    }

    public function testSearchRegexWithDelimiters()
    {
        $result = Toon::decode($this->tool->search('/User.*logged/i', regex: true));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);
    }

    public function testSearchRegexByLevel()
    {
        $result = Toon::decode($this->tool->search('.*', regex: true, level: 'WARNING'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);

        foreach ($result['entries'] as $entry) {
            $this->assertSame('WARNING', $entry['level']);
        }
    }

    public function testSearchContext()
    {
        $result = Toon::decode($this->tool->searchContext('user_id', '123'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);
        $this->assertArrayHasKey('user_id', $result['entries'][0]['context']);
        $this->assertSame(123, $result['entries'][0]['context']['user_id']);
    }

    public function testSearchContextReturnsEmptyWhenKeyNotFound()
    {
        $result = Toon::decode($this->tool->searchContext('nonexistent_key', 'value'), DecodeOptions::lenient());

        $this->assertArrayHasKey('entries', $result);
        $this->assertEmpty($result['entries']);
    }

    public function testSearchContextByLevel()
    {
        $result = Toon::decode($this->tool->searchContext('error', 'Connection', level: 'ERROR'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);
    }

    public function testTail()
    {
        $result = Toon::decode($this->tool->tail(10));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);
        $this->assertLessThanOrEqual(10, \count($result['entries']));
    }

    public function testTailWithLevel()
    {
        $result = Toon::decode($this->tool->tail(10, level: 'INFO'));

        $this->assertArrayHasKey('entries', $result);
        foreach ($result['entries'] as $entry) {
            $this->assertSame('INFO', $entry['level']);
        }
    }

    public function testTailWithChannel()
    {
        $result = Toon::decode($this->tool->tail(10, channel: 'security'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);
        foreach ($result['entries'] as $entry) {
            $this->assertSame('security', $entry['channel']);
        }
    }

    public function testListFiles()
    {
        $result = Toon::decode($this->tool->listFiles());

        $this->assertArrayHasKey('files', $result);
        $this->assertNotEmpty($result['files']);

        foreach ($result['files'] as $file) {
            $this->assertArrayHasKey('name', $file);
            $this->assertArrayHasKey('path', $file);
            $this->assertArrayHasKey('size', $file);
            $this->assertArrayHasKey('modified', $file);
        }
    }

    public function testListChannels()
    {
        $result = Toon::decode($this->tool->listChannels());

        $this->assertArrayHasKey('channels', $result);
        $this->assertNotEmpty($result['channels']);
        $this->assertContains('app', $result['channels']);
        $this->assertContains('security', $result['channels']);
    }

    public function testByLevel()
    {
        $result = Toon::decode($this->tool->search('', level: 'INFO'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);

        foreach ($result['entries'] as $entry) {
            $this->assertSame('INFO', $entry['level']);
        }
    }

    public function testByLevelWithLimit()
    {
        $result = Toon::decode($this->tool->search('', level: 'INFO', limit: 1));

        $this->assertArrayHasKey('entries', $result);
        $this->assertLessThanOrEqual(1, \count($result['entries']));
    }

    public function testSearchReturnsLogEntryArrayStructure()
    {
        $result = Toon::decode($this->tool->search('logged'));

        $this->assertArrayHasKey('entries', $result);
        $this->assertNotEmpty($result['entries']);

        $entry = $result['entries'][0];
        $this->assertArrayHasKey('datetime', $entry);
        $this->assertArrayHasKey('channel', $entry);
        $this->assertArrayHasKey('level', $entry);
        $this->assertArrayHasKey('message', $entry);
        $this->assertArrayHasKey('context', $entry);
        $this->assertArrayHasKey('extra', $entry);
        $this->assertArrayHasKey('source_file', $entry);
        $this->assertArrayHasKey('line_number', $entry);
    }
}
