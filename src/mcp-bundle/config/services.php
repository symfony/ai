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

use Http\Discovery\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/*
 * Only the server-agnostic services live here. Everything that carries per-server values
 * (registry, builder, server, session store, controller) is registered per server in
 * McpBundle::configureServer(): those values are method-call arguments on the builder, and
 * method calls cannot be overridden through definition inheritance.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('mcp.psr17_factory', Psr17Factory::class)

        ->set('mcp.psr_http_factory', PsrHttpFactory::class)
            ->args([
                service('mcp.psr17_factory'),
                service('mcp.psr17_factory'),
                service('mcp.psr17_factory'),
                service('mcp.psr17_factory'),
            ])

        ->set('mcp.http_foundation_factory', HttpFoundationFactory::class)
    ;
};
