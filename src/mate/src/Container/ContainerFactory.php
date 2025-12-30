<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Container;

use Mcp\Capability\Discovery\Discoverer;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Exception\MissingDependencyException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Factory for building a Symfony DI Container with MCP extension configurations.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ContainerFactory
{
    public function __construct(
        private string $rootDir,
    ) {
    }

    public function create(): ContainerInterface
    {
        $container = new ContainerBuilder();

        $this->registerCoreServices($container);

        $logger = $container->get('_build.logger');
        \assert($logger instanceof LoggerInterface);

        $extensionDiscovery = new ComposerExtensionDiscovery($this->rootDir, $logger);

        $this->loadExtensions($container, $extensionDiscovery, $logger);
        $this->loadUserServices($container, $extensionDiscovery, $logger);
        $this->loadEnvironmentVariables($container);

        // Remove the logger definition, it's not needed anymore'
        $container->removeDefinition('_build.logger');
        $container->compile(true);

        return $container;
    }

    private function registerCoreServices(ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__)));
        $loader->load('default.config.php');
        $container->setParameter('mate.root_dir', $this->rootDir);
    }

    private function loadExtensions(ContainerBuilder $container, ComposerExtensionDiscovery $extensionDiscovery, LoggerInterface $logger): void
    {
        $enabledExtensions = $this->getEnabledExtensions();
        $container->setParameter('mate.enabled_extensions', $enabledExtensions);
        if ([] === $enabledExtensions) {
            $container->setParameter('mate.extensions', [
                '_custom' => $extensionDiscovery->discoverRootProject(),
            ]);

            return;
        }

        $extensions = [];
        foreach ($extensionDiscovery->discover($enabledExtensions) as $packageName => $data) {
            $extensions[$packageName] = $data;
            $this->loadExtensionIncludes($container, $logger, $packageName, $data['includes']);
        }

        $extensions['_custom'] = $extensionDiscovery->discoverRootProject();

        $this->registerServices($container, $extensions, $logger);
        $container->setParameter('mate.extensions', $extensions);
    }

    /**
     * Pre-register all discovered services in the container.
     *
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     */
    private function registerServices(ContainerBuilder $container, array $extensions, LoggerInterface $logger): void
    {
        $discoverer = new Discoverer($logger);
        foreach ($extensions as $data) {
            $discoveryState = $discoverer->discover($this->rootDir, $data['dirs']);
            foreach ($discoveryState->getTools() as $tool) {
                $this->maybeRegisterHandler($container, $tool->handler);
            }

            foreach ($discoveryState->getResources() as $resource) {
                $this->maybeRegisterHandler($container, $resource->handler);
            }

            foreach ($discoveryState->getPrompts() as $prompt) {
                $this->maybeRegisterHandler($container, $prompt->handler);
            }

            foreach ($discoveryState->getResourceTemplates() as $template) {
                $this->maybeRegisterHandler($container, $template->handler);
            }
        }
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function maybeRegisterHandler(ContainerBuilder $container, \Closure|array|string $handler): void
    {
        $className = $this->extractClassName($handler);
        if (null === $className) {
            return;
        }

        if ($container->has($className)) {
            $container->getDefinition($className)
                ->setPublic(true);

            return;
        }

        $container->register($className, $className)
            ->setAutowired(true)
            ->setPublic(true);
    }

    /**
     * @param \Closure|array{0: object|string, 1: string}|string $handler
     */
    private function extractClassName(\Closure|array|string $handler): ?string
    {
        if ($handler instanceof \Closure) {
            return null;
        }

        if (\is_string($handler)) {
            return class_exists($handler) ? $handler : null;
        }

        $class = $handler[0];
        if (\is_object($class)) {
            return $class::class;
        }

        return class_exists($class) ? $class : null;
    }

    /**
     * Look at the `mate/extensions.php` file in the root directory of the project to find enabled extensions.
     *
     * @return string[] Package names
     */
    private function getEnabledExtensions(): array
    {
        $extensionsFile = $this->rootDir.'/mate/extensions.php';

        if (!file_exists($extensionsFile)) {
            return [];
        }

        $extensionsConfig = include $extensionsFile;
        if (!\is_array($extensionsConfig)) {
            return [];
        }

        $enabledExtensions = [];
        foreach ($extensionsConfig as $packageName => $config) {
            if (\is_string($packageName) && \is_array($config) && ($config['enabled'] ?? false)) {
                $enabledExtensions[] = $packageName;
            }
        }

        return $enabledExtensions;
    }

    /**
     * @param string[] $includeFiles
     */
    private function loadExtensionIncludes(ContainerBuilder $container, LoggerInterface $logger, string $packageName, array $includeFiles): void
    {
        foreach ($includeFiles as $includeFile) {
            if (!file_exists($includeFile)) {
                continue;
            }

            try {
                $loader = new PhpFileLoader($container, new FileLocator(\dirname($includeFile)));
                $loader->load(basename($includeFile));

                $logger->debug('Loaded extension include', [
                    'package' => $packageName,
                    'file' => $includeFile,
                ]);
            } catch (\Throwable $e) {
                $logger->warning('Failed to load extension include', [
                    'package' => $packageName,
                    'file' => $includeFile,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function loadEnvironmentVariables(ContainerBuilder $container): void
    {
        $envFile = $container->getParameter('mate.env_file');
        if (!\is_string($envFile) || '' === $envFile) {
            return;
        }

        if (!class_exists(Dotenv::class)) {
            throw MissingDependencyException::forDotenv();
        }

        $extra = [];
        $localFile = $this->rootDir.\DIRECTORY_SEPARATOR.$envFile.'.local';
        if (file_exists($localFile)) {
            $extra[] = $localFile;
        }

        (new Dotenv())->load($this->rootDir.\DIRECTORY_SEPARATOR.$envFile, ...$extra);
    }

    private function loadUserServices(ContainerBuilder $container, ComposerExtensionDiscovery $extensionDiscovery, LoggerInterface $logger): void
    {
        $rootProject = $extensionDiscovery->discoverRootProject();

        $loader = new PhpFileLoader($container, new FileLocator($this->rootDir));
        foreach ($rootProject['includes'] as $include) {
            try {
                $loader->load($include);

                $logger->debug('Loaded user services', [
                    'file' => $include,
                ]);
            } catch (\Throwable $e) {
                $logger->warning('Failed to load user services', [
                    'file' => $include,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
