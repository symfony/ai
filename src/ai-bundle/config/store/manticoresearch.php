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

return (new ArrayNodeDefinition('manticoresearch'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->cannotBeEmpty()->end()
            ->stringNode('table')->end()
            ->stringNode('field')
                ->defaultValue('_vectors')
            ->end()
            ->stringNode('type')
                ->defaultValue('hnsw')
            ->end()
            ->stringNode('similarity')
                ->defaultValue('cosine')
            ->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->stringNode('quantization')->end()
        ->end()
    ->end();
