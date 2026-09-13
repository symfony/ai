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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Exception\PhpVersionMismatchException;
use Symfony\AI\Mate\Runtime\PhpVersionGuard;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PhpVersionGuardTest extends TestCase
{
    public function testPassesWhenTheRunningVersionMatches()
    {
        $guard = new PhpVersionGuard(\PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION, 'vendor/bin/mate');

        $guard->assertMatches('tools:list');

        $this->expectNotToPerformAssertions();
    }

    public function testIgnoresThePatchLevel()
    {
        $guard = new PhpVersionGuard(\PHP_VERSION, 'vendor/bin/mate');

        $guard->assertMatches('tools:list');

        $this->expectNotToPerformAssertions();
    }

    public function testFailsWhenTheRunningVersionDiffers()
    {
        $guard = new PhpVersionGuard('5.6', 'ddev exec vendor/bin/mate');

        $this->expectException(PhpVersionMismatchException::class);
        $this->expectExceptionMessage('this project expects PHP "5.6"');

        $guard->assertMatches('tools:list');
    }

    public function testTheFailureNamesTheConfiguredInvocation()
    {
        $guard = new PhpVersionGuard('5.6', 'ddev exec vendor/bin/mate');

        $this->expectException(PhpVersionMismatchException::class);
        $this->expectExceptionMessage('Run it as "ddev exec vendor/bin/mate"');

        $guard->assertMatches('tools:call');
    }

    /**
     * `init` writes the very configuration this guard reads, so it has to stay reachable, and
     * `discover` runs unattended after every `composer install`: refusing there would leave the
     * extension list and the agent instructions silently stale.
     */
    public function testExemptCommandsStayCallableUnderAnyVersion()
    {
        $guard = new PhpVersionGuard('5.6', 'vendor/bin/mate', static function (string $message): void {});

        foreach (['init', 'discover', 'list', 'help', 'completion', '_complete', null] as $command) {
            $guard->assertMatches($command);
        }

        $this->expectNotToPerformAssertions();
    }

    public function testExemptCommandsWarnAboutTheWrongInterpreter()
    {
        $warnings = [];
        $guard = new PhpVersionGuard('5.6', 'ddev exec vendor/bin/mate', static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });

        $guard->assertMatches('init');

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('this project expects PHP "5.6"', $warnings[0]);
        $this->assertStringContainsString('ddev exec vendor/bin/mate', $warnings[0]);
    }

    public function testExemptCommandsDoNotWarnWithoutAConfiguredVersion()
    {
        $warnings = [];
        $guard = new PhpVersionGuard(null, 'vendor/bin/mate', static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });

        $guard->assertMatches('init');

        $this->assertSame([], $warnings);
    }

    public function testExemptCommandsDoNotWarnWhenTheVersionMatches()
    {
        $warnings = [];
        $guard = new PhpVersionGuard(\PHP_VERSION, 'vendor/bin/mate', static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });

        $guard->assertMatches('init');

        $this->assertSame([], $warnings);
    }

    #[DataProvider('provideVersionsThatDisableTheCheck')]
    public function testNoConfiguredVersionDisablesTheCheck(?string $version)
    {
        $guard = new PhpVersionGuard($version, 'vendor/bin/mate');

        $guard->assertMatches('tools:list');

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function provideVersionsThatDisableTheCheck(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'unparsable' => ['not-a-version'];
    }
}
