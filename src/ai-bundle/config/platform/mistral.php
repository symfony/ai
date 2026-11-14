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

return (new ArrayNodeDefinition('mistral'))
    ->children()
        ->stringNode('api_key')->isRequired()->end()
        ->stringNode('base_url')
            ->defaultValue('https://api.mistral.ai')
            ->info('Base URL of the Mistral API, e.g. "https://api.eu.mistral.ai"')
        ->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use')
        ->end()
        ->append($modelCatalog('static'))
    ->end();
