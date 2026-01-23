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

return (new ArrayNodeDefinition('meilisearch'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->cannotBeEmpty()->end()
            ->stringNode('api_key')->cannotBeEmpty()->end()
            ->stringNode('index_name')->end()
            ->stringNode('embedder')
                ->defaultValue('default')
            ->end()
            ->stringNode('vector_field')
                ->defaultValue('_vectors')
            ->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->floatNode('semantic_ratio')
                ->info('The ratio between semantic (vector) and full-text search (0.0 to 1.0). Default: 1.0 (100% semantic)')
                ->defaultValue(1.0)
                ->min(0.0)
                ->max(1.0)
            ->end()
        ->end()
    ->end();
