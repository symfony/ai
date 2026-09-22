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

return (new ArrayNodeDefinition('milvus'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->cannotBeEmpty()->end()
            ->stringNode('api_key')->end()
            ->stringNode('database')->end()
            ->stringNode('collection')->isRequired()->end()
            ->stringNode('vector_field')
                ->defaultValue('_vectors')
            ->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->stringNode('metric_type')
                ->defaultValue('COSINE')
            ->end()
            ->stringNode('http_client')->cannotBeEmpty()->end()
        ->end()
        ->validate()
            ->ifTrue(static fn ($v): bool => !isset($v['endpoint']) && !isset($v['http_client']))
            ->thenInvalid('Either "endpoint" or "http_client" must be configured.')
        ->end()
        ->validate()
            ->ifTrue(static fn ($v): bool => !isset($v['endpoint']) && isset($v['api_key']))
            ->thenInvalid('The "api_key" requires an "endpoint", configure it on the "http_client" otherwise.')
        ->end()
    ->end();
