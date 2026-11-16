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

$modelCatalog = require __DIR__.'/model_catalog.php';

return (new ArrayNodeDefinition('openresponses'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('base_url')->isRequired()->end()
            ->stringNode('api_key')->end()
            ->stringNode('http_client')
                ->defaultValue('http_client')
                ->info('Service ID of the HTTP client to use')
            ->end()
            ->append($modelCatalog('static'))
            ->stringNode('responses_path')->defaultValue('/v1/responses')->end()
        ->end()
    ->end();
