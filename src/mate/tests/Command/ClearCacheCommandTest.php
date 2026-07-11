<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Command\ClearCacheCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Antigravity
 */
final class ClearCacheCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/mate-cache-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testExecuteClearsCacheFilesAndDirectories()
    {
        mkdir($this->tempDir, 0755, true);
        mkdir($this->tempDir.'/sessions', 0755, true);
        file_put_contents($this->tempDir.'/file1.txt', 'content1');
        file_put_contents($this->tempDir.'/sessions/session1.txt', 'session_content');

        $command = new ClearCacheCommand($this->tempDir);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Successfully cleared 2 cache files', $output);
        $this->assertFileDoesNotExist($this->tempDir.'/file1.txt');
        $this->assertDirectoryDoesNotExist($this->tempDir.'/sessions');
        $this->assertDirectoryExists($this->tempDir);
    }

    public function testExecuteGracefullyHandlesMissingDirectory()
    {
        $command = new ClearCacheCommand($this->tempDir);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Cache directory does not exist. Nothing to clear.', $output);
    }

    public function testExecuteHandlesEmptyDirectory()
    {
        mkdir($this->tempDir, 0755, true);

        $command = new ClearCacheCommand($this->tempDir);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Cache directory is already empty.', $output);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
