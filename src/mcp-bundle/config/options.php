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
            ->arrayNode('client_transports')
                ->children()
                    ->booleanNode('stdio')->defaultFalse()->end()
                    ->booleanNode('sse')->defaultFalse()->end()
                ->end()
            ->end()
        ->end()
    ;
};
