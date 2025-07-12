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
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Routing\RouteLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class McpBundle extends AbstractBundle
{
    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->setParameter('mcp.app', $config['app']);
        $builder->setParameter('mcp.version', $config['version']);

        if (isset($config['client_transports'])) {
            $this->configureClient($config['client_transports'], $builder);
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
                ->setAutowired(true)
                ->addTag('console.command');
        }

        if ($transports['sse']) {
            $container->register('mcp.server.controller', McpController::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments');
        }

        $container->register('mcp.server.route_loader', RouteLoader::class)
            ->setArgument('$sseTransportEnabled', $transports['sse'])
            ->addTag('routing.route_loader');
    }
}
