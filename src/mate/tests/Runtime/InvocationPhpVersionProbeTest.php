<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Runtime;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Runtime\InvocationPhpVersionProbe;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class InvocationPhpVersionProbeTest extends TestCase
{
    public function testTheBareBinaryIsNotWrapped()
    {
        $probe = new InvocationPhpVersionProbe();

        $this->assertFalse($probe->isWrapped('vendor/bin/mate'));
    }

    public function testACustomBinaryPathAloneIsNotWrapped()
    {
        $probe = new InvocationPhpVersionProbe();

        $this->assertFalse($probe->isWrapped('bin/mate'));
    }

    public function testAWrapperInFrontOfTheBinaryIsWrapped()
    {
        $probe = new InvocationPhpVersionProbe();

        $this->assertTrue($probe->isWrapped('ddev exec vendor/bin/mate'));
    }

    public function testDetectingTheBareBinaryNeverRunsTheRunner()
    {
        $probe = new InvocationPhpVersionProbe(null, function (array $command): string {
            $this->fail('The runner must not be invoked for a bare binary.');
        });

        $this->assertNull($probe->detect('vendor/bin/mate'));
    }

    public function testDetectsTheVersionReportedByTheWrapper()
    {
        $probe = new InvocationPhpVersionProbe(null, static function (array $command): string {
            return 'MATE_PHP_VERSION=8.3';
        });

        $this->assertSame('8.3', $probe->detect('ddev exec vendor/bin/mate'));
    }

    /**
     * A wrapper prints its own chatter before the probe ever runs, and that chatter carries
     * digits. Matching the first version-shaped thing in the stream would answer with a container
     * name, so the probe reads its own sentinel instead.
     */
    public function testIgnoresWrapperNoiseThatContainsDigits()
    {
        $noise = [
            "Container app-web-1  Running\nMATE_PHP_VERSION=8.3\n",
            "PHP Deprecated:  x in Command line code on line 1\nMATE_PHP_VERSION=8.3\n",
            "PHP Warning:  PHP Startup: Unable to load dynamic library 'x' in Unknown on line 0\nMATE_PHP_VERSION=8.3\n",
        ];

        foreach ($noise as $output) {
            $probe = new InvocationPhpVersionProbe(null, static fn (array $command): string => $output);

            $this->assertSame('8.3', $probe->detect('ddev exec vendor/bin/mate'), $output);
        }
    }

    public function testReportsWhyTheProbeCameBackEmpty()
    {
        $probe = new InvocationPhpVersionProbe(null, static fn (array $command): string => 'command not found');

        $this->assertNull($probe->detect('ddev exec vendor/bin/mate'));
        $this->assertStringContainsString('command not found', (string) $probe->lastFailure());
    }

    /**
     * `ddev exec` names no binary, so nothing may be popped off it: probing through `ddev` alone
     * would silently ask a different command than the one configured.
     */
    public function testKeepsAWrapperThatDoesNotEndInTheBinary()
    {
        $seenCommand = null;
        $probe = new InvocationPhpVersionProbe(null, static function (array $command) use (&$seenCommand): string {
            $seenCommand = $command;

            return 'MATE_PHP_VERSION=8.3';
        });

        $probe->detect('ddev exec');

        $this->assertSame(['ddev', 'exec'], \array_slice((array) $seenCommand, 0, 2));
    }

    /**
     * Exercises the real subprocess path, which every other test replaces. The probe script
     * contains quotes and backslash-prefixed constants, exactly the kind of thing that breaks
     * silently under argument escaping.
     */
    public function testTheDefaultRunnerReportsThisProcessesVersion()
    {
        $probe = new InvocationPhpVersionProbe();

        $this->assertSame(
            \PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION,
            $probe->detect(\PHP_BINARY.' vendor/bin/mate'),
        );
    }

    public function testReturnsNullWhenTheRunnerFails()
    {
        $probe = new InvocationPhpVersionProbe(null, static function (array $command): ?string {
            return null;
        });

        $this->assertNull($probe->detect('ddev exec vendor/bin/mate'));
    }

    public function testReturnsNullWhenTheOutputCarriesNoVersion()
    {
        $probe = new InvocationPhpVersionProbe(null, static fn (array $command): string => 'nothing useful');

        $this->assertNull($probe->detect('ddev exec vendor/bin/mate'));
    }

    public function testAppendsAPhpProbeToAWrapperWithoutAnInterpreter()
    {
        $seenCommand = null;
        $probe = new InvocationPhpVersionProbe(null, static function (array $command) use (&$seenCommand): string {
            $seenCommand = $command;

            return 'MATE_PHP_VERSION=8.3';
        });

        $probe->detect('ddev exec vendor/bin/mate');

        $this->assertProbesThrough(['ddev', 'exec', 'php'], $seenCommand);
    }

    /**
     * "docker compose exec app php bin/mate" already names "php" as the interpreter that would
     * have run the binary; appending another "php" would run "... php php -r ...", which fails.
     */
    public function testDoesNotDoubleUpAnInterpreterAlreadyNamedInTheWrapper()
    {
        $seenCommand = null;
        $probe = new InvocationPhpVersionProbe(null, static function (array $command) use (&$seenCommand): string {
            $seenCommand = $command;

            return 'MATE_PHP_VERSION=8.3';
        });

        $probe->detect('docker compose exec app php bin/mate');

        $this->assertProbesThrough(['docker', 'compose', 'exec', 'app', 'php'], $seenCommand);
    }

    public function testDoesNotDoubleUpAVersionedInterpreter()
    {
        $seenCommand = null;
        $probe = new InvocationPhpVersionProbe(null, static function (array $command) use (&$seenCommand): string {
            $seenCommand = $command;

            return 'MATE_PHP_VERSION=8.3';
        });

        $probe->detect('symfony php8.3 vendor/bin/mate');

        $this->assertProbesThrough(['symfony', 'php8.3'], $seenCommand);
    }

    /**
     * @param list<string>      $expectedWrapper
     * @param list<string>|null $seenCommand
     */
    private function assertProbesThrough(array $expectedWrapper, ?array $seenCommand): void
    {
        $this->assertIsArray($seenCommand);
        $this->assertSame($expectedWrapper, \array_slice($seenCommand, 0, \count($expectedWrapper)));

        $script = end($seenCommand);
        $this->assertIsString($script);
        $this->assertStringContainsString('MATE_PHP_VERSION=', $script);
        $this->assertSame('-r', $seenCommand[\count($seenCommand) - 2]);
    }
}
