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
use Symfony\AI\Mate\Command\ResourcesListCommand;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResourcesListCommandTest extends TestCase
{
    public function testListsResourcesAndTemplates()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('sample://greeting', $output);
        $this->assertStringContainsString('sample://echo/{message}', $output);
        $this->assertStringContainsString('template', $output);
        $this->assertStringContainsString('Total: 3 resource(s)', $output);
    }

    public function testJsonFormatCarriesTypeAndParameters()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--format' => 'json']);

        $json = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame(3, $json['summary']['total']);

        $byName = array_column($json['resources'], null, 'name');
        $this->assertSame('resource', $byName['sample-greeting']['type']);
        $this->assertSame([], $byName['sample-greeting']['parameters']);
        $this->assertSame('template', $byName['sample-echo']['type']);
        $this->assertSame(['message'], $byName['sample-echo']['parameters']);
        $this->assertSame('text/plain', $byName['sample-echo']['mime_type']);
    }

    public function testToonFormat()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--format' => 'toon']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('sample-echo', $tester->getDisplay());
    }

    public function testFilterMatchesName()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--filter' => 'sample-e*', '--format' => 'json']);

        $json = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame(['sample-echo'], array_column($json['resources'], 'name'));
    }

    public function testFilterMatchesUri()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--filter' => 'sample://echo/*', '--format' => 'json']);

        $json = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame(['sample-echo'], array_column($json['resources'], 'name'));
    }

    public function testFilterWithoutMatchThrows()
    {
        $tester = new CommandTester($this->createCommand());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No resources found matching pattern "nothing*".');

        $tester->execute(['--filter' => 'nothing*']);
    }

    public function testExtensionFilterWithUnknownExtensionThrows()
    {
        $tester = new CommandTester($this->createCommand());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No resources found for extension "vendor/unknown".');

        $tester->execute(['--extension' => 'vendor/unknown']);
    }

    public function testUnknownFormatFails()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['--format' => 'yaml']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown output format "yaml"', $tester->getDisplay());
    }

    private function createCommand(): ResourcesListCommand
    {
        $extensions = [
            '_custom' => ['dirs' => ['tests/Command/Fixtures'], 'includes' => []],
        ];

        $logger = new NullLogger();
        $registry = new CapabilityRegistry(__DIR__.'/../..', $extensions, [], new ReflectionDiscoverer($logger), $logger);

        return new class($extensions, new CapabilityCollector($registry)) extends ResourcesListCommand {
            protected function isToonFormatAvailable(): bool
            {
                return true;
            }
        };
    }
}
