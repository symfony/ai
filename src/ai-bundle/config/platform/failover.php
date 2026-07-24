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
                        ->variableNode('model')
                            ->validate()
                                ->ifTrue(static function (mixed $v): bool {
                                    if (null === $v || (\is_string($v) && '' !== $v)) {
                                        return false;
                                    }

                                    if (\is_array($v)) {
                                        foreach ($v as $item) {
                                            if (!\is_string($item) || '' === $item) {
                                                return true;
                                            }
                                        }

                                        return [] === $v;
                                    }

                                    return true;
                                })
                                ->thenInvalid('The "model" option must be a non-empty string or a non-empty array of non-empty strings.')
                            ->end()
                        ->end()
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
