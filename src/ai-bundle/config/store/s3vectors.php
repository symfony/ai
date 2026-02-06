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

return (new ArrayNodeDefinition('s3vectors'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('client')
                ->info('Service reference to an existing S3VectorsClient')
            ->end()
            ->arrayNode('configuration')
                ->info('AsyncAws S3Vectors client configuration (used if client service is not provided)')
            ->end()
            ->stringNode('vector_bucket_name')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->stringNode('index_name')->end()
            ->arrayNode('filter')
                ->info('Default filter for queries')
            ->end()
            ->integerNode('top_k')
                ->info('Default number of results to return')
                ->defaultValue(3)
            ->end()
        ->end()
    ->end();
