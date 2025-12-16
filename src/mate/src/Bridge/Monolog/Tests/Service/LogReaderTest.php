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
use Symfony\AI\Mate\Bridge\Monolog\Model\SearchCriteria;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogParser;
use Symfony\AI\Mate\Bridge\Monolog\Service\LogReader;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LogReaderTest extends TestCase
{
    private LogReader $reader;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = \dirname(__DIR__).'/Fixtures';
        $this->reader = new LogReader(new LogParser(), $this->fixturesDir);
    }

    public function testGetLogFiles()
    {
        $files = $this->reader->getLogFiles();

        $this->assertCount(2, $files);
        $this->assertContains($this->fixturesDir.'/sample.log', $files);
        $this->assertContains($this->fixturesDir.'/sample.json.log', $files);
    }

    public function testReadAll()
    {
        $entries = iterator_to_array($this->reader->readAll());

        // 6 entries in sample.log + 5 entries in sample.json.log = 11 total
        $this->assertCount(11, $entries);
    }

    public function testReadAllWithLimit()
    {
        $criteria = new SearchCriteria(limit: 5);
        $entries = iterator_to_array($this->reader->readAll($criteria));

        $this->assertCount(5, $entries);
    }

    public function testReadAllWithLevelFilter()
    {
        $criteria = new SearchCriteria(level: 'ERROR');
        $entries = iterator_to_array($this->reader->readAll($criteria));

        // 1 ERROR in sample.log + 1 ERROR in sample.json.log = 2 total
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertSame('ERROR', $entry->level);
        }
    }

    public function testReadAllWithChannelFilter()
    {
        $criteria = new SearchCriteria(channel: 'security');
        $entries = iterator_to_array($this->reader->readAll($criteria));

        // 1 in sample.log + 1 in sample.json.log = 2 total
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertSame('security', $entry->channel);
        }
    }

    public function testReadAllWithTermSearch()
    {
        $criteria = new SearchCriteria(term: 'database');
        $entries = iterator_to_array($this->reader->readAll($criteria));

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('Database', $entries[0]->message);
    }

    public function testReadFile()
    {
        $entries = iterator_to_array($this->reader->readFile($this->fixturesDir.'/sample.log'));

        $this->assertCount(6, $entries);
    }

    public function testTail()
    {
        $entries = $this->reader->tail(3);

        $this->assertCount(3, $entries);
    }

    public function testTailWithLevel()
    {
        $entries = $this->reader->tail(10, 'ERROR');

        // Only ERROR entries should be returned
        foreach ($entries as $entry) {
            $this->assertSame('ERROR', $entry->level);
        }
    }

    public function testGetChannels()
    {
        $channels = $this->reader->getUniqueChannels();

        $this->assertContains('app', $channels);
        $this->assertContains('security', $channels);
    }

    public function testGetLogFilesForNonExistentDirectory()
    {
        $reader = new LogReader(new LogParser(), '/non/existent/path');
        $files = $reader->getLogFiles();

        $this->assertSame([], $files);
    }
}
