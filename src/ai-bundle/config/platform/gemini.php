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

return (new ArrayNodeDefinition('gemini'))
    ->children()
        ->stringNode('api_key')->end()
        ->scalarNode('endpoint')
            ->defaultValue('https://generativelanguage.googleapis.com/')
        ->end()
        ->scalarNode('version')
            ->defaultValue('v1beta')
            ->info('For more informations about available versions, please refer to "https://ai.google.dev/gemini-api/docs/api-versions".')
        ->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use')
        ->end()
    ->end();
