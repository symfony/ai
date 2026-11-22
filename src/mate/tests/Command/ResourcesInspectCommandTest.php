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
use Symfony\AI\Mate\Command\ResourcesInspectCommand;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResourcesInspectCommandTest extends TestCase
{
    public function testInspectByName()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['resource' => 'sample-echo']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('sample://echo/{message}', $output);
        $this->assertStringContainsString('Parameters', $output);
        $this->assertStringContainsString('message', $output);
        $this->assertStringContainsString('resources:read sample://echo/{message}', $output);
    }

    public function testInspectByConcreteUriFindsTemplate()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['resource' => 'sample://echo/world', '--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $json = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame('sample-echo', $json['name']);
        $this->assertSame('template', $json['type']);
    }

    public function testInspectByExactUriOfStaticResource()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['resource' => 'sample://payload', '--format' => 'json']);

        $json = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertSame('resource', $json['type']);
        $this->assertSame('application/json', $json['mime_type']);
        $this->assertSame([], $json['parameters']);
    }

    public function testConcreteUriDoesNotMatchAcrossSlashes()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['resource' => 'sample://echo/a/b']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testUnknownResourceFails()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['resource' => 'nothing://here']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Resource "nothing://here" not found', $tester->getDisplay());
        $this->assertStringContainsString('resources:list', $tester->getDisplay());
    }

    public function testToonFormat()
    {
        $tester = new CommandTester($this->createCommand());

        $tester->execute(['resource' => 'sample-echo', '--format' => 'toon']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('sample-echo', $tester->getDisplay());
    }

    private function createCommand(): ResourcesInspectCommand
    {
        $extensions = [
            '_custom' => ['dirs' => ['tests/Command/Fixtures'], 'includes' => []],
        ];

        $logger = new NullLogger();
        $registry = new CapabilityRegistry(__DIR__.'/../..', $extensions, [], new ReflectionDiscoverer($logger), $logger);

        return new class($extensions, new CapabilityCollector($registry)) extends ResourcesInspectCommand {
            protected function isToonFormatAvailable(): bool
            {
                return true;
            }
        };
    }
}
