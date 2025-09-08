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

use Mcp\Capability\Prompt\IdentifierInterface as PromptIdentifierInterface;
use Mcp\Capability\Prompt\PromptGetterInterface;
use Mcp\Capability\Resource\IdentifierInterface as ResourceIdentifierInterface;
use Mcp\Capability\Resource\ResourceReaderInterface;
use Mcp\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\DependencyInjection\McpRegistryCompilerPass;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
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

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new McpRegistryCompilerPass());
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->setParameter('mcp.app', $config['app']);
        $builder->setParameter('mcp.version', $config['version']);
        $builder->setParameter('mcp.page_size', $config['page_size']);
        $builder->setParameter('mcp.discovery.enabled', $config['discovery']['enabled'] ?? true);
        $builder->setParameter('mcp.discovery.directories', $config['discovery']['directories'] ?? ['src']);
        $builder->setParameter('mcp.discovery.exclude', $config['discovery']['exclude'] ?? ['vendor', 'var', 'tests']);
        $builder->setParameter('mcp.server_capabilities', $config['server_capabilities'] ?? []);

        if (isset($config['client_transports'])) {
            $this->configureClient($config['client_transports'], $builder);
        }

        $builder
            ->registerForAutoconfiguration(IdentifierInterface::class)
            ->addTag('mcp.tool')
        ;

        $builder
            ->registerForAutoconfiguration(PromptIdentifierInterface::class)
            ->addTag('mcp.prompt')
        ;

        $builder
            ->registerForAutoconfiguration(PromptGetterInterface::class)
            ->addTag('mcp.prompt')
        ;

        $builder
            ->registerForAutoconfiguration(ResourceIdentifierInterface::class)
            ->addTag('mcp.resource')
        ;

        $builder
            ->registerForAutoconfiguration(ResourceReaderInterface::class)
            ->addTag('mcp.resource')
        ;
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
            ->addTag('routing.route_loader');
    }
}
