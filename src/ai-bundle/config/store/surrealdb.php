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

return (new ArrayNodeDefinition('surrealdb'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->cannotBeEmpty()->end()
            ->stringNode('username')->cannotBeEmpty()->end()
            ->stringNode('password')->cannotBeEmpty()->end()
            ->stringNode('namespace')->cannotBeEmpty()->end()
            ->stringNode('database')->cannotBeEmpty()->end()
            ->stringNode('table')->end()
            ->stringNode('vector_field')
                ->defaultValue('_vectors')
            ->end()
            ->stringNode('strategy')
                ->defaultValue('cosine')
            ->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->booleanNode('namespaced_user')->end()
        ->end()
    ->end();
