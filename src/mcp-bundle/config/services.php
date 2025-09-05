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
use Mcp\Server\NotificationHandler\InitializedHandler;
use Mcp\Server\RequestHandler\InitializeHandler;
use Mcp\Server\RequestHandler\PingHandler;
use Mcp\Server\Transport\Sse\Store\CachePoolStore;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('mcp.server.notification_handler.initialized', InitializedHandler::class)
            ->args([])
            ->tag('mcp.server.notification_handler')
        ->set('mcp.server.request_handler.initialize', InitializeHandler::class)
            ->args([
                param('mcp.app'),
                param('mcp.version'),
            ])
            ->tag('mcp.server.request_handler')
        ->set('mcp.server.request_handler.ping', PingHandler::class)
            ->args([])
            ->tag('mcp.server.request_handler')
        ->set('mcp.server.request_handler.tool_call', ToolCallHandler::class)
            ->args([
                service('mcp.tool_executor'),
            ])
            ->tag('mcp.server.request_handler')
        ->set('mcp.server.request_handler.tool_list', ToolListHandler::class)
            ->args([
                service('mcp.tool_collection'),
                param('mcp.page_size'),
            ])
            ->tag('mcp.server.request_handler')

        ->set('mcp.message_factory', Factory::class)
            ->args([])
        ->set('mcp.server.json_rpc', JsonRpcHandler::class)
            ->args([
                service('mcp.message_factory'),
                tagged_iterator('mcp.server.request_handler'),
                tagged_iterator('mcp.server.notification_handler'),
                service('logger')->ignoreOnInvalid(),
            ])
        ->set('mcp.server', Server::class)
            ->args([
                service('mcp.server.json_rpc'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->alias(Server::class, 'mcp.server')
        ->set('mcp.server.sse.store.cache_pool', CachePoolStore::class)
            ->args([
                service('cache.app'),
            ])
        ->set('mcp.tool_chain', ToolChain::class)
            ->args([
                tagged_iterator('mcp.tool'),
            ])
            ->alias('mcp.tool_executor', 'mcp.tool_chain')
            ->alias('mcp.tool_collection', 'mcp.tool_chain')
    ;
};
