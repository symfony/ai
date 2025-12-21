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
use Symfony\AI\Mate\Command\InitCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class InitCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/mate-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testCreatesDirectoryAndConfigFile()
    {
        $command = new InitCommand($this->tempDir);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->tempDir.'/.mate');
        $this->assertFileExists($this->tempDir.'/.mate/bridges.php');
        $this->assertFileExists($this->tempDir.'/.mate/config.php');
        $this->assertFileExists($this->tempDir.'/.mate/.env');
        $this->assertFileExists($this->tempDir.'/mcp.json');
        $this->assertTrue(is_link($this->tempDir.'/.mcp.json'));
        $this->assertSame('mcp.json', readlink($this->tempDir.'/.mcp.json'));

        $content = file_get_contents($this->tempDir.'/.mate/bridges.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('mate discover', $content);
        $this->assertStringContainsString('enabled', $content);
    }

    public function testDisplaysSuccessMessage()
    {
        $command = new InitCommand($this->tempDir);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('AI Mate Initialization', $output);
        $this->assertStringContainsString('bridges.php', $output);
        $this->assertStringContainsString('config.php', $output);
        $this->assertStringContainsString('vendor/bin/mate discover', $output);
        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('Created', $output);
    }

    public function testDoesNotOverwriteExistingFileWithoutConfirmation()
    {
        $command = new InitCommand($this->tempDir);
        $tester = new CommandTester($command);

        // Create existing file
        mkdir($this->tempDir.'/.mate', 0755, true);
        file_put_contents($this->tempDir.'/.mate/bridges.php', '<?php return ["test" => "value"];');

        // Execute with 'no' response (twice for both files)
        $tester->setInputs(['no', 'no']);
        $tester->execute([]);

        // File should still contain original content
        $content = file_get_contents($this->tempDir.'/.mate/bridges.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('value', $content);
    }

    public function testOverwritesExistingFileWithConfirmation()
    {
        $command = new InitCommand($this->tempDir);
        $tester = new CommandTester($command);

        // Create existing file
        mkdir($this->tempDir.'/.mate', 0755, true);
        file_put_contents($this->tempDir.'/.mate/bridges.php', '<?php return ["test" => "value"];');

        // Execute with 'yes' response (twice for both files)
        $tester->setInputs(['yes', 'yes']);
        $tester->execute([]);

        // File should be overwritten with template content
        $content = file_get_contents($this->tempDir.'/.mate/bridges.php');
        $this->assertIsString($content);
        $this->assertStringNotContainsString('test', $content);
        $this->assertStringContainsString('mate discover', $content);
        $this->assertStringContainsString('enabled', $content);
    }

    public function testCreatesDirectoryIfNotExists()
    {
        $command = new InitCommand($this->tempDir);
        $tester = new CommandTester($command);

        // Ensure .mate directory doesn't exist
        $this->assertDirectoryDoesNotExist($this->tempDir.'/.mate');

        $tester->execute([]);

        // Directory should be created
        $this->assertDirectoryExists($this->tempDir.'/.mate');
        $this->assertFileExists($this->tempDir.'/.mate/bridges.php');
        $this->assertFileExists($this->tempDir.'/.mate/config.php');
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
