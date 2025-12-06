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

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('mcp.server.builder', Builder::class)
            ->factory([Server::class, 'builder'])
            ->call('setServerInfo', [param('mcp.app'), param('mcp.version')])
            ->call('setPaginationLimit', [param('mcp.pagination_limit')])
            ->call('setInstructions', [param('mcp.instructions')])
            ->call('setEventDispatcher', [service('event_dispatcher')])
            ->call('setSession', [service('mcp.session.store')])
            ->call('setDiscovery', [param('kernel.project_dir'), param('mcp.discovery.scan_dirs'), param('mcp.discovery.exclude_dirs')])
            ->call('setLogger', [service('logger')])
            ->tag('monolog.logger', ['channel' => 'mcp'])

        ->set('mcp.server', Server::class)
            ->factory([service('mcp.server.builder'), 'build'])
    ;
};
