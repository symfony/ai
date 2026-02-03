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

return (new ArrayNodeDefinition('cartesia'))
    ->children()
        ->stringNode('api_key')->isRequired()->end()
        ->stringNode('version')->isRequired()->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use')
        ->end()
        ->arrayNode('speech')
            ->children()
                ->stringNode('tts_model')->end()
                ->arrayNode('tts_options')
                ->scalarPrototype()
                    ->defaultValue([])
                    ->end()
                ->end()
                ->stringNode('stt_model')->end()
                ->arrayNode('stt_options')
                ->scalarPrototype()
                    ->defaultValue([])
                    ->end()
                ->end()
            ->end()
        ->end()
    ->end();
