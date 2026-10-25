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

use Probots\Pinecone\Client as PineconeClient;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

return (new ArrayNodeDefinition('pinecone'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('client')
                ->cannotBeEmpty()
                ->defaultValue(PineconeClient::class)
            ->end()
            ->stringNode('index_name')->isRequired()->end()
            ->stringNode('namespace')->end()
            ->arrayNode('filter')
                ->scalarPrototype()
                    ->defaultValue([])
                ->end()
            ->end()
            ->integerNode('top_k')->end()
            ->integerNode('dimensions')
                ->info('Number of dimensions of the index created by "ai:store:setup"')
                ->min(1)
            ->end()
            ->enumNode('metric')
                ->info('Distance metric of the index created by "ai:store:setup"')
                ->values(['cosine', 'euclidean', 'dotproduct'])
            ->end()
            ->stringNode('cloud')
                ->info('Cloud of the serverless index created by "ai:store:setup"')
            ->end()
            ->stringNode('region')
                ->info('Region of the serverless index created by "ai:store:setup"')
            ->end()
        ->end()
    ->end();
