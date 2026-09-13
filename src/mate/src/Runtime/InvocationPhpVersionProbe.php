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

use Symfony\Component\Process\Process;

/**
 * Determines the PHP major.minor version that will actually run Mate when the developer's
 * answer to "which command should your coding agent use to run Mate" wraps the binary in a
 * container/multi-PHP prefix (`ddev exec`, `symfony php`, `docker compose exec app php`, ...).
 *
 * The process running `mate init` is not necessarily the process that later runs `mate` for
 * real: a bare `vendor/bin/mate` executes directly, so the current process's own
 * `\PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION` is correct. A wrapped invocation routes through
 * something else entirely (a container, a version manager, ...), so this asks that same
 * wrapper to report the version by actually running `php` through it, instead of trusting the
 * host interpreter that merely happened to run `composer require` and `mate init`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class InvocationPhpVersionProbe
{
    /**
     * A cold `ddev exec` or `docker compose exec` may have to start a container first. Being too
     * impatient here pins the wrong version behind a warning that is easy to skim past, so the
     * rare long wait is the cheaper mistake.
     */
    private const TIMEOUT = 30.0;

    /**
     * The probe tags its own answer, so container startup banners, deprecations and startup
     * warnings on stdout cannot be mistaken for a version. `display_errors=0` keeps most of that
     * noise out in the first place.
     */
    private const SENTINEL = 'MATE_PHP_VERSION=';

    private const PROBE_SCRIPT = 'echo "MATE_PHP_VERSION=".\PHP_MAJOR_VERSION.".".\PHP_MINOR_VERSION;';

    /**
     * @var \Closure(list<string>): (string|null)
     */
    private \Closure $runner;

    private ?string $lastFailure = null;

    /**
     * @param string|null                                  $workingDirectory the directory the probe
     *                                                                       runs in; `docker compose
     *                                                                       exec` needs the compose
     *                                                                       file and `ddev exec` needs
     *                                                                       the project, so this must
     *                                                                       be the project root
     * @param (\Closure(list<string>): (string|null))|null $runner           runs a command and
     *                                                                       returns its stdout, or
     *                                                                       null on any failure
     */
    public function __construct(
        private ?string $workingDirectory = null,
        ?\Closure $runner = null,
    ) {
        $this->runner = $runner ?? $this->runProcess(...);
    }

    /**
     * Why the last {@see detect()} came back empty, when the probe itself could say.
     */
    public function lastFailure(): ?string
    {
        return $this->lastFailure;
    }

    /**
     * True when the invocation names something in front of the binary (a wrapper), as opposed
     * to a single token that is the binary itself.
     */
    public function isWrapped(string $invocation): bool
    {
        return [] !== $this->wrapperTokens($invocation);
    }

    /**
     * Runs `php` through the invocation's wrapper and reports the `major.minor` it belongs to.
     * Returns null when the wrapper cannot be executed, or its output does not parse as a
     * version; callers should fall back to the current process's own version and warn.
     */
    public function detect(string $invocation): ?string
    {
        $this->lastFailure = null;

        $wrapper = $this->wrapperTokens($invocation);
        if ([] === $wrapper) {
            return null;
        }

        $output = ($this->runner)($this->probeCommand($wrapper));
        if (null === $output) {
            return null;
        }

        return $this->normalize($output);
    }

    /**
     * Strips the trailing binary token (`vendor/bin/mate`, or any custom path whose basename
     * contains "mate") from the invocation, leaving only the wrapper in front of it. An
     * invocation with nothing left after that is the bare binary: not wrapped, nothing to probe.
     *
     * @return list<string>
     */
    private function wrapperTokens(string $invocation): array
    {
        $tokens = preg_split('/\s+/', trim($invocation));
        if (false === $tokens || [] === $tokens) {
            return [];
        }

        // Popping unconditionally would turn `ddev exec` into `ddev`, silently probing through a
        // different command than the one configured.
        if (str_contains(basename((string) end($tokens)), 'mate')) {
            array_pop($tokens);
        }

        return $tokens;
    }

    /**
     * Appends a `php -r` probe to the wrapper, unless the wrapper already ends with a `php`
     * (or `php8.3`, ...) token: `symfony php` and `docker compose exec app php` already name
     * the interpreter that would otherwise have run the binary, and doubling it up (`symfony
     * php php -r ...`) runs nothing.
     *
     * @param list<string> $wrapper
     *
     * @return list<string>
     */
    private function probeCommand(array $wrapper): array
    {
        $lastToken = basename((string) end($wrapper));
        if (1 === preg_match('/^php(\d+(\.\d+)?)?$/', $lastToken)) {
            return array_merge($wrapper, ['-d', 'display_errors=0', '-r', self::PROBE_SCRIPT]);
        }

        return array_merge($wrapper, ['php', '-d', 'display_errors=0', '-r', self::PROBE_SCRIPT]);
    }

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command): ?string
    {
        try {
            $process = new Process($command, $this->workingDirectory, null, null, self::TIMEOUT);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->lastFailure = trim($process->getErrorOutput());
                if ('' === $this->lastFailure) {
                    $this->lastFailure = \sprintf('exit code %s', $process->getExitCode() ?? 'unknown');
                }

                return null;
            }

            return $process->getOutput();
        } catch (\Throwable $e) {
            $this->lastFailure = $e->getMessage();

            return null;
        }
    }

    /**
     * Reads the version out of the probe's own sentinel rather than out of whatever the wrapper
     * happened to print. A leading-noise pattern cannot do this: `Container app-web-1  Running`
     * carries digits, and matching the first version-shaped thing in the stream would answer with
     * a container name.
     */
    private function normalize(string $output): ?string
    {
        if (1 !== preg_match('/'.preg_quote(self::SENTINEL, '/').'(\d+)\.(\d+)/', $output, $matches)) {
            $this->lastFailure ??= \sprintf('unexpected output: "%s"', trim($output));

            return null;
        }

        return $matches[1].'.'.$matches[2];
    }
}
