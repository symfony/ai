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

return (new ArrayNodeDefinition('redis'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->variableNode('connection_parameters')
                ->info('see https://github.com/phpredis/phpredis?tab=readme-ov-file#example-1')
                ->cannotBeEmpty()
            ->end()
            ->stringNode('client')
                ->info('a service id of a Redis client')
                ->cannotBeEmpty()
            ->end()
            ->stringNode('endpoint')->cannotBeEmpty()->end()
            ->stringNode('index_name')->cannotBeEmpty()->end()
        ->end()
        ->validate()
            ->ifTrue(static fn (array $v): bool => !isset($v['connection_parameters']) && !isset($v['client']))
            ->thenInvalid('Either "connection_parameters" or "client" must be configured.')
        ->end()
        ->validate()
            ->ifTrue(static fn (array $v): bool => isset($v['connection_parameters']) && isset($v['client']))
            ->thenInvalid('Either "connection_parameters" or "client" can be configured, but not both.')
        ->end()
    ->end();
