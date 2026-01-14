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
use Symfony\AI\Mate\Command\ToolsListCommand;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ]);

        $command = new ToolsListCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('MCP Tools', $output);
        $this->assertStringContainsString('Total:', $output);
        $this->assertStringContainsString('tool(s)', $output);
        $this->assertStringContainsString('php-version', $output);
        $this->assertStringContainsString('operating-system', $output);
    }

    public function testExecuteWithJsonFormat()
    {
        $rootDir = __DIR__.'/../..';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ]);

        $command = new ToolsListCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('tools', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('total', $json['summary']);
        $this->assertGreaterThanOrEqual(4, $json['summary']['total']);
        $this->assertArrayHasKey('php-version', $json['tools']);
        $this->assertArrayHasKey('name', $json['tools']['php-version']);
        $this->assertArrayHasKey('handler', $json['tools']['php-version']);
        $this->assertArrayHasKey('extension', $json['tools']['php-version']);
    }

    public function testExecuteWithInvalidExtensionFilter()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new ToolsListCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No tools found for extension "invalid-extension"');

        $tester->execute(['--extension' => 'invalid-extension']);
    }

    public function testExecuteWithInvalidNameFilter()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['mate/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new ToolsListCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No tools found matching pattern "non-existent-tool-name"');

        $tester->execute(['--filter' => 'non-existent-tool-name']);
    }

    public function testTableOutputFormat()
    {
        $rootDir = __DIR__.'/../..';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ]);

        $command = new ToolsListCommand(new NullLogger(), $container);
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

    public function testExecuteWithNameFilterMatchingTools()
    {
        $rootDir = __DIR__.'/../..';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ]);

        $command = new ToolsListCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['--filter' => 'php-*']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('php-version', $output);
        $this->assertStringContainsString('php-extensions', $output);
        $this->assertStringNotContainsString('operating-system-family', $output);
    }
}
