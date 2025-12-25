<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Bridge\Monolog\Capability;

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
        $this->fixturesDir = __DIR__.'/../../../Fixtures/logs';
        $parser = new LogParser();
        $reader = new LogReader($parser, $this->fixturesDir);
        $this->tool = new LogSearchTool($reader);
    }

    public function testSearchByTextTerm()
    {
        $results = $this->tool->search('logged in');

        $this->assertNotEmpty($results);
        $this->assertCount(1, $results);
        $this->assertStringContainsString('User logged in', $results[0]['message']);
    }

    public function testSearchByTextTermReturnsEmptyWhenNotFound()
    {
        $results = $this->tool->search('nonexistent search term xyz');

        $this->assertEmpty($results);
    }

    public function testSearchByLevel()
    {
        $results = $this->tool->search('', level: 'ERROR');

        $this->assertNotEmpty($results);

        foreach ($results as $entry) {
            $this->assertSame('ERROR', $entry['level']);
        }
    }

    public function testSearchByChannel()
    {
        $results = $this->tool->search('', channel: 'security');

        $this->assertNotEmpty($results);

        foreach ($results as $entry) {
            $this->assertSame('security', $entry['channel']);
        }
    }

    public function testSearchByEnvironment()
    {
        $results = $this->tool->search('', environment: 'prod');

        $this->assertNotEmpty($results);

        // All results should be from files containing 'prod' in their name
        foreach ($results as $entry) {
            $this->assertStringContainsString('prod', $entry['source_file']);
        }
    }

    public function testSearchWithLimit()
    {
        $results = $this->tool->search('', limit: 2);

        $this->assertLessThanOrEqual(2, \count($results));
    }

    public function testSearchRegex()
    {
        $results = $this->tool->searchRegex('Database.*failed');

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('Database connection failed', $results[0]['message']);
    }

    public function testSearchRegexWithDelimiters()
    {
        $results = $this->tool->searchRegex('/User.*logged/i');

        $this->assertNotEmpty($results);
    }

    public function testSearchRegexByLevel()
    {
        $results = $this->tool->searchRegex('.*', level: 'WARNING');

        $this->assertNotEmpty($results);

        foreach ($results as $entry) {
            $this->assertSame('WARNING', $entry['level']);
        }
    }

    public function testSearchContext()
    {
        $results = $this->tool->searchContext('user_id', '123');

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('user_id', $results[0]['context']);
        $this->assertSame(123, $results[0]['context']['user_id']);
    }

    public function testSearchContextReturnsEmptyWhenKeyNotFound()
    {
        $results = $this->tool->searchContext('nonexistent_key', 'value');

        $this->assertEmpty($results);
    }

    public function testSearchContextByLevel()
    {
        $results = $this->tool->searchContext('error', 'Connection', level: 'ERROR');

        $this->assertNotEmpty($results);
    }

    public function testTail()
    {
        $results = $this->tool->tail(10);

        $this->assertNotEmpty($results);
        $this->assertLessThanOrEqual(10, \count($results));
    }

    public function testTailWithLevel()
    {
        $results = $this->tool->tail(10, level: 'INFO');

        foreach ($results as $entry) {
            $this->assertSame('INFO', $entry['level']);
        }
    }

    public function testTailWithEnvironment()
    {
        $results = $this->tool->tail(10, environment: 'test');

        foreach ($results as $entry) {
            $this->assertStringContainsString('test', $entry['source_file']);
        }
    }

    public function testListFiles()
    {
        $results = $this->tool->listFiles();

        $this->assertNotEmpty($results);

        foreach ($results as $file) {
            $this->assertArrayHasKey('name', $file);
            $this->assertArrayHasKey('path', $file);
            $this->assertArrayHasKey('size', $file);
            $this->assertArrayHasKey('modified', $file);
        }
    }

    public function testListFilesForEnvironment()
    {
        $results = $this->tool->listFiles('dev');

        $this->assertNotEmpty($results);

        foreach ($results as $file) {
            $this->assertStringContainsString('dev', $file['name']);
        }
    }

    public function testListChannels()
    {
        $results = $this->tool->listChannels();

        $this->assertNotEmpty($results);
        $this->assertContains('app', $results);
        $this->assertContains('security', $results);
    }

    public function testByLevel()
    {
        $results = $this->tool->byLevel('INFO');

        $this->assertNotEmpty($results);

        foreach ($results as $entry) {
            $this->assertSame('INFO', $entry['level']);
        }
    }

    public function testByLevelWithEnvironment()
    {
        $results = $this->tool->byLevel('ERROR', environment: 'prod');

        $this->assertNotEmpty($results);

        foreach ($results as $entry) {
            $this->assertSame('ERROR', $entry['level']);
            $this->assertStringContainsString('prod', $entry['source_file']);
        }
    }

    public function testByLevelWithLimit()
    {
        $results = $this->tool->byLevel('INFO', limit: 1);

        $this->assertLessThanOrEqual(1, \count($results));
    }

    public function testSearchReturnsLogEntryArrayStructure()
    {
        $results = $this->tool->search('logged');

        $this->assertNotEmpty($results);

        $entry = $results[0];
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
