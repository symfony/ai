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
use Symfony\AI\Mate\Bridge\Symfony\Model\Container;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;

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
     * @param string|null $query Filter services by ID or class name (case-insensitive partial match)
     *
     * @return array<string, class-string|null>
     */
    #[McpTool('symfony-services', 'Search Symfony dependency injection container services. Optionally filter by service ID or class name. Returns a map of service IDs to their class names.')]
    public function getServices(?string $query = null): array
    {
        $container = $this->readContainer();
        if (null === $container) {
            return [];
        }

        $output = [];
        foreach ($container->services as $service) {
            if (null !== $query && '' !== $query) {
                $matches = str_contains(strtolower($service->id), strtolower($query))
                    || (null !== $service->class && str_contains(strtolower($service->class), strtolower($query)));
                if (!$matches) {
                    continue;
                }
            }
            $output[$service->id] = $service->class;
        }

        return $output;
    }

    private function readContainer(): ?Container
    {
        $environments = ['', '/dev', '/test', '/prod'];
        foreach ($environments as $env) {
            $file = $this->cacheDir."$env/App_KernelDevDebugContainer.xml";
            if (file_exists($file)) {
                return $this->provider->getContainer($file);
            }
        }

        return null;
    }
}
