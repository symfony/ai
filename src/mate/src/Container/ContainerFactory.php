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

use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\ComposerTypeDiscovery;
use Symfony\AI\Mate\Exception\MissingDependencyException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Factory for building a Symfony DI Container with MCP bridge configurations.
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

    public function create(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__)));
        $loader->load('default.config.php');

        $enabledBridges = $this->getEnabledBridges();

        $container->setParameter('mate.enabled_bridges', $enabledBridges);
        $container->setParameter('mate.root_dir', $this->rootDir);

        $logger = $container->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);

        $discovery = new ComposerTypeDiscovery($this->rootDir, $logger);

        if ([] !== $enabledBridges) {
            foreach ($discovery->discover($enabledBridges) as $packageName => $data) {
                $this->loadBridgeIncludes($container, $logger, $packageName, $data['includes']);
            }
        }

        $rootProject = $discovery->discoverRootProject();
        $this->loadUserServices($rootProject, $container);

        $this->loadUserEnvVar($container);

        return $container;
    }

    /**
     * @return string[] Package names
     */
    private function getEnabledBridges(): array
    {
        $bridgesFile = $this->rootDir.'/.mate/bridges.php';

        if (!file_exists($bridgesFile)) {
            return [];
        }

        $bridgesConfig = include $bridgesFile;
        if (!\is_array($bridgesConfig)) {
            return [];
        }

        $enabledBridges = [];
        foreach ($bridgesConfig as $packageName => $config) {
            if (\is_string($packageName) && \is_array($config) && ($config['enabled'] ?? false)) {
                $enabledBridges[] = $packageName;
            }
        }

        return $enabledBridges;
    }

    /**
     * @param string[] $includeFiles
     */
    private function loadBridgeIncludes(ContainerBuilder $container, LoggerInterface $logger, string $packageName, array $includeFiles): void
    {
        foreach ($includeFiles as $includeFile) {
            if (!file_exists($includeFile)) {
                continue;
            }

            try {
                $loader = new PhpFileLoader($container, new FileLocator(\dirname($includeFile)));
                $loader->load(basename($includeFile));

                $logger->debug('Loaded bridge include', [
                    'package' => $packageName,
                    'file' => $includeFile,
                ]);
            } catch (\Throwable $e) {
                $logger->warning('Failed to load bridge include', [
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
    private function loadUserServices(array $rootProject, ContainerBuilder $container): void
    {
        $logger = $container->get(LoggerInterface::class);
        \assert($logger instanceof LoggerInterface);

        $loader = new PhpFileLoader($container, new FileLocator($this->rootDir.'/.mate'));
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
