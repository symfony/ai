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
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class AbstractMcpPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        $taggedServices = $container->findTaggedServiceIds($this->getTag());

        if ([] === $taggedServices) {
            return;
        }

        $serviceLocatorRef = ServiceLocatorTagPass::register($container, $taggedServices);

        $container->getDefinition('mcp.server.builder')
            ->addMethodCall('setContainer', [$serviceLocatorRef]);
    }

    abstract protected function getTag(): string;
}
