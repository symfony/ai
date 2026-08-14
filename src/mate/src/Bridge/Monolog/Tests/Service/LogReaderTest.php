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
            $this->assertSame('ERROR', $entry->getLevel());
        }
    }

    public function testReadAllWithChannelFilter()
    {
        $criteria = new SearchCriteria(channel: 'security');
        $entries = iterator_to_array($this->reader->readAll($criteria));

        // 1 in sample.log + 1 in sample.json.log = 2 total
        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertSame('security', $entry->getChannel());
        }
    }

    public function testReadAllWithTermSearch()
    {
        $criteria = new SearchCriteria(term: 'database');
        $entries = iterator_to_array($this->reader->readAll($criteria));

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('Database', $entries[0]->getMessage());
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
            $this->assertSame('ERROR', $entry->getLevel());
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

    public function testSingleLogDirectoryDoesNotStampKernelContext()
    {
        $entries = iterator_to_array($this->reader->readAll());

        foreach ($entries as $entry) {
            $this->assertNull($entry->getKernelContext());
        }
    }

    public function testSupportsMultipleLogDirectories()
    {
        $reader = $this->createMultiKernelReader();

        $files = $reader->getLogFiles();

        // 2 files in the fixtures directory + 2 files in the nested logs directory
        $this->assertCount(4, $files);
        $this->assertContains($this->fixturesDir.'/sample.log', $files);
        $this->assertContains($this->fixturesDir.'/logs/prod.log', $files);
    }

    public function testGetLogFilesFiltersByKernelContext()
    {
        $reader = $this->createMultiKernelReader();

        $files = $reader->getLogFiles('admin');

        $this->assertCount(2, $files);
        $this->assertContains($this->fixturesDir.'/logs/prod.log', $files);
        $this->assertContains($this->fixturesDir.'/logs/app_test.log', $files);
    }

    public function testGetKernelContextResolvesTheMostSpecificDirectory()
    {
        $reader = $this->createMultiKernelReader();

        $this->assertSame('website', $reader->getKernelContext($this->fixturesDir.'/sample.log'));
        $this->assertSame('admin', $reader->getKernelContext($this->fixturesDir.'/logs/prod.log'));
        $this->assertNull($reader->getKernelContext('/somewhere/else/dev.log'));
    }

    public function testReadAllStampsKernelContextOnEntries()
    {
        $reader = $this->createMultiKernelReader();

        $entries = iterator_to_array($reader->readAll());

        // 11 entries in the fixtures directory + 4 entries in the nested logs directory
        $this->assertCount(15, $entries);

        $contexts = [];
        foreach ($entries as $entry) {
            $contexts[$entry->getKernelContext()] = true;
        }

        $this->assertArrayHasKey('website', $contexts);
        $this->assertArrayHasKey('admin', $contexts);
        $this->assertCount(2, $contexts);
    }

    public function testReadAllFiltersByKernelContext()
    {
        $reader = $this->createMultiKernelReader();

        $entries = iterator_to_array($reader->readAll(null, 'admin'));

        $this->assertCount(4, $entries);
        foreach ($entries as $entry) {
            $this->assertSame('admin', $entry->getKernelContext());
        }
    }

    public function testGetUniqueChannelsFiltersByKernelContext()
    {
        $reader = $this->createMultiKernelReader();

        $channels = $reader->getUniqueChannels('admin');

        $this->assertContains('test', $channels);
        $this->assertNotContains('security', $channels);
    }

    public function testTailMergesTheNewestFileOfEveryKernelContext()
    {
        $reader = $this->createMultiKernelReader();

        $entries = $reader->tail(3);

        // Both contexts are tailed, merged and sorted by time: the admin fixtures are the most recent ones
        $this->assertCount(3, $entries);
        $this->assertSame('website', $entries[0]->getKernelContext());
        $this->assertSame('admin', $entries[1]->getKernelContext());
        $this->assertSame('admin', $entries[2]->getKernelContext());
    }

    public function testTailFiltersByKernelContext()
    {
        $reader = $this->createMultiKernelReader();

        $entries = $reader->tail(10, kernelContext: 'admin');

        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertSame('admin', $entry->getKernelContext());
        }
    }

    private function createMultiKernelReader(): LogReader
    {
        return new LogReader(new LogParser(), [
            'website' => $this->fixturesDir,
            'admin' => $this->fixturesDir.'/logs',
        ]);
    }
}
