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

use Mcp\Capability\Discovery\Discoverer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Capability\ServerInfo;
use Symfony\AI\Mate\Command\ToolsCallCommand;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolsCallCommandTest extends TestCase
{
    public function testExecuteCallsToolSuccessfully()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([
            'tool-name' => 'php-version',
            'json-input' => '{}',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Executing Tool: php-version', $output);
        $this->assertStringContainsString('Result', $output);
        $this->assertStringContainsString(\PHP_VERSION, $output);
    }

    public function testExecuteWithJsonFormat()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([
            'tool-name' => 'php-version',
            'json-input' => '{}',
            '--format' => 'json',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        // JSON format should not include decorative headers
        $this->assertStringNotContainsString('Executing Tool:', $output);
        $this->assertStringNotContainsString('Result', $output);

        $result = json_decode($output, true);
        $this->assertIsString($result);
        $this->assertSame(\PHP_VERSION, $result);
    }

    public function testExecuteWithInvalidToolName()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([
            'tool-name' => 'non-existent-tool',
            'json-input' => '{}',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Tool "non-existent-tool" not found', $output);
    }

    public function testExecuteWithInvalidJson()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([
            'tool-name' => 'php-version',
            'json-input' => '{invalid json}',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Invalid JSON', $output);
    }

    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     */
    private function createCommand(string $rootDir, array $extensions): ToolsCallCommand
    {
        $logger = new NullLogger();
        $discoverer = new Discoverer($logger);
        $loader = new FilteredDiscoveryLoader($rootDir, $extensions, [], $discoverer, $logger);

        $container = new ContainerBuilder();
        $container->set(ServerInfo::class, new ServerInfo());

        return new ToolsCallCommand($loader, $container, $logger);
    }
}
