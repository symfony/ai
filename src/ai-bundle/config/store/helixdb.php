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

return (new ArrayNodeDefinition('helixdb'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')
                ->defaultValue('http://127.0.0.1:6969')
            ->end()
            ->stringNode('http_client')
                ->defaultValue('http_client')
            ->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->integerNode('top_k')
                ->defaultValue(5)
            ->end()
        ->end()
    ->end();
