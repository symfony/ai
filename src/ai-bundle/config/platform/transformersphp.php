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

return (new ArrayNodeDefinition('transformersphp'))
    ->children()
        ->stringNode('model_catalog')
            ->defaultNull()
            ->info('Service ID of a custom model catalog to use instead of the bundled one')
        ->end()
    ->end();
