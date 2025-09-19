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
use Mcp\Server\ServerBuilder;
use Mcp\Server\Transport\Sse\Store\CachePoolStore;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('mcp.server.sse.store.cache_pool', CachePoolStore::class)
            ->args([
                service('cache.app'),
            ])
        ->set('mcp.server.builder', ServerBuilder::class)
            ->factory([Server::class, 'make'])
            ->call('setServerInfo', [param('mcp.app'), param('mcp.version')])
            ->call('setPaginationLimit', [param('mcp.pagination_limit')])
            ->call('setInstructions', [param('mcp.instructions')])
            ->call('setLogger', [service('logger')])
            ->call('setDiscovery', [param('kernel.project_dir'), ['src']])

        ->set('mcp.server', Server::class)
            ->factory([service('mcp.server.builder'), 'build'])

    ;
};
