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

return (new ArrayNodeDefinition('acp'))
    ->children()
        ->stringNode('name')
            ->defaultValue('acp')
            ->cannotBeEmpty()
        ->end()
        ->stringNode('command')->end()
        ->stringNode('working_directory')->end()
        ->arrayNode('environment')
            ->useAttributeAsKey('name')
            ->scalarPrototype()->end()
        ->end()
    ->end();
