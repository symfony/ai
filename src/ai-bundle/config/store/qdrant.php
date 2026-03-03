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

return (new ArrayNodeDefinition('qdrant'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->end()
            ->stringNode('api_key')->end()
            ->stringNode('collection_name')
                ->info('The name of the store will be used if the "collection_name" is not set')
            ->end()
            ->stringNode('http_client')
                ->defaultValue('http_client')
            ->end()
            ->integerNode('dimensions')
                ->defaultValue(1536)
            ->end()
            ->stringNode('distance')
                ->defaultValue('Cosine')
            ->end()
            ->booleanNode('async')
                ->defaultValue(false)
            ->end()
        ->end()
    ->end();
