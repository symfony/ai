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
            ->arrayNode('setup_options')
                ->children()
                    ->variableNode('fields')
                        ->defaultValue([])
                    ->end()
                ->end()
            ->end()
        ->end()
    ->end();
