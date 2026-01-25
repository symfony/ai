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

return (new ArrayNodeDefinition('openai'))
    ->children()
        ->stringNode('api_key')->isRequired()->end()
        ->scalarNode('region')
            ->defaultNull()
            ->validate()
                ->ifNotInArray([null, 'EU', 'US'])
                ->thenInvalid('The region must be either "EU" (https://eu.api.openai.com), "US" (https://us.api.openai.com) or null (https://api.openai.com)')
            ->end()
            ->info('The region for OpenAI API (EU, US, or null for default)')
        ->end()
        ->stringNode('http_client')
            ->defaultValue('http_client')
            ->info('Service ID of the HTTP client to use')
        ->end()
    ->end();
