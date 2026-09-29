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
use Symfony\AI\Mate\Bridge\Symfony\Exception\XmlContainerCouldNotBeLoadedException;
use Symfony\AI\Mate\Bridge\Symfony\Model\Container;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;

/**
 * The Messenger and Doctrine configuration read from the compiled container, placeholders resolved, without booting the kernel.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ContainerConfiguration
{
    private const MAX_DEPTH = 10;
    private const PROCESSORS = ['resolve', 'default', 'string', 'trim', 'base64', 'file'];

    /**
     * @var array<string, string>
     */
    private readonly array $parameters;

    private readonly ?string $compiledProjectDir;

    /**
     * @param list<string> $skipped containers that were tried first and could not be loaded
     */
    private function __construct(
        private readonly Container $container,
        private readonly ProjectEnvironment $environment,
        private readonly string $projectDir,
        public readonly string $path,
        public readonly array $skipped = [],
    ) {
        $this->parameters = $container->getParameters();
        $this->compiledProjectDir = $this->parameters['kernel.project_dir'] ?? null;
    }

    /**
     * Prefers the container of APP_ENV, then dev, test and prod.
     *
     * @throws ContainerNotDumpedException
     */
    public static function load(string $cacheDir, ContainerProvider $provider, ProjectEnvironment $environment, string $projectDir): self
    {
        $skipped = [];
        foreach (array_unique([$environment->appEnv(), 'dev', 'test', 'prod']) as $env) {
            $files = glob($cacheDir.'/'.$env.'/*DebugContainer.xml') ?: [];
            sort($files);
            if ([] === $files) {
                continue;
            }
            try {
                return new self($provider->getContainer($files[0]), $environment, $projectDir, $files[0], $skipped);
            } catch (XmlContainerCouldNotBeLoadedException $e) {
                $skipped[] = \sprintf('%s: %s', $files[0], $e->getMessage());
            }
        }

        throw new ContainerNotDumpedException(\sprintf('No usable compiled container found under "%s".%s The Messenger configuration is read from the container a debug kernel dumps: run "bin/console cache:warmup" once (APP_DEBUG=1), then run this tool again, or use "bin/console messenger:failed:show".', $cacheDir, [] === $skipped ? '' : ' Could not load: '.implode('; ', $skipped).'.'));
    }

    /**
     * The project directory the container was compiled in, as the traces of the workers show it.
     */
    public function compiledProjectDir(): ?string
    {
        return $this->compiledProjectDir;
    }

    /**
     * Every transport a worker can receive from (tag `messenger.receiver`).
     *
     * @return list<array{name: string, dsn: string, options: array<string, string>, failure: bool}>
     */
    public function receivers(): array
    {
        $receivers = [];
        foreach ($this->container->getServices() as $service) {
            foreach ($service->getTags() as $tag) {
                if ('messenger.receiver' !== $tag->getName()) {
                    continue;
                }
                $arguments = $service->getArguments();
                if (!\is_string($arguments[0]['value'] ?? null)) {
                    continue;
                }
                $options = [];
                if ('collection' === ($arguments[1]['type'] ?? null) && \is_array($arguments[1]['value'])) {
                    foreach ($arguments[1]['value'] as $option) {
                        if (null !== $option['key'] && 'scalar' === $option['type'] && \is_scalar($option['value'])) {
                            $options[$option['key']] = (string) $option['value'];
                        }
                    }
                }
                $attributes = $tag->getAttributes();
                $receivers[] = [
                    'name' => (string) ($attributes['alias'] ?? $service->getId()),
                    'dsn' => $arguments[0]['value'],
                    'options' => $options,
                    // Dumped as "true"/"false" by newer XML dumpers, as "1"/"" by DI 7.3.0.
                    'failure' => filter_var($attributes['is_failure_transport'] ?? '', \FILTER_VALIDATE_BOOL),
                ];
            }
        }

        return $receivers;
    }

    /**
     * The scalar parameters of a DoctrineBundle connection (url, driver, host, dbname, path, ...)
     * and its driverOptions, placeholders resolved.
     *
     * @return array<string, mixed>|null null when there is no such connection
     */
    public function doctrineConnection(string $name): ?array
    {
        $service = $this->container->getServices()[\sprintf('doctrine.dbal.%s_connection', $name)] ?? null;
        if (null === $service) {
            return null;
        }

        $params = [];
        $collection = $service->getArguments()[0] ?? null;
        foreach (\is_array($collection['value'] ?? null) ? $collection['value'] : [] as $argument) {
            if (null === $argument['key']) {
                continue;
            }
            if ('driverOptions' === $argument['key'] && \is_array($argument['value'])) {
                foreach ($argument['value'] as $option) {
                    if (null !== $option['key'] && 'scalar' === $option['type']) {
                        $params['driverOptions'][is_numeric($option['key']) ? (int) $option['key'] : $option['key']] = $this->value($option['value']);
                    }
                }
                continue;
            }
            if ('scalar' === $argument['type']) {
                $params[$argument['key']] = $this->value($argument['value']);
            }
        }

        return $params;
    }

    /**
     * Resolves `%env(...)%` and `%parameter%` placeholders.
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
                if (null === $env && !str_starts_with($m[1], 'default:')) {
                    $error = $this->environment->error();
                    throw new FailureTransportNotReadableException(\sprintf('Environment variable not found: "%s".%s', $this->envName($m[1]), null === $error ? '' : ' The .env files could not be loaded: '.$error));
                }

                return (string) $env;
            }

            return $this->parameter($m[2], $depth);
        }, $value);

        return (string) $resolved;
    }

    private function value(mixed $value): mixed
    {
        return \is_string($value) ? $this->resolve($value) : $value;
    }

    private function parameter(string $name, int $depth): string
    {
        if (!isset($this->parameters[$name])) {
            throw new FailureTransportNotReadableException(\sprintf('Container parameter "%s" not found.', $name));
        }

        $value = $this->resolve($this->parameters[$name], $depth + 1);

        // The container may be compiled elsewhere (CI, Docker): remap its project dir to this one.
        $compiled = rtrim((string) $this->compiledProjectDir, '/');
        if ('' !== $compiled && ($value === $compiled || str_starts_with($value, $compiled.'/'))) {
            return $this->projectDir.substr($value, \strlen($compiled));
        }

        return $value;
    }

    /**
     * Returns null when the variable is not set (for "default:" with an empty fallback, too).
     */
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

            return '' === $fallback ? null : $this->parameter($fallback, $depth);
        }

        if (!\in_array($processor, self::PROCESSORS, true)) {
            throw new FailureTransportNotReadableException(\sprintf('The env processor "%s" in "%%env(%s)%%" is not supported here (supported: "%s"); use "bin/console messenger:failed:show" instead.', $processor, $expression, implode('", "', self::PROCESSORS)));
        }

        $value = $this->env($rest, $depth);
        if (null === $value) {
            return null;
        }

        return match ($processor) {
            'resolve' => $this->resolve($value, $depth + 1),
            'trim' => trim($value),
            'base64' => (string) base64_decode(strtr($value, '-_', '+/'), true),
            'file' => is_file($value) ? (string) file_get_contents($value) : throw new FailureTransportNotReadableException(\sprintf('File "%s" of "%%env(%s)%%" not found.', $value, $expression)),
            default => $value,
        };
    }

    private function envName(string $expression): string
    {
        $parts = explode(':', $expression);

        return end($parts);
    }
}
