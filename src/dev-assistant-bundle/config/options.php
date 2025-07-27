<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->arrayNode('cache')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->integerNode('ttl')->defaultValue(3600)->end()
                ->end()
            ->end()
            ->arrayNode('ai')
                ->children()
                    ->arrayNode('providers')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('api_key')->end()
                                ->scalarNode('model')->end()
                                ->scalarNode('base_url')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('rate_limiting')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->integerNode('limit')->defaultValue(100)->end()
                    ->integerNode('window')->defaultValue(3600)->end()
                ->end()
            ->end()
        ->end()
    ;
};
