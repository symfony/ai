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
use Symfony\AI\Mate\Command\DebugCapabilitiesCommand;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DebugCapabilitiesCommandTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/../Discovery/Fixtures';
    }

    public function testExecuteDisplaysCapabilities()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Mate MCP Capabilities', $output);
        $this->assertStringContainsString('Summary', $output);
    }

    public function testExecuteWithEmptyExtensions()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Root Project', $output);
    }

    public function testExecuteWithJsonFormat()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('extensions', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('extensions', $json['summary']);
        $this->assertArrayHasKey('tools', $json['summary']);
        $this->assertArrayHasKey('resources', $json['summary']);
        $this->assertArrayHasKey('prompts', $json['summary']);
        $this->assertArrayHasKey('resource_templates', $json['summary']);
    }

    public function testExecuteWithExtensionFilter()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute(['--extension' => '_custom']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Root Project', $output);
        $this->assertStringNotContainsString('vendor/package-a', $output);
        $this->assertStringNotContainsString('vendor/package-b', $output);
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
        $this->expectExceptionMessage('Extension "invalid-extension" not found');

        $tester->execute(['--extension' => 'invalid-extension']);
    }

    public function testExecuteWithTypeFilter()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute(['--type' => 'tool']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteWithInvalidTypeFilter()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid type "invalid-type"');

        $tester->execute(['--type' => 'invalid-type']);
    }

    public function testExecuteWithCombinedFilters()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([
            '--extension' => 'vendor/package-a',
            '--type' => 'tool',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteWithJsonFormatAndFilters()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $extensions = [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ];

        $command = $this->createCommand($rootDir, $extensions);
        $tester = new CommandTester($command);

        $tester->execute([
            '--format' => 'json',
            '--extension' => '_custom',
            '--type' => 'resource',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('_custom', $json['extensions']);
        $this->assertArrayHasKey('resources', $json['extensions']['_custom']);
        $this->assertArrayNotHasKey('tools', $json['extensions']['_custom']);
    }

    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     * @param array<string, array<string, array{enabled: bool}>>       $disabledFeatures
     */
    private function createCommand(string $rootDir, array $extensions, array $disabledFeatures = []): DebugCapabilitiesCommand
    {
        $logger = new NullLogger();
        $discoverer = new Discoverer($logger);
        $loader = new FilteredDiscoveryLoader($rootDir, $extensions, $disabledFeatures, $discoverer, $logger);
        $collector = new CapabilityCollector($loader);

        return new DebugCapabilitiesCommand($extensions, $collector);
    }
}
