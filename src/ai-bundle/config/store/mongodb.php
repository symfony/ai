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

use MongoDB\Client as MongoDbClient;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

return (new ArrayNodeDefinition('mongodb'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('client')
                ->cannotBeEmpty()
                ->defaultValue(MongoDbClient::class)
            ->end()
            ->stringNode('database')->isRequired()->end()
            ->stringNode('collection')->end()
            ->stringNode('index_name')->isRequired()->end()
            ->stringNode('vector_field')
                ->defaultValue('vector')
            ->end()
            ->booleanNode('bulk_write')
                ->defaultValue(false)
            ->end()
            ->integerNode('dimensions')
                ->info('Number of dimensions of the vector index created by "ai:store:setup"')
            ->end()
            ->enumNode('metric')
                ->info('Distance function of the vector index created by "ai:store:setup"')
                ->values(['euclidean', 'cosine', 'dotProduct'])
            ->end()
            ->variableNode('index_fields')
                ->info('Extra fields of the vector index created by "ai:store:setup", like filter fields')
            ->end()
        ->end()
    ->end();
