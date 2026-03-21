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

use HelgeSverre\Toon\Toon;
use Mcp\Capability\Discovery\Discoverer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Command\ToolsListCommand;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolsListCommandTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/../Discovery/Fixtures';
    }

    public function testExecuteDisplaysToolsList()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('MCP Tools', $output);
        $this->assertStringContainsString('Total:', $output);
        $this->assertStringContainsString('tool(s)', $output);
        $this->assertStringContainsString('server-info', $output);
    }

    public function testExecuteWithJsonFormat()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('tools', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('total', $json['summary']);
        $this->assertGreaterThanOrEqual(1, $json['summary']['total']);
        $this->assertArrayHasKey('server-info', $json['tools']);
        $this->assertArrayHasKey('name', $json['tools']['server-info']);
        $this->assertArrayHasKey('handler', $json['tools']['server-info']);
        $this->assertArrayHasKey('extension', $json['tools']['server-info']);
    }

    public function testExecuteWithToonFormat()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute(['--format' => 'toon']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $toon = Toon::decode($output);
        $this->assertIsArray($toon);
        $this->assertArrayHasKey('tools', $toon);
        $this->assertArrayHasKey('summary', $toon);
        $this->assertArrayHasKey('total', $toon['summary']);
        $this->assertGreaterThanOrEqual(1, $toon['summary']['total']);
        $this->assertArrayHasKey('server-info', $toon['tools']);
        $this->assertArrayHasKey('name', $toon['tools']['server-info']);
        $this->assertArrayHasKey('handler', $toon['tools']['server-info']);
        $this->assertArrayHasKey('extension', $toon['tools']['server-info']);
    }

    public function testExecuteWithInvalidExtensionFilter()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No tools found for extension "invalid-extension"');

        $tester->execute(['--extension' => 'invalid-extension']);
    }

    public function testExecuteWithInvalidNameFilter()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No tools found matching pattern "non-existent-tool-name"');

        $tester->execute(['--filter' => 'non-existent-tool-name']);
    }

    public function testTableOutputFormat()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute(['--format' => 'table']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('MCP Tools', $output);
        $this->assertStringContainsString('Tool Name', $output);
        $this->assertStringContainsString('Description', $output);
        $this->assertStringContainsString('Handler', $output);
        $this->assertStringContainsString('Extension', $output);
    }

    public function testExecuteWithToonFormatFailsWhenToonIsNotAvailable()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $logger = new NullLogger();
        $discoverer = new Discoverer($logger);
        $loader = new FilteredDiscoveryLoader($rootDir, $extensions, [], $discoverer, $logger);
        $collector = new CapabilityCollector($loader);

        $command = new class($extensions, $collector) extends ToolsListCommand {
            protected function isToonFormatAvailable(): bool
            {
                return false;
            }
        };
        $tester = new CommandTester($command);

        $tester->execute(['--format' => 'toon']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('helgesverre/toon', $output);
        $this->assertStringContainsString('composer require helgesverre/toon', $output);
    }

    public function testExecuteWithNameFilterMatchingTools()
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute(['--filter' => 'server-*']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('server-info', $output);
    }

    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     * @param array<string, array<string, array{enabled: bool}>>       $disabledFeatures
     */
    private function createCommand(string $rootDir, array $extensions, array $disabledFeatures = []): ToolsListCommand
    {
        $logger = new NullLogger();
        $discoverer = new Discoverer($logger);
        $loader = new FilteredDiscoveryLoader($rootDir, $extensions, $disabledFeatures, $discoverer, $logger);
        $collector = new CapabilityCollector($loader);

        return new class($extensions, $collector) extends ToolsListCommand {
            protected function isToonFormatAvailable(): bool
            {
                return true;
            }
        };
    }
}
