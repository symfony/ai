<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\DependencyInjection\McpPromptPass;
use Symfony\AI\McpBundle\DependencyInjection\McpResourcePass;
use Symfony\AI\McpBundle\DependencyInjection\McpResourceTemplatePass;
use Symfony\AI\McpBundle\DependencyInjection\McpToolPass;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class McpBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/options.php');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->setParameter('mcp.app', $config['app']);
        $builder->setParameter('mcp.version', $config['version']);
        $builder->setParameter('mcp.pagination_limit', $config['pagination_limit']);
        $builder->setParameter('mcp.instructions', $config['instructions']);

        $this->registerMcpAttributes($builder);

        if (isset($config['client_transports'])) {
            $this->configureClient($config['client_transports'], $builder);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new McpToolPass());
        $container->addCompilerPass(new McpPromptPass());
        $container->addCompilerPass(new McpResourcePass());
        $container->addCompilerPass(new McpResourceTemplatePass());
    }

    private function registerMcpAttributes(ContainerBuilder $builder): void
    {
        $mcpAttributes = [
            McpTool::class => 'mcp.tool',
            McpPrompt::class => 'mcp.prompt',
            McpResource::class => 'mcp.resource',
            McpResourceTemplate::class => 'mcp.resource_template',
        ];

        foreach ($mcpAttributes as $attributeClass => $tag) {
            $builder->registerAttributeForAutoconfiguration(
                $attributeClass,
                static function (ChildDefinition $definition) use ($tag): void {
                    $definition->addTag($tag);
                }
            );
        }
    }

    /**
     * @param array{stdio: bool, sse: bool} $transports
     */
    private function configureClient(array $transports, ContainerBuilder $container): void
    {
        if (!$transports['stdio'] && !$transports['sse']) {
            return;
        }

        if ($transports['stdio']) {
            $container->register('mcp.server.command', McpCommand::class)
                ->setArguments([
                    new Reference('mcp.server'),
                    new Reference('logger'),
                ])
                ->addTag('console.command');
        }

        if ($transports['sse']) {
            $container->register('mcp.server.controller', McpController::class)
                ->setArguments([
                    new Reference('mcp.server'),
                    new Reference('mcp.server.sse.store.cache_pool'),
                    new Reference('router'),
                ])
                ->setPublic(true)
                ->addTag('controller.service_arguments');
        }

        $container->register('mcp.server.route_loader', RouteLoader::class)
            ->setArgument(0, $transports['sse'])
            ->addTag('routing.loader');
    }
}
