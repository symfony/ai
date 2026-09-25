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

use Symfony\AI\Mate\Bridge\Symfony\Exception\ContainerNotDumpedException;
use Symfony\AI\Mate\Bridge\Symfony\Exception\FailureTransportNotReadableException;

/**
 * The Messenger and Doctrine configuration of the application, read from the compiled container
 * (`var/cache/<env>/*DebugContainer.xml`) with `%env()%` and `%parameter%` placeholders resolved
 * against {@see ProjectEnvironment}. The application is never booted.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ContainerConfiguration
{
    private const MAX_DEPTH = 10;

    /**
     * @var array<string, string>
     */
    private array $parameters = [];

    private function __construct(
        private readonly \SimpleXMLElement $xml,
        private readonly ProjectEnvironment $environment,
        private readonly string $projectDir,
        public readonly string $path,
    ) {
        foreach ($xml->parameters->parameter ?? [] as $parameter) {
            $this->parameters[(string) $parameter['key']] = (string) $parameter;
        }
    }

    /**
     * Prefers the container of the application's APP_ENV, then dev, test and prod.
     *
     * @param list<string> $cacheDirs
     *
     * @throws ContainerNotDumpedException
     */
    public static function load(array $cacheDirs, ProjectEnvironment $environment, string $projectDir): self
    {
        foreach ($cacheDirs as $cacheDir) {
            foreach (array_unique([$environment->appEnv(), 'dev', 'test', 'prod']) as $env) {
                $files = glob($cacheDir.'/'.$env.'/*DebugContainer.xml') ?: [];
                sort($files);
                if ([] !== $files && false !== $xml = @simplexml_load_file($files[0])) {
                    return new self($xml, $environment, $projectDir, $files[0]);
                }
            }
        }

        throw new ContainerNotDumpedException(\sprintf('No compiled container found under "%s". The Messenger configuration is read from the container a debug kernel dumps: run "bin/console cache:warmup" once (APP_DEBUG=1), then run this tool again.', implode('", "', $cacheDirs)));
    }

    /**
     * The project directory the container was compiled in, as the traces of the workers show it.
     */
    public function compiledProjectDir(): ?string
    {
        return $this->parameters['kernel.project_dir'] ?? null;
    }

    /**
     * Every transport a worker can receive from (tag `messenger.receiver`).
     *
     * @return list<array{name: string, dsn: string, options: array<string, string>, failure: bool}>
     */
    public function receivers(): array
    {
        $receivers = [];
        foreach ($this->xml->services->service ?? [] as $service) {
            foreach ($service->tag ?? [] as $tag) {
                if ('messenger.receiver' !== (string) $tag['name'] || !isset($service->argument[0])) {
                    continue;
                }
                $options = [];
                foreach ($service->argument[1]->argument ?? [] as $option) {
                    if (isset($option['key']) && 0 === \count($option->children())) {
                        $options[(string) $option['key']] = (string) $option;
                    }
                }
                $receivers[] = [
                    'name' => (string) $tag['alias'],
                    'dsn' => (string) $service->argument[0],
                    'options' => $options,
                    'failure' => 'true' === (string) $tag['is_failure_transport'],
                ];
            }
        }

        return $receivers;
    }

    /**
     * The scalar parameters of a DoctrineBundle connection (url, driver, host, dbname, path, ...),
     * placeholders resolved.
     *
     * @return array<string, mixed>|null null when there is no such connection
     */
    public function doctrineConnection(string $name): ?array
    {
        foreach ($this->xml->services->service ?? [] as $service) {
            if (\sprintf('doctrine.dbal.%s_connection', $name) !== (string) $service['id']) {
                continue;
            }
            $params = [];
            foreach ($service->argument[0]->argument ?? [] as $argument) {
                if (!isset($argument['key']) || 0 !== \count($argument->children())) {
                    continue;
                }
                $value = $this->resolve((string) $argument);
                $params[(string) $argument['key']] = match (true) {
                    'null' === $value => null,
                    'true' === $value => true,
                    'false' === $value => false,
                    default => $value,
                };
            }

            return $params;
        }

        return null;
    }

    /**
     * Resolves `%env(...)%` (processors: resolve, default, string, trim, base64) and
     * `%parameter%` placeholders.
     *
     * @throws FailureTransportNotReadableException when a placeholder cannot be resolved
     */
    public function resolve(string $value, int $depth = 0): string
    {
        if ($depth > self::MAX_DEPTH) {
            throw new FailureTransportNotReadableException(\sprintf('Circular reference while resolving "%s".', $value));
        }

        $resolved = preg_replace_callback('/%env\(([^)%]+)\)%|%([^%\s]+)%|%%/', function (array $m) use ($depth): string {
            if ('%%' === $m[0]) {
                return '%';
            }
            if ('' !== $m[1]) {
                $env = $this->env($m[1], $depth);
                if (null === $env) {
                    throw new FailureTransportNotReadableException(\sprintf('Environment variable not found: "%s". It is set neither in the real environment nor in the project\'s .env files (APP_ENV=%s).', $this->envName($m[1]), $this->environment->appEnv()));
                }

                return $env;
            }

            return $this->parameter($m[2], $depth);
        }, $value);

        return (string) $resolved;
    }

    private function parameter(string $name, int $depth): string
    {
        // The container may have been compiled elsewhere (a CI or Docker path): the project is
        // where Mate runs.
        if ('kernel.project_dir' === $name) {
            return $this->projectDir;
        }
        if (!isset($this->parameters[$name])) {
            throw new FailureTransportNotReadableException(\sprintf('Container parameter "%s" not found.', $name));
        }

        return $this->resolve($this->parameters[$name], $depth + 1);
    }

    private function env(string $expression, int $depth): ?string
    {
        if (!str_contains($expression, ':')) {
            return $this->environment->get($expression);
        }

        [$processor, $rest] = explode(':', $expression, 2);

        if ('default' === $processor) {
            [$fallback, $inner] = str_contains($rest, ':') ? explode(':', $rest, 2) : ['', $rest];
            $value = $this->env($inner, $depth);
            if (null !== $value && '' !== $value) {
                return $value;
            }

            return '' === $fallback ? '' : $this->parameter($fallback, $depth);
        }

        $value = $this->env($rest, $depth);
        if (null === $value) {
            return null;
        }

        return match ($processor) {
            'resolve' => $this->resolve($value, $depth + 1),
            'string' => $value,
            'trim' => trim($value),
            'base64' => (string) base64_decode(strtr($value, '-_', '+/'), true),
            default => throw new FailureTransportNotReadableException(\sprintf('The env processor "%s" in "%%env(%s)%%" is not supported here; run "bin/console messenger:failed:show" instead.', $processor, $expression)),
        };
    }

    private function envName(string $expression): string
    {
        $parts = explode(':', $expression);

        return end($parts);
    }
}
