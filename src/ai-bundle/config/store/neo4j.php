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

return (new ArrayNodeDefinition('neo4j'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->cannotBeEmpty()->end()
            ->stringNode('username')->cannotBeEmpty()->end()
            ->stringNode('password')->cannotBeEmpty()->end()
            ->stringNode('database')->end()
            ->stringNode('vector_index_name')->cannotBeEmpty()->end()
            ->stringNode('node_name')->cannotBeEmpty()->end()
            ->stringNode('vector_field')
                ->defaultValue('embeddings')
            ->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->stringNode('distance')
                ->defaultValue('cosine')
            ->end()
            ->booleanNode('quantization')->end()
            ->stringNode('http_client')->cannotBeEmpty()->end()
        ->end()
        ->validate()
            ->ifTrue(static fn ($v): bool => !isset($v['endpoint']) && !isset($v['http_client']))
            ->thenInvalid('Either "endpoint" or "http_client" must be configured.')
        ->end()
        ->validate()
            ->ifTrue(static fn ($v): bool => !isset($v['endpoint']) && (isset($v['username']) || isset($v['password'])))
            ->thenInvalid('The "username" and "password" require an "endpoint", configure them on the "http_client" otherwise.')
        ->end()
        ->validate()
            ->ifTrue(static fn ($v): bool => !isset($v['username']) && isset($v['password']))
            ->thenInvalid('The "password" requires a "username".')
        ->end()
    ->end();
