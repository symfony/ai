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
            ->scalarNode('page_size')->defaultValue(20)->end()
            ->arrayNode('discovery')
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->arrayNode('directories')
                        ->scalarPrototype()->end()
                        ->defaultValue(['src'])
                    ->end()
                    ->arrayNode('exclude')
                        ->scalarPrototype()->end()
                        ->defaultValue(['vendor', 'var', 'tests'])
                    ->end()
                ->end()
            ->end()
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
            ->arrayNode('server_capabilities')
                ->addDefaultsIfNotSet()
                ->children()
                    ->booleanNode('tools')->defaultTrue()->end()
                    ->booleanNode('tools_list_changed')->defaultNull()->end()
                    ->booleanNode('resources')->defaultNull()->end()
                    ->booleanNode('resources_subscribe')->defaultFalse()->end()
                    ->booleanNode('resources_list_changed')->defaultNull()->end()
                    ->booleanNode('prompts')->defaultNull()->end()
                    ->booleanNode('prompts_list_changed')->defaultNull()->end()
                    ->booleanNode('logging')->defaultFalse()->end()
                    ->booleanNode('completions')->defaultTrue()->end()
                    ->arrayNode('experimental')
                        ->useAttributeAsKey('name')
                        ->variablePrototype()->end()
                        ->defaultValue([])
                    ->end()
                ->end()
            ->end()
            ->arrayNode('client_transports')
                ->children()
                    ->booleanNode('stdio')->defaultFalse()->end()
                    ->booleanNode('sse')->defaultFalse()->end()
                ->end()
            ->end()
        ->end()
    ;
};
