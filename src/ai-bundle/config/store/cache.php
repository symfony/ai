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

return (new ArrayNodeDefinition('cache'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('service')->cannotBeEmpty()->defaultValue('cache.app')->end()
            ->stringNode('cache_key')
                ->info('The name of the store will be used if the key is not set')
            ->end()
            ->stringNode('strategy')->end()
        ->end()
    ->end();
