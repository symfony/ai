<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Runtime;

use Symfony\AI\Mate\Exception\PhpVersionMismatchException;

/**
 * Refuses to run when the interpreter is not the one the project recorded.
 *
 * Mate reads the compiled container, the profiler cache and the logs of *this* project, and
 * extensions may behave differently per runtime. A host-side invocation against an application
 * that lives in a container therefore reports something that is not the application under test,
 * which is worse than not answering at all.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PhpVersionGuard
{
    /**
     * Commands that must stay callable under any interpreter: `init` writes the very
     * configuration this guard reads, `discover` reads Composer metadata rather than the
     * application and runs unattended after every `composer install`, and the rest never touch
     * the application. They still warn, so a wrong interpreter is visible before it reaches a
     * command that does refuse.
     */
    private const EXEMPT_COMMANDS = ['init', 'discover', 'list', 'help', 'completion', '_complete'];

    /**
     * @var \Closure(string): void
     */
    private \Closure $warningWriter;

    /**
     * @param (\Closure(string): void)|null $warningWriter
     */
    public function __construct(
        private ?string $expectedVersion,
        private string $invocation,
        ?\Closure $warningWriter = null,
    ) {
        $this->warningWriter = $warningWriter ?? static function (string $message): void {
            fwrite(\STDERR, \PHP_EOL.' [WARNING] '.$message.\PHP_EOL.\PHP_EOL);
        };
    }

    public function assertMatches(?string $commandName): void
    {
        $expected = $this->normalize($this->expectedVersion);
        if (null === $expected) {
            return;
        }

        $running = \PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION;
        if ($running === $expected) {
            return;
        }

        $mismatch = \sprintf('Mate is running under PHP "%s" but this project expects PHP "%s". Run it as "%s".', \PHP_VERSION, $expected, $this->invocation);

        if (null === $commandName || \in_array($commandName, self::EXEMPT_COMMANDS, true)) {
            ($this->warningWriter)($mismatch.' This command does not read the application, so it runs anyway, but the ones that do will refuse.');

            return;
        }

        throw new PhpVersionMismatchException($mismatch.' The expected version is the "mate.php_version" parameter in mate/config.php; remove it to disable this check.');
    }

    /**
     * Reduces any version string to `major.minor`, which is the granularity that decides
     * whether an extension behaves the same.
     */
    private function normalize(?string $version): ?string
    {
        if (null === $version || '' === trim($version)) {
            return null;
        }

        if (1 !== preg_match('/^\D*(\d+)\.(\d+)/', trim($version), $matches)) {
            return null;
        }

        return $matches[1].'.'.$matches[2];
    }
}
