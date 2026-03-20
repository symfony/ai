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

return (new ArrayNodeDefinition('sqlite'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('dsn')->cannotBeEmpty()->end()
            ->stringNode('connection')->cannotBeEmpty()->end()
            ->stringNode('table_name')->end()
            ->stringNode('strategy')->end()
            ->booleanNode('vec')->defaultFalse()->end()
            ->enumNode('distance')->values(['cosine', 'L2'])->defaultValue('cosine')->end()
            ->integerNode('vector_dimension')->defaultValue(1536)->end()
        ->end()
        ->validate()
            ->ifTrue(static function ($v) {
                $hasDsn = isset($v['dsn']) && null !== $v['dsn'];
                $hasConnection = isset($v['connection']) && null !== $v['connection'];

                return $hasDsn && $hasConnection;
            })
            ->thenInvalid('Cannot use both "dsn" and "connection" for SQLite store. Choose one.')
        ->end()
        ->validate()
            ->ifTrue(static function ($v) {
                $hasDsn = isset($v['dsn']) && null !== $v['dsn'];
                $hasConnection = isset($v['connection']) && null !== $v['connection'];

                return !$hasDsn && !$hasConnection;
            })
            ->thenInvalid('Either "dsn" or "connection" must be configured for SQLite store.')
        ->end()
    ->end();
