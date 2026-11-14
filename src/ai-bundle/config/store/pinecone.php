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

return (new ArrayNodeDefinition('pinecone'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('api_key')->isRequired()->cannotBeEmpty()->end()
            ->stringNode('http_client')
                ->defaultValue('http_client')
            ->end()
            ->stringNode('index_name')->isRequired()->end()
            ->stringNode('namespace')->end()
            ->arrayNode('filter')
                ->scalarPrototype()
                    ->defaultValue([])
                ->end()
            ->end()
            ->integerNode('top_k')
                ->defaultValue(3)
            ->end()
            ->integerNode('dimensions')
                ->info('Number of dimensions of the index created by "ai:store:setup"')
                ->min(1)
                ->defaultValue(1536)
            ->end()
            ->enumNode('metric')
                ->info('Distance metric of the index created by "ai:store:setup"')
                ->values(['cosine', 'euclidean', 'dotproduct'])
                ->defaultNull()
            ->end()
            ->stringNode('cloud')
                ->info('Cloud of the serverless index created by "ai:store:setup"')
                ->defaultNull()
            ->end()
            ->stringNode('region')
                ->info('Region of the serverless index created by "ai:store:setup"')
                ->defaultNull()
            ->end()
        ->end()
    ->end();
