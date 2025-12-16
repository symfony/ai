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
     * @return array<string, class-string|null>
     */
    #[McpTool('symfony-services', 'Get a list of all symfony services')]
    public function getAllServices(): array
    {
        $container = $this->readContainer();
        if (null === $container) {
            return [];
        }

        $output = [];
        foreach ($container->services as $service) {
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
