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
    public function __construct(
        private string $cacheDir,
        private ContainerProvider $provider,
    ) {
    }

    /**
     * @param string|null $query Filter by service ID or class name (case-insensitive partial match)
     * @param string|null $tag   Filter by DI tag name (e.g. kernel.event_listener, twig.extension)
     */
    #[McpTool(name: 'symfony-services', title: 'Symfony Services', description: 'Search Symfony dependency injection container services. Optionally filter by service ID, class name, or tag name. Returns a map of service IDs to their class names.')]
    public function getServices(?string $query = null, ?string $tag = null): string
    {
        $container = $this->readContainer();
        if (null === $container) {
            return ResponseEncoder::encode([]);
        }

        $output = [];
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

            $output[$service->getId()] = $service->getClass();
        }

        return ResponseEncoder::encode($output);
    }

    /**
     * @param string $id The exact service ID to retrieve details for
     */
    #[McpTool(name: 'symfony-service-detail', title: 'Symfony Service Detail', description: 'Get full details of a single Symfony DI container service by its exact ID, including class, tags, method calls, and constructor/factory information.')]
    public function getServiceDetail(string $id): string
    {
        $container = $this->readContainer();
        if (null === $container) {
            throw new ServiceNotFoundException(\sprintf('Service "%s" not found: container could not be loaded.', $id));
        }

        $services = $container->getServices();
        if (!isset($services[$id])) {
            throw new ServiceNotFoundException(\sprintf('Service "%s" not found in the container.', $id));
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

        return ResponseEncoder::encode($output);
    }

    private function readContainer(): ?Container
    {
        $environments = ['', '/dev', '/test', '/prod'];
        foreach ($environments as $env) {
            $dir = $this->cacheDir.$env;
            $files = glob($dir.'/*DebugContainer.xml');
            if (false !== $files && [] !== $files) {
                sort($files);

                return $this->provider->getContainer($files[0]);
            }
        }

        return null;
    }
}
