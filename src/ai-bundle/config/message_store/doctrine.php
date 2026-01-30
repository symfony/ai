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
    ->children()
        ->arrayNode('dbal')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->stringNode('connection')->cannotBeEmpty()->end()
                    ->stringNode('table_name')
                        ->info('The name of the message store will be used if the table_name is not set')
                    ->end()
                ->end()
            ->end()
        ->end()
    ->end();
