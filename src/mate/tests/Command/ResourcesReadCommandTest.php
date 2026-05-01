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
use Symfony\AI\Mate\Command\ResourcesReadCommand;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;
use Symfony\AI\Mate\Service\RegistryProvider;
use Symfony\AI\Mate\Tests\Command\Fixtures\SampleResources;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResourcesReadCommandTest extends TestCase
{
    public function testReadStaticResource()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            'uri' => 'sample://greeting',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Reading Resource: sample://greeting', $output);
        $this->assertStringContainsString('Hello from the Mate test fixture!', $output);
    }

    public function testReadTemplatedResource()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            'uri' => 'sample://echo/world',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Reading Resource: sample://echo/world', $output);
        $this->assertStringContainsString('echo: world', $output);
    }

    public function testReadWithJsonFormat()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            'uri' => 'sample://greeting',
            '--format' => 'json',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $this->assertStringNotContainsString('Reading Resource:', $output);

        $contents = json_decode($output, true);
        $this->assertIsArray($contents);
        $this->assertCount(1, $contents);
        $this->assertSame('sample://greeting', $contents[0]['uri']);
        $this->assertSame('text/plain', $contents[0]['mimeType']);
        $this->assertSame('Hello from the Mate test fixture!', $contents[0]['text']);
    }

    public function testReadWithToonFormat()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            'uri' => 'sample://greeting',
            '--format' => 'toon',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $contents = Toon::decode($output);
        $this->assertIsArray($contents);
        $this->assertSame('sample://greeting', $contents[0]['uri']);
        $this->assertSame('Hello from the Mate test fixture!', $contents[0]['text']);
    }

    public function testReadWithUnknownUri()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([
            'uri' => 'unknown://does-not-exist',
        ]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Resource "unknown://does-not-exist" not found', $output);
    }

    private function createCommand(): ResourcesReadCommand
    {
        $rootDir = __DIR__.'/../..';
        $extensions = [
            '_custom' => ['dirs' => ['tests/Command/Fixtures'], 'includes' => []],
        ];

        $logger = new NullLogger();
        $discoverer = new Discoverer($logger);
        $loader = new FilteredDiscoveryLoader($rootDir, $extensions, [], $discoverer, $logger);
        $registryProvider = new RegistryProvider($loader, $logger);

        $container = new ContainerBuilder();
        $container->set(SampleResources::class, new SampleResources());

        return new class($registryProvider, $container) extends ResourcesReadCommand {
            protected function isToonFormatAvailable(): bool
            {
                return true;
            }
        };
    }
}
