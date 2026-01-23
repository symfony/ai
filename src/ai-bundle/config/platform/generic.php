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

return (new ArrayNodeDefinition('generic'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('base_url')->isRequired()->end()
            ->stringNode('api_key')->end()
            ->stringNode('http_client')
                ->defaultValue('http_client')
                ->info('Service ID of the HTTP client to use')
            ->end()
            ->stringNode('model_catalog')
                ->info('Service ID of the model catalog to use')
            ->end()
            ->booleanNode('supports_completions')->defaultTrue()->end()
            ->booleanNode('supports_embeddings')->defaultTrue()->end()
            ->stringNode('completions_path')->defaultValue('/v1/chat/completions')->end()
            ->stringNode('embeddings_path')->defaultValue('/v1/embeddings')->end()
        ->end()
    ->end();
