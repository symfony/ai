<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\E2E;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The environment the end-to-end tests hand to the processes they start - the web server that
 * Panther boots, and the console commands of the store.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Environment
{
    /**
     * Both the web server and the console are started as child processes, which inherit `$_ENV` -
     * and would otherwise run in the `test` environment of this process, with its dummy API key and
     * without the Docker services of the demo.
     *
     * The variables loaded from the `.env*` files are dropped, so that the child resolves them
     * itself from `.env` and `.env.local` in the `dev` environment. The Docker services are then
     * passed on from `symfony var:export`, the way `symfony serve` does it - without them, the
     * child would look for PostgreSQL on the default port instead of the one Docker exposes.
     */
    public static function expose(): void
    {
        static $prepared = false;

        if ($prepared) {
            return;
        }

        $prepared = true;

        foreach (explode(',', $_SERVER['SYMFONY_DOTENV_VARS'] ?? '') as $name) {
            unset($_ENV[$name]);
        }
        unset($_ENV['SYMFONY_DOTENV_VARS'], $_ENV['SYMFONY_DOTENV_PATH'], $_ENV['APP_ENV'], $_ENV['APP_DEBUG']);

        // PHP's built-in web server handles one request at a time, so the streamed answers of the
        // recipe and the turbo stream bot would block the whole application while they run.
        $_ENV['PHP_CLI_SERVER_WORKERS'] = '4';

        if (null === (new ExecutableFinder())->find('symfony')) {
            return;
        }

        $process = new Process(['symfony', 'var:export'], self::projectDirectory());
        $process->run();

        if (!$process->isSuccessful()) {
            return;
        }

        foreach (explode(' ', trim($process->getOutput())) as $variable) {
            if (str_contains($variable, '=')) {
                [$name, $value] = explode('=', $variable, 2);
                $_ENV[$name] = $value;
            }
        }
    }

    public static function projectDirectory(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * The API keys are read from `.env.local` by the child processes, which run in `dev` - this
     * process runs in `test`, where Dotenv skips that file and only the dummy key of `.env.test`
     * is around.
     */
    public static function isConfigured(string $key): bool
    {
        // Dotenv does not use `putenv()`, so `getenv()` only knows the keys exported in the shell -
        // and not the dummy ones that `.env.test` defines for the default test suite.
        if (false !== $exported = getenv($key)) {
            return '' !== $exported;
        }

        $dotenv = self::projectDirectory().'/.env.local';
        $local = is_file($dotenv) ? (file_get_contents($dotenv) ?: '') : '';

        return 1 === preg_match('/^'.preg_quote($key, '/').'=\s*[\'"]?\S/m', $local);
    }
}
