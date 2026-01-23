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

return (new ArrayNodeDefinition('cloudflare'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('account_id')->cannotBeEmpty()->end()
            ->stringNode('api_key')->cannotBeEmpty()->end()
            ->stringNode('index_name')->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->stringNode('metric')
                ->defaultValue('cosine')
            ->end()
            ->stringNode('endpoint')->end()
        ->end()
    ->end();
