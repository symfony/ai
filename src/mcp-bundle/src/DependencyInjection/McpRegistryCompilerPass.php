<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to register MCP tools with the Registry.
 *
 * @author Assistant
 */
final class McpRegistryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('mcp.registry')) {
            return;
        }

        $registryDefinition = $container->getDefinition('mcp.registry');

        // Find all services tagged as 'mcp.tool'
        $taggedServices = $container->findTaggedServiceIds('mcp.tool');

        foreach ($taggedServices as $serviceId => $tags) {
            $registryDefinition->addMethodCall('registerToolService', [
                new Reference($serviceId),
            ]);
        }
    }
}
