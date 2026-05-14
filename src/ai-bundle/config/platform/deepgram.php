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

return (new ArrayNodeDefinition('deepgram'))
    ->children()
        ->stringNode('endpoint')
            ->defaultValue('https://api.deepgram.com/v1/')
            ->info('Deepgram REST API endpoint')
        ->end()
        ->stringNode('websocket_endpoint')
            ->defaultValue('wss://api.deepgram.com/v1')
            ->info('Deepgram WebSocket endpoint (no trailing slash)')
        ->end()
        ->booleanNode('use_websockets')
            ->defaultFalse()
            ->info('Route requests through the WebSocket transport instead of REST')
        ->end()
        ->stringNode('api_key')
            ->isRequired()
            ->cannotBeEmpty()
        ->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use')
        ->end()
    ->end();
