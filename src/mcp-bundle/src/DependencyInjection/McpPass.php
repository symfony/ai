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

use Mcp\Capability\Registry\ReferenceHandler;
use Symfony\AI\McpBundle\App\McpAppReferenceHandler;
use Symfony\AI\McpBundle\App\McpAppRenderer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class McpPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        $allMcpServices = [];
        $mcpTags = ['mcp.tool', 'mcp.prompt', 'mcp.resource', 'mcp.resource_template'];

        foreach ($mcpTags as $tag) {
            $taggedServices = $container->findTaggedServiceIds($tag);
            $allMcpServices = array_merge($allMcpServices, $taggedServices);
        }

        if ([] === $allMcpServices) {
            return;
        }

        $serviceReferences = [];
        foreach (array_keys($allMcpServices) as $serviceId) {
            $serviceReferences[$serviceId] = new Reference($serviceId);
        }

        $serviceLocatorRef = ServiceLocatorTagPass::register($container, $serviceReferences);
        $container->getDefinition('mcp.server.builder')->addMethodCall('setContainer', [$serviceLocatorRef]);

        $this->configureAppReferenceHandler($container, $serviceLocatorRef);
    }

    /**
     * Wires the {@see McpAppReferenceHandler} that renders MCP App tool templates (declared via
     * {@see \Symfony\AI\McpBundle\Attribute\AsMcpApp}::$toolTemplate or {@see \Symfony\AI\McpBundle\Attribute\AsMcpAppTool})
     * into the tool result's `html` field. It decorates the SDK default handler, which keeps the
     * reflection-derived input schema and argument mapping intact.
     */
    private function configureAppReferenceHandler(ContainerBuilder $container, Reference $serviceLocatorRef): void
    {
        $toolTemplates = $container->hasParameter('mcp.apps.tool_templates')
            ? $container->getParameter('mcp.apps.tool_templates')
            : [];

        if ([] === $toolTemplates) {
            return;
        }

        $innerHandler = new Definition(ReferenceHandler::class, [$serviceLocatorRef]);

        $container->register('mcp.app.reference_handler', McpAppReferenceHandler::class)
            ->setArguments([
                $innerHandler,
                new Reference(McpAppRenderer::SERVICE_ID),
                $toolTemplates,
            ]);

        $container->getDefinition('mcp.server.builder')
            ->addMethodCall('setReferenceHandler', [new Reference('mcp.app.reference_handler')]);
    }
}
