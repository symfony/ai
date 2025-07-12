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

use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;
use Symfony\AI\McpSdk\Server\NotificationHandler\InitializedHandler;
use Symfony\AI\McpSdk\Server\NotificationHandlerInterface;
use Symfony\AI\McpSdk\Server\RequestHandler\InitializeHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\PingHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolCallHandler;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolListHandler;
use Symfony\AI\McpSdk\Server\RequestHandlerInterface;
use Symfony\AI\McpSdk\Server\Transport\Sse\Store\CachePoolStore;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->instanceof(NotificationHandlerInterface::class)
            ->tag('mcp.server.notification_handler')
        ->instanceof(RequestHandlerInterface::class)
            ->tag('mcp.server.request_handler')

        ->set(InitializedHandler::class)
        ->set(InitializeHandler::class)
        ->args([
            '$name' => param('mcp.app'),
            '$version' => param('mcp.version'),
        ])
        ->set(PingHandler::class)
        ->set(ToolCallHandler::class)
        ->set(ToolListHandler::class)

        ->set('mcp.message_factory', Factory::class)
        ->set('mcp.server.json_rpc', JsonRpcHandler::class)
            ->args([
                '$messageFactory' => service('mcp.message_factory'),
                '$requestHandlers' => tagged_iterator('mcp.server.request_handler'),
                '$notificationHandlers' => tagged_iterator('mcp.server.notification_handler'),
            ])
        ->set('mcp.server', Server::class)
            ->args([
                '$jsonRpcHandler' => service('mcp.server.json_rpc'),
            ])
            ->alias(Server::class, 'mcp.server')
        ->set(CachePoolStore::class)
    ;
};
