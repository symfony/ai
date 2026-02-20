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

return (new ArrayNodeDefinition('qdrant'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->cannotBeEmpty()->end()
            ->stringNode('api_key')->cannotBeEmpty()->end()
            ->stringNode('collection_name')->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->stringNode('distance')
                ->defaultValue('Cosine')
            ->end()
            ->booleanNode('async')->end()
            ->booleanNode('hybrid_enabled')->defaultFalse()->end()
            ->scalarNode('dense_vector_name')->defaultValue('dense')->end()
            ->scalarNode('sparse_vector_name')->defaultValue('bm25')->end()
        ->end()
    ->end();
