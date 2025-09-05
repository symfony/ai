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

use Symfony\AI\McpBundle\Registry\SymfonyRegistry;
use Symfony\AI\McpBundle\Registry\SymfonyRegistryFactory;
use Mcp\JsonRpc\Handler;
use Mcp\JsonRpc\MessageFactory;
use Mcp\Schema\Implementation;
use Mcp\Server;
use Mcp\Server\Transport\Sse\Store\CachePoolStore;

return static function (ContainerConfigurator $container): void {
    $container->services()
        // Registry factory for configuring discovery
        ->set('mcp.registry.factory', SymfonyRegistryFactory::class)
            ->args([
                param('kernel.project_dir'),
                param('mcp.discovery.enabled'),
                param('mcp.discovery.directories'),
                param('mcp.discovery.exclude'),
                service('logger')->ignoreOnInvalid(),
            ])

        // Core Registry for managing tools, prompts, and resources
        ->set('mcp.registry', SymfonyRegistry::class)
            ->factory([service('mcp.registry.factory'), 'create'])

        // Implementation info for the server
        ->set('mcp.implementation', Implementation::class)
            ->args([
                param('mcp.app'),
                param('mcp.version'),
            ])

        // Message Factory
        ->set('mcp.message_factory', MessageFactory::class)
            ->factory([MessageFactory::class, 'make'])

        // JSON-RPC Handler with all request and notification handlers
        ->set('mcp.json_rpc_handler', Handler::class)
            ->factory([Handler::class, 'make'])
            ->args([
                service('mcp.registry'),
                service('mcp.implementation'),
                service('logger')->ignoreOnInvalid(),
            ])

        // Main MCP Server
        ->set('mcp.server', Server::class)
            ->args([
                service('mcp.json_rpc_handler'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->alias(Server::class, 'mcp.server')

        // SSE Store for Server-Sent Events transport
        ->set('mcp.server.sse.store.cache_pool', CachePoolStore::class)
            ->args([
                service('cache.app'),
            ])
    ;
};
