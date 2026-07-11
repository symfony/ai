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
use Symfony\AI\Mate\Command\StopCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Antigravity
 */
final class StopCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/mate-stop-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testExecuteGracefullyHandlesMissingDirectory()
    {
        $command = new StopCommand($this->tempDir);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('No running servers found.', $output);
    }

    public function testExecuteGracefullyHandlesEmptyDirectory()
    {
        mkdir($this->tempDir, 0755, true);

        $command = new StopCommand($this->tempDir);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('No running servers found.', $output);
    }

    public function testExecuteFindsAndTerminatesPidFileProcess()
    {
        mkdir($this->tempDir, 0755, true);

        // Write a PID file with a dummy PID that doesn't exist
        // Note: posix_kill on a non-existent PID should be handled gracefully (warning suppressed)
        file_put_contents($this->tempDir.'/server_12345.pid', '12345');

        $command = new StopCommand($this->tempDir);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Verify PID file is deleted
        $this->assertFileDoesNotExist($this->tempDir.'/server_12345.pid');
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
