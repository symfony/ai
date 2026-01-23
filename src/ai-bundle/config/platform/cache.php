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
            ->stringNode('platform')->isRequired()->end()
            ->stringNode('service')
                ->info('The cache service id as defined under the "cache" configuration key')
                ->defaultValue('cache.app')
            ->end()
            ->stringNode('cache_key')
                ->info('Key used to store platform results, if not set, the current platform name will be used, the "prompt_cache_key" can be set during platform call to override this value')
            ->end()
            ->integerNode('ttl')->end()
        ->end()
    ->end();
