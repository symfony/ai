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
                ->normalizeKeys(false)
                ->scalarPrototype()->end()
            ->end()
            ->stringNode('vector_bucket_name')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->stringNode('index_name')->end()
            ->arrayNode('filter')
                ->info('Default filter for queries')
                ->normalizeKeys(false)
                ->variablePrototype()->end()
            ->end()
            ->integerNode('dimensions')
                ->info('Number of dimensions of the index created by "ai:store:setup"')
                ->min(1)
            ->end()
            ->enumNode('metric')
                ->info('Distance metric of the index created by "ai:store:setup"')
                ->values(['cosine', 'euclidean'])
            ->end()
            ->integerNode('top_k')
                ->info('Default number of results to return')
                ->defaultValue(3)
            ->end()
        ->end()
    ->end();
