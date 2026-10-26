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

return (new ArrayNodeDefinition('memory'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('identifier')
                ->setDeprecated('symfony/ai-bundle', '0.16', 'The "%node%" option is deprecated, the in-memory message store keeps each conversation by its ID.')
                ->cannotBeEmpty()
            ->end()
        ->end()
    ->end();
