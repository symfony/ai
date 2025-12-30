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
use Symfony\AI\Mate\Command\DebugExtensionsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DebugExtensionsCommandTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/../Discovery/Fixtures';
    }

    public function testExecuteDisplaysExtensions()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', ['vendor/package-a', 'vendor/package-b']);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['vendor/vendor/package-a/src'], 'includes' => []],
            'vendor/package-b' => ['dirs' => ['vendor/vendor/package-b/lib'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('MCP Extension Discovery', $output);
        $this->assertStringContainsString('Root Project', $output);
        $this->assertStringContainsString('vendor/package-a', $output);
        $this->assertStringContainsString('vendor/package-b', $output);
        $this->assertStringContainsString('Summary', $output);
    }

    public function testExecuteShowsEnabledExtensionsOnly()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', ['vendor/package-a']);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['vendor/vendor/package-a/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('vendor/package-a', $output);
        $this->assertStringContainsString('enabled', $output);
        // vendor/package-b should not be shown as enabled
        $this->assertStringNotContainsString('Disabled Extensions', $output);
    }

    public function testExecuteWithShowAllFlag()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', ['vendor/package-a']);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['vendor/vendor/package-a/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['--show-all' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('vendor/package-a', $output);
        $this->assertStringContainsString('vendor/package-b', $output);
        $this->assertStringContainsString('Disabled Extensions', $output);
    }

    public function testExecuteWithJsonFormat()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', ['vendor/package-a']);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['vendor/vendor/package-a/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('extensions', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('_custom', $json['extensions']);
        $this->assertArrayHasKey('vendor/package-a', $json['extensions']);
        $this->assertArrayHasKey('total_discovered', $json['summary']);
        $this->assertArrayHasKey('enabled', $json['summary']);
        $this->assertArrayHasKey('disabled', $json['summary']);
        $this->assertArrayHasKey('loaded', $json['summary']);
    }

    public function testExecuteShowsExtensionDetails()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', ['vendor/package-a']);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['vendor/vendor/package-a/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Scan directories', $output);
        $this->assertStringContainsString('vendor/vendor/package-a/src', $output);
    }

    public function testExecuteHandlesNoLoadedExtensions()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', []);
        $container->setParameter('mate.extensions', [
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Root Project', $output);
    }

    public function testExecuteShowsLoadedStatus()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', ['vendor/package-a']);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['vendor/vendor/package-a/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('loaded', $output);
    }

    public function testExecuteJsonFormatContainsExtensionMetadata()
    {
        $rootDir = $this->fixturesDir.'/with-ai-mate-config';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', ['vendor/package-a']);
        $container->setParameter('mate.extensions', [
            'vendor/package-a' => ['dirs' => ['vendor/vendor/package-a/src'], 'includes' => []],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute(['--format' => 'json']);

        $json = json_decode($tester->getDisplay(), true);
        $this->assertArrayHasKey('type', $json['extensions']['_custom']);
        $this->assertSame('root_project', $json['extensions']['_custom']['type']);
        $this->assertArrayHasKey('status', $json['extensions']['_custom']);
        $this->assertArrayHasKey('loaded', $json['extensions']['_custom']);
        $this->assertArrayHasKey('scan_dirs', $json['extensions']['_custom']);
        $this->assertArrayHasKey('includes', $json['extensions']['_custom']);

        $this->assertArrayHasKey('type', $json['extensions']['vendor/package-a']);
        $this->assertSame('vendor_extension', $json['extensions']['vendor/package-a']['type']);
    }

    public function testExecuteShowsIncludeFiles()
    {
        $rootDir = $this->fixturesDir.'/with-includes';
        $container = new ContainerBuilder();
        $container->setParameter('mate.root_dir', $rootDir);
        $container->setParameter('mate.enabled_extensions', ['vendor/package-with-includes']);
        $container->setParameter('mate.extensions', [
            'vendor/package-with-includes' => [
                'dirs' => [],
                'includes' => [$rootDir.'/vendor/vendor/package-with-includes/config/config.php'],
            ],
            '_custom' => ['dirs' => [], 'includes' => []],
        ]);

        $command = new DebugExtensionsCommand(new NullLogger(), $container);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Include files', $output);
        $this->assertStringContainsString('config/config.php', $output);
    }
}
