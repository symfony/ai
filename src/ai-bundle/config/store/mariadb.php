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

return (new ArrayNodeDefinition('mariadb'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('connection')->cannotBeEmpty()->end()
            ->stringNode('table_name')->end()
            ->stringNode('index_name')->cannotBeEmpty()->end()
            ->stringNode('vector_field_name')->cannotBeEmpty()->end()
            ->arrayNode('setup_options')
                ->children()
                    ->integerNode('dimensions')->end()
                ->end()
            ->end()
        ->end()
    ->end();
