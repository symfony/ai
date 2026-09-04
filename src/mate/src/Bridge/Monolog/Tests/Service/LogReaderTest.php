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

use PHPUnit\Framework\Attributes\DataProvider;
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
        // A checkout gives both fixtures the same mtime, so which one is "newest" would
        // otherwise depend on LogReader's tie-break rule rather than this test's intent.
        touch($this->fixturesDir.'/sample.json.log', time() + 60);

        $result = $this->reader->tail(3);

        // tail() reads only the newest file of the context (sample.json.log, 5 entries),
        // not every file readAll() would merge.
        $this->assertCount(3, $result['entries']);
        $this->assertSame(5, $result['total_matched']);
        $this->assertTrue($result['truncated']);
    }

    public function testTailWithLevel()
    {
        $entries = $this->reader->tail(10, 'ERROR')['entries'];

        // Only ERROR entries should be returned
        foreach ($entries as $entry) {
            $this->assertSame('ERROR', $entry->getLevel());
        }
    }

    public function testTailReportsTruncationWhenMatchesExceedLimit()
    {
        $tempDir = sys_get_temp_dir().'/mate-log-reader-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $lines = [];
        for ($i = 1; $i <= 30; ++$i) {
            $lines[] = \sprintf('[2024-01-01 00:%02d:00] app.ERROR: Error number %d [] []', $i, $i);
        }
        file_put_contents($tempDir.'/app.log', implode("\n", $lines)."\n");

        try {
            $reader = new LogReader(new LogParser(), $tempDir);

            $result = $reader->tail(10, 'ERROR');

            $this->assertCount(10, $result['entries']);
            $this->assertSame(30, $result['total_matched']);
            $this->assertTrue($result['truncated']);
        } finally {
            $this->removeDirectory($tempDir);
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

        $entries = $reader->tail(3)['entries'];

        // Both contexts are tailed, merged and sorted by time: the admin fixtures are the most recent ones
        $this->assertCount(3, $entries);
        $this->assertSame('website', $entries[0]->getKernelContext());
        $this->assertSame('admin', $entries[1]->getKernelContext());
        $this->assertSame('admin', $entries[2]->getKernelContext());
    }

    public function testTailFiltersByKernelContext()
    {
        $reader = $this->createMultiKernelReader();

        $entries = $reader->tail(10, kernelContext: 'admin')['entries'];

        $this->assertCount(2, $entries);
        foreach ($entries as $entry) {
            $this->assertSame('admin', $entry->getKernelContext());
        }
    }

    public function testTailFindsMatchesEarlierInTheFileThanTheRawLineWindow()
    {
        $tempDir = sys_get_temp_dir().'/mate-log-reader-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $lines = [];
        for ($i = 1; $i <= 20; ++$i) {
            $lines[] = \sprintf('[2024-01-01 00:%02d:00] app.ERROR: Error number %d [] []', $i, $i);
        }
        for ($i = 1; $i <= 500; ++$i) {
            $lines[] = \sprintf('[2024-01-01 01:%02d:%02d] app.INFO: Info number %d [] []', intdiv($i, 60), $i % 60, $i);
        }
        file_put_contents($tempDir.'/app.log', implode("\n", $lines)."\n");

        try {
            $reader = new LogReader(new LogParser(), $tempDir);

            // A raw-line window of 2*limit=100 lines, counted from the end of the file,
            // never reaches the 20 ERROR lines that sit behind 500 later INFO lines.
            $result = $reader->tail(50, 'ERROR');
            $entries = $result['entries'];

            $this->assertCount(20, $entries, 'all 20 ERROR entries must be found even though 500 INFO lines follow them');
            $this->assertSame('Error number 1', $entries[0]->getMessage());
            $this->assertSame('Error number 20', $entries[19]->getMessage());
            $this->assertSame(20, $result['total_matched']);
            $this->assertFalse($result['truncated']);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testTailKeepsTheMostRecentMatchesWhenMatchesExceedLimit()
    {
        $tempDir = sys_get_temp_dir().'/mate-log-reader-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $lines = [];
        for ($i = 1; $i <= 30; ++$i) {
            $lines[] = \sprintf('[2024-01-01 00:%02d:00] app.ERROR: Error number %d [] []', $i, $i);
        }
        file_put_contents($tempDir.'/app.log', implode("\n", $lines)."\n");

        try {
            $reader = new LogReader(new LogParser(), $tempDir);

            $entries = $reader->tail(10, 'ERROR')['entries'];

            $this->assertCount(10, $entries);
            $this->assertSame('Error number 21', $entries[0]->getMessage());
            $this->assertSame('Error number 30', $entries[9]->getMessage());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testGetKernelContextDoesNotMatchSiblingDirectoryWithSharedPrefix()
    {
        $tempDir = sys_get_temp_dir().'/mate-log-reader-test-'.uniqid();
        mkdir($tempDir.'/web', 0755, true);
        mkdir($tempDir.'/website', 0755, true);
        file_put_contents($tempDir.'/website/dev.log', "[2024-01-01T00:00:00+00:00] app.INFO: Test message [] []\n");

        try {
            $reader = new LogReader(new LogParser(), ['web' => $tempDir.'/web']);

            $this->assertNull($reader->getKernelContext($tempDir.'/website/dev.log'));

            $entries = iterator_to_array($reader->readFile($tempDir.'/website/dev.log'));

            $this->assertCount(1, $entries);
            $this->assertNull($entries[0]->getKernelContext());
            $this->assertSame('dev.log', $entries[0]->getSourceFile());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMtimeTieRotatedNames(): iterable
    {
        yield 'numeric suffix' => ['app.1.log'];
        yield 'date suffix' => ['app-2024-01-01.log'];
    }

    #[DataProvider('provideMtimeTieRotatedNames')]
    public function testGetLogFilesIsDeterministicOnMtimeTie(string $rotated)
    {
        $tempDir = sys_get_temp_dir().'/mate-log-reader-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $tie = time();

            // glob() already lists both names alphabetically before any sort runs, and the
            // rotated name is always the lexically smaller one, so this reproduces the tie
            // with the "wrong" file first regardless of the order touch() runs in here.
            touch($tempDir.'/'.$rotated, $tie);
            touch($tempDir.'/app.log', $tie);

            $reader = new LogReader(new LogParser(), $tempDir);

            $this->assertSame($tempDir.'/app.log', $reader->getLogFiles()[0]);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function createMultiKernelReader(): LogReader
    {
        return new LogReader(new LogParser(), [
            'website' => $this->fixturesDir,
            'admin' => $this->fixturesDir.'/logs',
        ]);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
