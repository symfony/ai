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
use Symfony\AI\Mate\Discovery\ComposerTypeDiscovery;
use Symfony\AI\Mate\Discovery\ServiceDiscovery;
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
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__)));
        $loader->load('default.config.php');

        $enabledExtensions = $this->getEnabledExtensions();

        $container->setParameter('mate.enabled_extensions', $enabledExtensions);
        $container->setParameter('mate.root_dir', $this->rootDir);

        $logger = $container->get('_build.logger');
        \assert($logger instanceof LoggerInterface);

        $discovery = new ComposerTypeDiscovery($this->rootDir, $logger);

        if ([] !== $enabledExtensions) {
            foreach ($discovery->discover($enabledExtensions) as $packageName => $data) {
                $this->loadExtensionIncludes($container, $logger, $packageName, $data['includes']);
            }
        }

        $rootProject = $discovery->discoverRootProject();
        $this->loadUserServices($rootProject, $container, $logger);

        $this->loadUserEnvVar($container);

        $discovery = new Discoverer($logger);
        $extensions = $this->getExtensionsToLoad($container, $logger);
        (new ServiceDiscovery())->registerServices($discovery, $container, $this->rootDir, $extensions);
        $container->setParameter('mate._extensions', $extensions);

        // Remove the logger definition, it's not needed anymore'
        $container->removeDefinition('_build.logger');
        $container->compile(true);

        return $container;
    }

    /**
     * @return array<string, array{dirs: string[], includes: string[]}>
     */
    private function getExtensionsToLoad(ContainerBuilder $container, LoggerInterface $logger): array
    {
        $rootDir = $container->getParameter('mate.root_dir');
        \assert(\is_string($rootDir));

        $packageNames = $container->getParameter('mate.enabled_extensions');
        \assert(\is_array($packageNames));
        /** @var array<int, string> $packageNames */

        /** @var array<string, array{dirs: array<string>, includes: array<string>}> $extensions */
        $extensions = [];

        $discovery = new ComposerTypeDiscovery($rootDir, $logger);
        foreach ($discovery->discover($packageNames) as $packageName => $data) {
            $extensions[$packageName] = $data;
        }

        $extensions['_custom'] = $discovery->discoverRootProject();

        return $extensions;
    }

    /**
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

    private function loadUserEnvVar(ContainerBuilder $container): void
    {
        $envFile = $container->getParameter('mate.env_file');

        if (null === $envFile || !\is_string($envFile) || '' === $envFile) {
            return;
        }

        if (!class_exists(Dotenv::class)) {
            throw MissingDependencyException::forDotenv();
        }

        $extra = [];
        $localFile = $this->rootDir.\DIRECTORY_SEPARATOR.$envFile.\DIRECTORY_SEPARATOR.'.local';
        if (!file_exists($localFile)) {
            $extra[] = $localFile;
        }

        (new Dotenv())->load($this->rootDir.\DIRECTORY_SEPARATOR.$envFile, ...$extra);
    }

    /**
     * @param array{dirs: array<string>, includes: array<string>} $rootProject
     */
    private function loadUserServices(array $rootProject, ContainerBuilder $container, LoggerInterface $logger): void
    {
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
