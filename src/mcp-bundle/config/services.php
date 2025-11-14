<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Mcp\Server;
use Mcp\Server\Builder;
use Symfony\Bundle\MonologBundle\MonologBundle;

return static function (ContainerConfigurator $container): void {
    if (class_exists(MonologBundle::class)) {
        $container->services()
            ->set('monolog.logger.mcp')
                ->parent('monolog.logger_prototype')
                ->args(['mcp'])
                ->tag('monolog.logger', ['channel' => 'mcp'])
        ;
    }

    $builderDefinition = $container->services()
        ->set('mcp.server.builder', Builder::class)
            ->factory([Server::class, 'builder'])
            ->call('setServerInfo', [param('mcp.app'), param('mcp.version')])
            ->call('setPaginationLimit', [param('mcp.pagination_limit')])
            ->call('setInstructions', [param('mcp.instructions')])
            ->call('setEventDispatcher', [service('event_dispatcher')])
            ->call('setSession', [service('mcp.session.store')])
            ->call('setDiscovery', [param('kernel.project_dir'), param('mcp.discovery.scan_dirs'), param('mcp.discovery.exclude_dirs')]);

    if (class_exists(MonologBundle::class)) {
        $builderDefinition->call('setLogger', [service('monolog.logger.mcp')]);
    }

    $container->services()
        ->set('mcp.server', Server::class)
            ->factory([service('mcp.server.builder'), 'build'])

    ;
};
