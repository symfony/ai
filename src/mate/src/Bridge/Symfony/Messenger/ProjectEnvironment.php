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

/**
 * The environment variables the application would see, without booting it.
 *
 * Reads the `.env` files in the order `Dotenv::loadEnv()` does (`.env` or `.env.dist`, then
 * `.env.local` unless APP_ENV is test, then `.env.$APP_ENV` and `.env.$APP_ENV.local`); a real
 * environment variable of Mate's own process wins over every file, as it does in the application.
 * Without symfony/dotenv only the real environment is used. Nothing is written to the process
 * environment.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProjectEnvironment
{
    /**
     * @var array<string, string>|null
     */
    private ?array $fileValues = null;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function get(string $name): ?string
    {
        $ambient = $this->ambient($name);
        if (null !== $ambient) {
            return $ambient;
        }

        return $this->fileValues()[$name] ?? null;
    }

    public function appEnv(): string
    {
        $value = $this->ambient('APP_ENV') ?? $this->parse($this->baseFile())['APP_ENV'] ?? null;

        return null === $value || '' === $value ? 'dev' : $value;
    }

    /**
     * @return array<string, string>
     */
    private function fileValues(): array
    {
        if (null !== $this->fileValues) {
            return $this->fileValues;
        }

        $appEnv = $this->appEnv();
        $files = [$this->baseFile()];
        if ('test' !== $appEnv) {
            $files[] = '.env.local';
        }
        $files[] = '.env.'.$appEnv;
        $files[] = '.env.'.$appEnv.'.local';

        $values = [];
        foreach ($files as $file) {
            $values = array_merge($values, $this->parse($file));
        }

        return $this->fileValues = $values;
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $file): array
    {
        $path = $this->projectDir.'/'.$file;
        if (!class_exists(Dotenv::class) || !is_file($path)) {
            return [];
        }

        try {
            return (new Dotenv())->parse((string) file_get_contents($path), $path);
        } catch (\Throwable) {
            return [];
        }
    }

    private function baseFile(): string
    {
        return !is_file($this->projectDir.'/.env') && is_file($this->projectDir.'/.env.dist') ? '.env.dist' : '.env';
    }

    private function ambient(string $name): ?string
    {
        foreach ([$_SERVER, $_ENV] as $bag) {
            if (isset($bag[$name]) && \is_string($bag[$name])) {
                return $bag[$name];
            }
        }

        $value = getenv($name);

        return false === $value ? null : $value;
    }
}
