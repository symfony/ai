<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Capability;

use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\Mate\Bridge\Symfony\Exception\ServiceNotFoundException;
use Symfony\AI\Mate\Bridge\Symfony\Model\Container;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ServiceTool
{
    private const ENVIRONMENTS = ['', '/dev', '/test', '/prod'];

    /**
     * @var array<string|int, string>
     */
    private readonly array $cacheDirs;

    private readonly bool $hasContexts;

    /**
     * @param string|array<string, string> $cacheDir A single cache directory, or a map of context name to cache
     *                                               directory for multi-kernel (APP_ID) applications
     */
    public function __construct(
        string|array $cacheDir,
        private ContainerProvider $provider,
    ) {
        $this->hasContexts = \is_array($cacheDir);
        $this->cacheDirs = \is_string($cacheDir) ? [0 => $cacheDir] : $cacheDir;
    }

    /**
     * @param string|null $query   Filter by service ID or class name (case-insensitive partial match)
     * @param string|null $tag     Filter by DI tag name (e.g. kernel.event_listener, twig.extension)
     * @param string|null $context Filter by Symfony kernel context, only relevant when multiple cache directories are configured
     */
    #[McpTool(name: 'symfony-services', title: 'Symfony Services', description: 'Search Symfony dependency injection container services. Optionally filter by service ID, class name, or tag name. Returns a map of service IDs to their class names. When multiple kernel contexts are configured, the map is nested per context and can be narrowed with the context parameter.')]
    public function getServices(?string $query = null, ?string $tag = null, ?string $context = null): string
    {
        $containers = $this->readContainers($context);

        if (!$this->hasContexts) {
            $container = $containers[0] ?? null;
            if (null === $container) {
                return ResponseEncoder::encode([]);
            }

            return ResponseEncoder::encode($this->collectServices($container, $query, $tag));
        }

        $output = [];
        foreach ($containers as $containerContext => $container) {
            $output[$containerContext] = $this->collectServices($container, $query, $tag);
        }

        return ResponseEncoder::encode($output);
    }

    /**
     * @param string      $id      The exact service ID to retrieve details for
     * @param string|null $context Filter by Symfony kernel context, only relevant when multiple cache directories are configured
     */
    #[McpTool(name: 'symfony-service-detail', title: 'Symfony Service Detail', description: 'Get full details of a single Symfony DI container service by its exact ID, including class, tags, method calls, and constructor/factory information. When multiple kernel contexts are configured, the containers are searched in order and the result carries the context it was found in.')]
    public function getServiceDetail(string $id, ?string $context = null): string
    {
        $containers = $this->readContainers($context);
        if ([] === $containers) {
            throw new ServiceNotFoundException(\sprintf('Service "%s" not found: container could not be loaded.', $id));
        }

        foreach ($containers as $containerContext => $container) {
            $services = $container->getServices();
            if (!isset($services[$id])) {
                continue;
            }

            $service = $services[$id];

            $tags = [];
            foreach ($service->getTags() as $tag) {
                $entry = ['name' => $tag->getName()];
                foreach ($tag->getAttributes() as $key => $value) {
                    $entry[$key] = $value;
                }
                $tags[] = $entry;
            }

            [$factoryClass, $factoryMethod] = $service->getConstructor();
            $constructor = null;
            if (null !== $factoryClass) {
                $constructor = $factoryClass.'::'.$factoryMethod;
            }

            $output = [
                'id' => $service->getId(),
                'class' => $service->getClass(),
                'tags' => $tags,
                'calls' => $service->getCalls(),
            ];

            if (null !== $constructor) {
                $output['factory'] = $constructor;
            }

            if (\is_string($containerContext)) {
                $output['context'] = $containerContext;
            }

            return ResponseEncoder::encode($output);
        }

        throw new ServiceNotFoundException(\sprintf('Service "%s" not found in the container.', $id));
    }

    /**
     * @return array<string, string|null>
     */
    private function collectServices(Container $container, ?string $query, ?string $tag): array
    {
        $services = [];
        foreach ($container->getServices() as $service) {
            if (null !== $query && '' !== $query) {
                $matches = str_contains(strtolower($service->getId()), strtolower($query))
                    || (null !== $service->getClass() && str_contains(strtolower($service->getClass()), strtolower($query)));
                if (!$matches) {
                    continue;
                }
            }

            if (null !== $tag && '' !== $tag) {
                $hasTag = false;
                foreach ($service->getTags() as $serviceTag) {
                    if ($serviceTag->getName() === $tag) {
                        $hasTag = true;
                        break;
                    }
                }
                if (!$hasTag) {
                    continue;
                }
            }

            $services[$service->getId()] = $service->getClass();
        }

        return $services;
    }

    /**
     * @return array<string|int, Container>
     */
    private function readContainers(?string $context = null): array
    {
        $containers = [];
        foreach ($this->cacheDirs as $cacheContext => $cacheDir) {
            if (null !== $context && '' !== $context && $cacheContext !== $context) {
                continue;
            }

            $container = $this->readContainer($cacheDir);
            if (null === $container) {
                continue;
            }

            $containers[$cacheContext] = $container;
        }

        return $containers;
    }

    private function readContainer(string $cacheDir): ?Container
    {
        foreach (self::ENVIRONMENTS as $env) {
            $dir = $cacheDir.$env;
            $files = glob($dir.'/*DebugContainer.xml');
            if (false !== $files && [] !== $files) {
                sort($files);

                return $this->provider->getContainer($files[0]);
            }
        }

        return null;
    }
}
