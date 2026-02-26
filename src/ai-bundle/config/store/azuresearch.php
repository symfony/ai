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

return (new ArrayNodeDefinition('azuresearch'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->end()
            ->stringNode('api_key')->end()
            ->stringNode('api_version')->end()
            ->stringNode('index_name')
                ->info('The name of the store will be used if the "index_name" option is not set')
            ->end()
            ->stringNode('http_client')
                ->defaultValue('http_client')
            ->end()
            ->stringNode('vector_field')
                ->defaultValue('vector')
            ->end()
        ->end()
    ->end();
