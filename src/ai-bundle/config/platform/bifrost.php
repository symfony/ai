<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Configurator;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

return (new ArrayNodeDefinition('bifrost'))
    ->children()
        ->stringNode('endpoint')->defaultNull()->end()
        ->stringNode('api_key')->defaultNull()->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use. When "endpoint" is null, this client MUST be pre-configured with a base URI (e.g. via framework.http_client.scoped_clients).')
        ->end()
    ->end()
    ->validate()
        ->ifTrue(static fn (array $cfg): bool => null === ($cfg['endpoint'] ?? null) && 'http_client' === ($cfg['http_client'] ?? 'http_client'))
        ->thenInvalid('Either "endpoint" must be set, or a custom "http_client" service with a pre-configured base URI must be provided.')
    ->end();
