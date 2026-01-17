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

use Symfony\AI\McpBundle\Security\RoleScopeExtractor;
use Symfony\AI\McpBundle\Security\ScopeChecker;
use Symfony\AI\McpBundle\Security\ScopeCheckerInterface;
use Symfony\AI\McpBundle\Security\ScopeExtractorInterface;
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
        $this->processServiceLocator($container);
        $this->processScopeRequirements($container);
    }

    private function processServiceLocator(ContainerBuilder $container): void
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
    }

    private function processScopeRequirements(ContainerBuilder $container): void
    {
        if (!$container->has('security.token_storage')) {
            return;
        }

        if (!$container->hasDefinition('mcp.server.controller')) {
            return;
        }

        // Build scope maps by MCP name
        $toolScopes = [];
        $promptScopes = [];
        $resourceScopes = [];

        $toolServices = $container->findTaggedServiceIds('mcp.tool');
        $promptServices = $container->findTaggedServiceIds('mcp.prompt');
        $resourceServices = $container->findTaggedServiceIds('mcp.resource');

        foreach ($container->findTaggedServiceIds('mcp.require_scope') as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $scopes = $attributes['scopes'] ?? [];

                if (isset($toolServices[$serviceId])) {
                    $name = $toolServices[$serviceId][0]['name'] ?? null;
                    if ($name) {
                        $toolScopes[$name] = array_merge($toolScopes[$name] ?? [], $scopes);
                    }
                } elseif (isset($promptServices[$serviceId])) {
                    $name = $promptServices[$serviceId][0]['name'] ?? null;
                    if ($name) {
                        $promptScopes[$name] = array_merge($promptScopes[$name] ?? [], $scopes);
                    }
                } elseif (isset($resourceServices[$serviceId])) {
                    $uri = $resourceServices[$serviceId][0]['uri'] ?? null;
                    if ($uri) {
                        $resourceScopes[$uri] = array_merge($resourceScopes[$uri] ?? [], $scopes);
                    }
                }
            }
        }

        // Register scope checker if there are scopes to check
        if ([] !== $toolScopes || [] !== $promptScopes || [] !== $resourceScopes) {
            $this->registerScopeChecker($container, $toolScopes, $promptScopes, $resourceScopes);
        }
    }

    /**
     * @param array<string, list<string>> $toolScopes
     * @param array<string, list<string>> $promptScopes
     * @param array<string, list<string>> $resourceScopes
     */
    private function registerScopeChecker(
        ContainerBuilder $container,
        array $toolScopes,
        array $promptScopes,
        array $resourceScopes,
    ): void {
        // Register scope extractor
        $container->register('mcp.security.scope_extractor', RoleScopeExtractor::class);
        $container->setAlias(ScopeExtractorInterface::class, 'mcp.security.scope_extractor');

        // Register scope checker
        $scopeChecker = (new Definition(ScopeChecker::class))
            ->setArguments([
                new Reference('security.token_storage'),
                new Reference('mcp.security.scope_extractor'),
                $toolScopes,
                $promptScopes,
                $resourceScopes,
            ]);

        $container->setDefinition('mcp.security.scope_checker', $scopeChecker);
        $container->setAlias(ScopeCheckerInterface::class, 'mcp.security.scope_checker');

        // Inject scope checker into controller
        $container->getDefinition('mcp.server.controller')
            ->setArgument('$scopeChecker', new Reference('mcp.security.scope_checker'));
    }
}
