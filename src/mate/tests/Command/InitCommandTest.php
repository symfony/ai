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
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Agent\AgentInstructionsAggregator;
use Symfony\AI\Mate\Agent\AgentInstructionsMaterializer;
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
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->tempDir.'/mate');
        $this->assertFileExists($this->tempDir.'/mate/extensions.php');
        $this->assertFileExists($this->tempDir.'/mate/config.php');
        $this->assertFileExists($this->tempDir.'/mate/.env');
        $this->assertFileExists($this->tempDir.'/mate/AGENT_INSTRUCTIONS.md');
        $this->assertFileExists($this->tempDir.'/mcp.json');
        $this->assertFileExists($this->tempDir.'/bin/codex');
        $this->assertFileExists($this->tempDir.'/bin/codex.bat');
        $this->assertTrue(is_executable($this->tempDir.'/bin/codex'));
        $this->assertTrue(is_link($this->tempDir.'/.mcp.json'));
        $this->assertSame('mcp.json', readlink($this->tempDir.'/.mcp.json'));
        $this->assertFileExists($this->tempDir.'/AGENTS.md');

        $content = file_get_contents($this->tempDir.'/mate/extensions.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('mate discover', $content);
        $this->assertStringContainsString('enabled', $content);
    }

    public function testDisplaysSuccessMessage()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('AI Mate Initialization', $output);
        $this->assertStringContainsString('extensions.php', $output);
        $this->assertStringContainsString('config.php', $output);
        $this->assertStringContainsString('composer dump-autoload', $output);
        $this->assertStringContainsString('./bin/codex', $output);
        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('Created', $output);
    }

    public function testDoesNotOverwriteExistingFileWithoutConfirmation()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // Create existing file
        mkdir($this->tempDir.'/mate', 0755, true);
        file_put_contents($this->tempDir.'/mate/extensions.php', '<?php return ["test" => "value"];');

        // Execute with 'no' response (twice for both files)
        $tester->setInputs(['no', 'no']);
        $tester->execute([]);

        // File should still contain original content
        $content = file_get_contents($this->tempDir.'/mate/extensions.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('value', $content);
    }

    public function testOverwritesExistingFileWithConfirmation()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // Create existing file
        mkdir($this->tempDir.'/mate', 0755, true);
        file_put_contents($this->tempDir.'/mate/extensions.php', '<?php return ["test" => "value"];');

        // Execute with 'yes' response (twice for both files)
        $tester->setInputs(['yes', 'yes']);
        $tester->execute([]);

        // File should be overwritten with template content
        $content = file_get_contents($this->tempDir.'/mate/extensions.php');
        $this->assertIsString($content);
        $this->assertStringNotContainsString('test', $content);
        $this->assertStringContainsString('mate discover', $content);
        $this->assertStringContainsString('enabled', $content);
    }

    public function testCreatesDirectoryIfNotExists()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // Ensure mate directory doesn't exist
        $this->assertDirectoryDoesNotExist($this->tempDir.'/mate');

        $tester->execute([]);

        // Directory should be created
        $this->assertDirectoryExists($this->tempDir.'/mate');
        $this->assertFileExists($this->tempDir.'/mate/extensions.php');
        $this->assertFileExists($this->tempDir.'/mate/config.php');
    }

    public function testMcpJsonUsesPhpBinaryByDefault()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $mcpJson = $this->readMcpJson();
        $this->assertSame('php', $mcpJson['mcpServers']['symfony-ai-mate']['command']);
        $this->assertSame(
            ['./vendor/bin/mate', 'serve', '--force-keep-alive'],
            $mcpJson['mcpServers']['symfony-ai-mate']['args']
        );
    }

    public function testMcpJsonDefaultsToDdevWrapperWhenDdevDetected()
    {
        mkdir($this->tempDir.'/.ddev', 0755, true);

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // Accept the detected default by submitting an empty answer.
        $tester->setInputs(['']);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $mcpJson = $this->readMcpJson();
        $this->assertSame('ddev', $mcpJson['mcpServers']['symfony-ai-mate']['command']);
        $this->assertSame(
            ['exec', 'php', './vendor/bin/mate', 'serve', '--force-keep-alive'],
            $mcpJson['mcpServers']['symfony-ai-mate']['args']
        );
    }

    public function testMcpJsonUsesProvidedPhpBinary()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['docker compose exec php php']);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $mcpJson = $this->readMcpJson();
        $this->assertSame('docker', $mcpJson['mcpServers']['symfony-ai-mate']['command']);
        $this->assertSame(
            ['compose', 'exec', 'php', 'php', './vendor/bin/mate', 'serve', '--force-keep-alive'],
            $mcpJson['mcpServers']['symfony-ai-mate']['args']
        );
    }

    public function testKeepsExistingMcpJsonUntouchedWhenOverwriteDeclined()
    {
        $existing = <<<'JSON'
            {
                "mcpServers": {
                    "symfony-ai-mate": {
                        "command": "my-custom-php",
                        "args": ["./vendor/bin/mate", "serve"]
                    }
                }
            }
            JSON;
        file_put_contents($this->tempDir.'/mcp.json', $existing);

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // Decline overwriting the existing mcp.json; no PHP binary prompt should follow.
        $tester->setInputs(['no']);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        // The user's file is left exactly as-is, with no placeholder resolution.
        $this->assertSame($existing, file_get_contents($this->tempDir.'/mcp.json'));
        // And the command neither prompts for a binary nor claims it configured one.
        $display = $tester->getDisplay();
        $this->assertStringNotContainsString('PHP binary to run Mate', $display);
        $this->assertStringNotContainsString('Configured', $display);
    }

    public function testSetsExtensionFalseByDefault()
    {
        // Create composer.json without ai-mate config
        file_put_contents($this->tempDir.'/composer.json', json_encode(['name' => 'test/package']));

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Verify composer.json has extension: false by default
        $composerContent = file_get_contents($this->tempDir.'/composer.json');
        $this->assertIsString($composerContent);
        $composerJson = json_decode($composerContent, true);
        $this->assertIsArray($composerJson);
        $this->assertArrayHasKey('extra', $composerJson);
        $this->assertArrayHasKey('ai-mate', $composerJson['extra']);
        $this->assertArrayHasKey('extension', $composerJson['extra']['ai-mate']);
        $this->assertFalse($composerJson['extra']['ai-mate']['extension']);
    }

    public function testScaffoldsSensitiveFilesWithSecurePermissions()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Permission-based tests are not reliable on Windows');
        }

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(0750, fileperms($this->tempDir.'/mate') & 0777);
        $this->assertSame(0640, fileperms($this->tempDir.'/mate/.env') & 0777);
        $this->assertSame(0640, fileperms($this->tempDir.'/mate/config.php') & 0777);
    }

    private function createCommand(): InitCommand
    {
        $logger = new NullLogger();
        $aggregator = new AgentInstructionsAggregator($this->tempDir, [], $logger);
        $materializer = new AgentInstructionsMaterializer($this->tempDir, $aggregator, $logger);

        return new InitCommand($this->tempDir, $materializer);
    }

    /**
     * @return array{mcpServers: array{symfony-ai-mate: array{command: string, args: list<string>}}}
     */
    private function readMcpJson(): array
    {
        $content = file_get_contents($this->tempDir.'/mcp.json');
        $this->assertIsString($content);

        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);

        return $decoded;
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
