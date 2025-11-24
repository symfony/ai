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

use Mcp\Capability\Registry;
use Mcp\Server;
use Mcp\Server\Builder;
use Symfony\AI\McpBundle\Profiler\DataCollector;
use Symfony\AI\McpBundle\Profiler\TraceableRegistry;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('monolog.logger.mcp')
            ->parent('monolog.logger_prototype')
            ->args(['mcp'])
            ->tag('monolog.logger', ['channel' => 'mcp'])

        ->set('ai.mcp.registry.inner', Registry::class)
            ->args([service('event_dispatcher'), service('monolog.logger.mcp')])

        ->set('ai.mcp.registry', TraceableRegistry::class)
            ->args([service('ai.mcp.registry.inner')])

        ->set('mcp.server.builder', Builder::class)
            ->factory([Server::class, 'builder'])
            ->call('setServerInfo', [param('mcp.app'), param('mcp.version')])
            ->call('setPaginationLimit', [param('mcp.pagination_limit')])
            ->call('setInstructions', [param('mcp.instructions')])
            ->call('setLogger', [service('monolog.logger.mcp')])
            ->call('setEventDispatcher', [service('event_dispatcher')])
            ->call('setRegistry', [service('ai.mcp.registry')])
            ->call('setSession', [service('mcp.session.store')])
            ->call('setDiscovery', [param('kernel.project_dir'), param('mcp.discovery.scan_dirs'), param('mcp.discovery.exclude_dirs')])

        ->set('mcp.server', Server::class)
            ->factory([service('mcp.server.builder'), 'build'])

        ->set('ai.mcp.data_collector', DataCollector::class)
            ->args([service('ai.mcp.registry')])
            ->tag('data_collector');
};
