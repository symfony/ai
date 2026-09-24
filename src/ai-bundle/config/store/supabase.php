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

return (new ArrayNodeDefinition('supabase'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('http_client')
                ->cannotBeEmpty()
                ->info('Service ID of the HTTP client to use')
            ->end()
            ->stringNode('url')->cannotBeEmpty()->end()
            ->stringNode('api_key')->cannotBeEmpty()->end()
            ->stringNode('table')->end()
            ->stringNode('vector_field')
                ->defaultValue('embedding')
            ->end()
            ->integerNode('vector_dimension')
                ->defaultValue(1536)
            ->end()
            ->stringNode('function_name')
                ->defaultValue('match_documents')
            ->end()
        ->end()
        ->validate()
            ->ifTrue(static fn ($v): bool => !isset($v['url']) && !isset($v['http_client']))
            ->thenInvalid('Either "url" or "http_client" must be configured.')
        ->end()
        ->validate()
            ->ifTrue(static fn ($v): bool => !isset($v['url']) && isset($v['api_key']))
            ->thenInvalid('The "api_key" requires a "url", configure it on the "http_client" otherwise.')
        ->end()
    ->end();
