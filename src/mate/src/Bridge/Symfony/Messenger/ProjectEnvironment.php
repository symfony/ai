<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Messenger;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\PathException;

/**
 * The environment variables the application would see, without booting it.
 *
 * Runs Symfony's own `Dotenv::bootEnv()` on the project's `.env` (so `.env.local.php`, `.env.local`,
 * `.env.$APP_ENV`, `${VAR}` references and the precedence of real environment variables behave
 * exactly as in the application), reads the result, and restores `$_SERVER` and `$_ENV`. Without
 * symfony/dotenv only the real environment is used.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProjectEnvironment
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    private ?string $error = null;

    public function __construct(string $projectDir)
    {
        if (!class_exists(Dotenv::class)) {
            return;
        }

        $server = $_SERVER;
        $env = $_ENV;
        try {
            (new Dotenv())->bootEnv($projectDir.'/.env');
            foreach ($_ENV + $_SERVER as $name => $value) {
                if (\is_string($name) && \is_scalar($value)) {
                    $this->values[$name] = (string) $value;
                }
            }
        } catch (PathException) {
            // No .env files: only the real environment counts.
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        } finally {
            $_SERVER = $server;
            $_ENV = $env;
        }
    }

    public function get(string $name): ?string
    {
        if (isset($this->values[$name])) {
            return $this->values[$name];
        }

        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_scalar($value) && false !== $value ? (string) $value : null;
    }

    public function appEnv(): string
    {
        $env = $this->get('APP_ENV');

        return null === $env || '' === $env ? 'dev' : $env;
    }

    /**
     * Why the .env files could not be loaded (a syntax error, for example), if they could not.
     */
    public function error(): ?string
    {
        return $this->error;
    }
}
