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

return (new ArrayNodeDefinition('azuresearch'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('endpoint')->isRequired()->end()
            ->stringNode('api_key')->isRequired()->end()
            ->stringNode('index_name')->isRequired()->end()
            ->stringNode('api_version')->isRequired()->end()
            ->stringNode('vector_field')->end()
        ->end()
    ->end();
