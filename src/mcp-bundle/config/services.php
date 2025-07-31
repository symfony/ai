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

use Symfony\AI\McpBundle\Session\SessionIdentifierResolver;
use Symfony\AI\McpBundle\Session\SessionResolver;
use Symfony\AI\McpBundle\Session\SessionSubscriber;
use Symfony\AI\McpSdk\Capability\ToolChain;
use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;
use Symfony\AI\McpSdk\Server\NotificationHandler\InitializedHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\InitializeHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\PingHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolCallHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolListHandler;
use Symfony\AI\McpSdk\Server\Transport\Sse\Store\CachePoolStore;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\SessionIdentifierFactory;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\SessionPoolStorage;

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
            ->alias(Factory::class, 'mcp.message_factory')
        ->set('mcp.server.json_rpc', JsonRpcHandler::class)
            ->args([
                service('mcp.message_factory'),
                tagged_iterator('mcp.server.request_handler'),
                tagged_iterator('mcp.server.notification_handler'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->alias(JsonRpcHandler::class, 'mcp.server.json_rpc')
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
        ->set('mcp.server.http_stream.session.identifier_factory', SessionIdentifierFactory::class)
            ->args([
                service('security')->nullOnInvalid(),
            ])
            ->alias(SessionIdentifierFactory::class, 'mcp.server.http_stream.session.identifier_factory')
        ->set('mcp.server.http_stream.session.identifier_resolver', SessionIdentifierResolver::class)
            ->tag('controller.argument_value_resolver')
        ->set('mcp.server.http_stream.session.resolver', SessionResolver::class)
            ->tag('controller.argument_value_resolver')
        ->set('mcp.server.http_stream.session.pool', SessionPoolStorage::class)
            ->args([
                service('cache.app'),
                param('mcp.http_stream.session.ttl'),
            ])
            ->alias(Server\Transport\StreamableHttp\SessionStorageInterface::class, 'mcp.server.http_stream.session.pool')
        ->set('mcp.server.http_stream.session.subscriber', SessionSubscriber::class)
            ->args([
                service('mcp.server.http_stream.session.identifier_factory'),
                service('mcp.server.http_stream.session.factory'),
            ])
            ->tag('kernel.event_subscriber')
            ->alias(SessionSubscriber::class, 'mcp.server.http_stream.session.subscriber')
        ->set('mcp.server.http_stream.session.factory', Server\Transport\StreamableHttp\Session\SessionFactory::class)
            ->args([
                service('mcp.server.http_stream.session.identifier_factory'),
                service('mcp.server.http_stream.session.pool'),
            ])
            ->alias(Server\Transport\StreamableHttp\Session\SessionFactory::class, 'mcp.server.http_stream.session.factory')
        ->set('mcp.tool_chain', ToolChain::class)
            ->args([
                tagged_iterator('mcp.tool'),
            ])
            ->alias('mcp.tool_executor', 'mcp.tool_chain')
            ->alias('mcp.tool_collection', 'mcp.tool_chain')
    ;
};
