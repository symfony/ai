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

use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Controller\McpHttpStreamController;
use Symfony\AI\McpBundle\Controller\McpSseController;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Server\NotificationHandlerInterface;
use Symfony\AI\McpSdk\Server\RequestHandlerInterface;
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

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->setParameter('mcp.app', $config['app']);
        $builder->setParameter('mcp.version', $config['version']);
        $builder->setParameter('mcp.page_size', $config['page_size']);
        $builder->setParameter('mcp.http_stream.session.ttl', 3600);

        if (isset($config['client_transports'])) {
            $this->configureClient($config['client_transports'], $builder);
        }

        $builder
            ->registerForAutoconfiguration(IdentifierInterface::class)
            ->addTag('mcp.tool')
        ;
    }

    /**
     * @param array{stdio: bool, sse: bool, http_stream: bool} $transports
     */
    private function configureClient(array $transports, ContainerBuilder $container): void
    {
        if (!$transports['stdio'] && !$transports['sse'] && !$transports['http_stream']) {
            return;
        }

        $container->registerForAutoconfiguration(NotificationHandlerInterface::class)
            ->addTag('mcp.server.notification_handler');
        $container->registerForAutoconfiguration(RequestHandlerInterface::class)
            ->addTag('mcp.server.request_handler');

        if ($transports['stdio']) {
            $container->register('mcp.server.command', McpCommand::class)
                ->setArguments([
                    new Reference('mcp.server'),
                ])
                ->addTag('console.command');
        }

        if ($transports['sse']) {
            $container->register('mcp.server.sse.controller', McpSseController::class)
                ->setArguments([
                    new Reference('mcp.server'),
                    new Reference('mcp.server.sse.store.cache_pool'),
                    new Reference('router'),
                ])
                ->setPublic(true)
                ->addTag('controller.service_arguments');
        }

        if ($transports['http_stream']) {
            $container->register('mcp.server.http_stream.controller', McpHttpStreamController::class)
                ->setArguments([
                    new Reference('mcp.server.json_rpc'),
                    new Reference('mcp.message_factory'),
                    new Reference('mcp.server.http_stream.session.factory'),
                ])
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;
            $container->setAlias(McpHttpStreamController::class, 'mcp.server.http_stream.controller');
        }

        $container->register('mcp.server.route_loader', RouteLoader::class)
            ->setArgument(0, $transports['sse'])
            ->addTag('routing.route_loader');
    }
}
