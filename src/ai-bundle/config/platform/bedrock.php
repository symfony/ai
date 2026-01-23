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

return (new ArrayNodeDefinition('bedrock'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('bedrock_runtime_client')
                ->defaultNull()
                ->info('Service ID of the Bedrock runtime client to use')
            ->end()
            ->stringNode('model_catalog')->defaultNull()->end()
        ->end()
    ->end();
