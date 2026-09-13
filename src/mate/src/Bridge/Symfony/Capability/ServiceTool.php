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

use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Bridge\Symfony\Exception\ContainerNotDumpedException;
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
     * The container of a real application holds thousands of services, so an unfiltered call
     * would spend the whole context window on one answer.
     */
    private const DEFAULT_LIMIT = 100;

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
     * @param int         $limit   Maximum number of services to return, per context
     */
    #[MateTool(name: 'symfony-services', title: 'Symfony Services', description: 'Search Symfony dependency injection container services. Optionally filter by service ID, class name, or tag name. Returns the matches under "services", the number of matches as "count", and whether the map was cut short as "truncated". When multiple kernel contexts are configured, that result is nested per context and can be narrowed with the context parameter.')]
    public function getServices(?string $query = null, ?string $tag = null, ?string $context = null, int $limit = self::DEFAULT_LIMIT): string
    {
        $containers = $this->readContainers($context);

        if (!$this->hasContexts) {
            return ResponseEncoder::encodeUntrusted($this->collectServices($containers[0], $query, $tag, $limit));
        }

        $output = [];
        foreach ($containers as $containerContext => $container) {
            $output[$containerContext] = $this->collectServices($container, $query, $tag, $limit);
        }

        return ResponseEncoder::encodeUntrusted($output);
    }

    /**
     * @param string      $id      The exact service ID to retrieve details for
     * @param string|null $context Filter by Symfony kernel context, only relevant when multiple cache directories are configured
     */
    #[MateTool(name: 'symfony-service-detail', title: 'Symfony Service Detail', description: 'Get full details of a single Symfony DI container service by its exact ID, including class, tags, method calls, and constructor/factory information. When multiple kernel contexts are configured, the containers are searched in order and the result carries the context it was found in.')]
    public function getServiceDetail(string $id, ?string $context = null): string
    {
        $containers = $this->readContainers($context);

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

            return ResponseEncoder::encodeUntrusted($output);
        }

        throw new ServiceNotFoundException(\sprintf('Service "%s" not found in the container.', $id));
    }

    /**
     * @return array{services: array<string, string|null>, count: int, truncated: bool}
     */
    private function collectServices(Container $container, ?string $query, ?string $tag, int $limit): array
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

        $count = \count($services);
        $truncated = $limit > 0 && $count > $limit;
        if ($truncated) {
            $services = \array_slice($services, 0, $limit, true);
        }

        return ['services' => $services, 'count' => $count, 'truncated' => $truncated];
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

        // Answering with an empty result here is indistinguishable from an application that
        // really has no matching service, which sends the caller looking for the wrong thing.
        if ([] === $containers) {
            if (null !== $context && '' !== $context) {
                throw new ContainerNotDumpedException(\sprintf('No compiled container found for context "%s". Known contexts: "%s".', $context, implode('", "', array_map(strval(...), array_keys($this->cacheDirs)))));
            }

            throw new ContainerNotDumpedException(\sprintf('No compiled container found under "%s". Warm the cache first, for example with "bin/console cache:warmup", then run this tool again.', implode('", "', $this->cacheDirs)));
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
