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

return (new ArrayNodeDefinition('clickhouse'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('dsn')->cannotBeEmpty()->end()
            ->stringNode('http_client')->cannotBeEmpty()->end()
            ->stringNode('database')->isRequired()->cannotBeEmpty()->end()
            ->stringNode('table')->isRequired()->cannotBeEmpty()->end()
        ->end()
        ->validate()
            ->ifTrue(static fn ($v): bool => !isset($v['dsn']) && !isset($v['http_client']))
            ->thenInvalid('Either "dsn" or "http_client" must be configured.')
        ->end()
    ->end();
