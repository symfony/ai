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

return (new ArrayNodeDefinition('supabase'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('http_client')
                ->cannotBeEmpty()
                ->defaultValue('http_client')
                ->info('Service ID of the HTTP client to use')
            ->end()
            ->stringNode('url')->isRequired()->cannotBeEmpty()->end()
            ->stringNode('api_key')->isRequired()->cannotBeEmpty()->end()
            ->stringNode('table')->end()
            ->stringNode('vector_field')
                ->defaultValue('embedding')
            ->end()
            ->integerNode('vector_dimension')
                ->defaultValue(1536)
            ->end()
            ->stringNode('function_name')
                ->defaultValue('match_documents')
            ->end()
        ->end()
    ->end();
