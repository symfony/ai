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
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\AI\Mate\Exception\MissingDependencyException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Factory for building a Symfony DI Container with extension configurations.
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

        $userSuffix = (getenv('USER') ?: getenv('USERNAME')) ?: 'default';
        $userSuffix = preg_replace('/[^a-zA-Z0-9]/', '', $userSuffix);
        $projectHash = substr(md5($this->rootDir), 0, 8);
        $container->setParameter('mate.cache_dir', sys_get_temp_dir().'/mate/'.$userSuffix.'_'.$projectHash);
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
     * Pre-register the handler classes of all discovered capabilities as public services.
     *
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     */
    private function registerServices(ContainerBuilder $container, array $extensions, LoggerInterface $logger): void
    {
        $discoverer = new ReflectionDiscoverer($logger);
        foreach ($extensions as $data) {
            $capabilities = $discoverer->discover($this->rootDir, $data['dirs']);

            foreach ($capabilities->getTools() as $tool) {
                $this->registerHandler($container, $tool->handlerClass);
            }

            foreach ($capabilities->getResources() as $resource) {
                $this->registerHandler($container, $resource->handlerClass);
            }

            foreach ($capabilities->getResourceTemplates() as $template) {
                $this->registerHandler($container, $template->handlerClass);
            }
        }
    }

    private function registerHandler(ContainerBuilder $container, string $className): void
    {
        if (!class_exists($className)) {
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
            throw new MissingDependencyException('Cannot load any environment file with out Symfony Dotenv. Please run run "composer require symfony/dotenv" and try again.');
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
