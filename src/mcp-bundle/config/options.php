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

return static function (DefinitionConfigurator $configurator): void {
    $configurator->rootNode()
        ->children()
            ->scalarNode('app')->defaultValue('app')->end()
            ->scalarNode('version')->defaultValue('0.0.1')->end()
            // ->arrayNode('servers')
            //     ->useAttributeAsKey('name')
            //     ->arrayPrototype()
            //         ->children()
            //             ->enumNode('transport')
            //                 ->values(['stdio', 'sse'])
            //                 ->isRequired()
            //             ->end()
            //             ->arrayNode('stdio')
            //                 ->children()
            //                     ->scalarNode('command')->isRequired()->end()
            //                     ->arrayNode('arguments')
            //                         ->scalarPrototype()->end()
            //                         ->defaultValue([])
            //                     ->end()
            //                 ->end()
            //             ->end()
            //             ->arrayNode('sse')
            //                 ->children()
            //                     ->scalarNode('url')->isRequired()->end()
            //                 ->end()
            //             ->end()
            //         ->end()
            //         ->validate()
            //             ->ifTrue(function ($v) {
            //                 if ('stdio' === $v['transport'] && !isset($v['stdio'])) {
            //                     return true;
            //                 }
            //                 if ('sse' === $v['transport'] && !isset($v['sse'])) {
            //                     return true;
            //                 }
            //
            //                 return false;
            //             })
            //             ->thenInvalid('When transport is "%s", you must configure the corresponding section.')
            //         ->end()
            //     ->end()
            // ->end()
            ->arrayNode('client_transports')
                ->children()
                    ->booleanNode('stdio')->defaultFalse()->end()
                    ->booleanNode('sse')->defaultFalse()->end()
                ->end()
            ->end()
        ->end()
    ;
};
