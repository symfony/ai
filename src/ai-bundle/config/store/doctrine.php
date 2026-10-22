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

return (new ArrayNodeDefinition('doctrine'))
    ->info('Stores vectors in a column of the table a Doctrine entity already lives in, and returns entities when queried.')
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('entity')
                ->info('FQCN of the entity holding the vectors.')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->stringNode('vector_field')
                ->info('The entity field the vectors are stored in.')
                ->defaultValue('embedding')
            ->end()
            ->enumNode('distance')
                ->info('Distance metric to use for vector similarity search.')
                ->values(['cosine', 'euclidean', 'inner_product'])
                ->defaultValue('cosine')
            ->end()
            ->stringNode('entity_manager')
                ->info('Service id of the entity manager owning the entity.')
                ->defaultValue('doctrine.orm.entity_manager')
            ->end()
            ->stringNode('index_name')
                ->info('Name of the vector index. Defaults to "<table>_<column>_idx".')
                ->defaultNull()
            ->end()
            ->arrayNode('setup_options')
                ->children()
                    ->integerNode('dimensions')->min(1)->defaultValue(1536)->end()
                ->end()
            ->end()
        ->end()
    ->end();
