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

return (new ArrayNodeDefinition('postgres'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('dsn')->cannotBeEmpty()->end()
            ->stringNode('username')->end()
            ->stringNode('password')->end()
            ->stringNode('table_name')->end()
            ->stringNode('vector_field')
                ->defaultValue('embedding')
            ->end()
            ->enumNode('distance')
                ->info('Distance metric to use for vector similarity search')
                ->values(['cosine', 'inner_product', 'l1', 'l2'])
                ->defaultValue('l2')
            ->end()
            ->stringNode('dbal_connection')->cannotBeEmpty()->end()
        ->end()
        ->validate()
            ->ifTrue(static fn (array $v): bool => !isset($v['dsn']) && !isset($v['dbal_connection']))
            ->thenInvalid('Either "dsn" or "dbal_connection" must be configured.')
        ->end()
        ->validate()
            ->ifTrue(static fn (array $v): bool => isset($v['dsn'], $v['dbal_connection']))
            ->thenInvalid('Either "dsn" or "dbal_connection" can be configured, but not both.')
        ->end()
    ->end();
