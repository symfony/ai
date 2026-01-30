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
            ->booleanNode('namespaced_user')
                ->info('Using a namespaced user is a good practice to prevent any undesired access to a specific table, see https://surrealdb.com/docs/surrealdb/reference-guide/security-best-practices')
            ->end()
        ->end()
    ->end();
