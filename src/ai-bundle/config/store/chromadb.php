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

use Codewithkyrian\ChromaDB\Client as ChromaDbClient;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

return (new ArrayNodeDefinition('chromadb'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('client')
                ->cannotBeEmpty()
                ->defaultValue(ChromaDbClient::class)
            ->end()
            ->stringNode('collection')->isRequired()->end()
        ->end()
    ->end();
