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

return (new ArrayNodeDefinition('failover'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->arrayNode('platforms')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('platform')->end()
                        ->stringNode('model')->end()
                    ->end()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static fn (string $v): array => ['platform' => $v])
                    ->end()
                ->end()
            ->end()
            ->stringNode('rate_limiter')->cannotBeEmpty()->end()
        ->end()
    ->end();
