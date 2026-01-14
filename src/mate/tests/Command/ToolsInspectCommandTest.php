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
use Symfony\AI\Mate\Command\ToolsInspectCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolsInspectCommandTest extends TestCase
{
    public function testExecuteInspectsExistingTool()
    {
        $rootDir = __DIR__.'/../..';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ]);

        $command = new ToolsInspectCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['tool-name' => 'php-version']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('php-version', $output);
        $this->assertStringContainsString('Description', $output);
        $this->assertStringContainsString('Handler', $output);
        $this->assertStringContainsString('Extension', $output);
        $this->assertStringContainsString('Input Schema', $output);
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

        $command = new ToolsInspectCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['tool-name' => 'php-version', '--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('description', $json);
        $this->assertArrayHasKey('handler', $json);
        $this->assertArrayHasKey('input_schema', $json);
        $this->assertArrayHasKey('extension', $json);
        $this->assertSame('php-version', $json['name']);
    }

    public function testExecuteWithInvalidToolName()
    {
        $rootDir = __DIR__.'/../..';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ]);

        $command = new ToolsInspectCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['tool-name' => 'non-existent-tool']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Tool "non-existent-tool" not found', $output);
        $this->assertStringContainsString('mcp:tools:list', $output);
    }

    public function testTextOutputFormatDisplaysFullInformation()
    {
        $rootDir = __DIR__.'/../..';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ]);

        $command = new ToolsInspectCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['tool-name' => 'php-version', '--format' => 'text']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('php-version', $output);
        $this->assertStringContainsString('Description', $output);
        $this->assertStringContainsString('Handler', $output);
        $this->assertStringContainsString('Extension', $output);
        $this->assertStringContainsString('Input Schema', $output);
        $this->assertStringContainsString('_custom', $output);
    }

    public function testJsonOutputContainsAllFields()
    {
        $rootDir = __DIR__.'/../..';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.disabled_features', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => ['src/Capability'], 'includes' => []],
        ]);

        $command = new ToolsInspectCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['tool-name' => 'operating-system', '--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertSame('operating-system', $json['name']);
        $this->assertIsString($json['handler']);
        $this->assertSame('_custom', $json['extension']);
    }
}
