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
        ->stringNode('host_url')->defaultValue('http://127.0.0.1:11434')->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use')
        ->end()
        ->booleanNode('api_catalog')
            ->info('If set, the Ollama API will be used to build the catalog and retrieve models information, using this option leads to additional HTTP calls')
        ->end()
    ->end();
