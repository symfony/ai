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

return (new ArrayNodeDefinition('ollama'))
    ->children()
        ->stringNode('endpoint')
            ->defaultNull()
            ->info('Endpoint for Ollama (e.g. "http://127.0.0.1:11434" for local, or a cloud endpoint). If null, the http_client is used as-is and must already be configured with a base URI.')
        ->end()
        ->stringNode('api_key')
            ->defaultNull()
            ->info('API key for Ollama Cloud authentication (optional for local usage)')
        ->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use. When "endpoint" is null, this client must be pre-configured (e.g. with a base_uri).')
        ->end()
        ->booleanNode('api_catalog')
            ->info('If set, the Ollama API will be used to build the catalog and retrieve models information, using this option leads to additional HTTP calls')
        ->end()
    ->end();
