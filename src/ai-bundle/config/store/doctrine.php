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

return (new ArrayNodeDefinition('doctrine'))
    ->info('Keeps the vectors in the #[Embedding] field of a Doctrine entity, or mirrors the entity into another store, and returns entities when queried.')
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->stringNode('entity')
                ->info('FQCN of the entity with #[Embedding] on a field, or on the class when it is mirrored.')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->enumNode('metric')
                ->info('Metric to use for vector similarity search.')
                ->values(['cosine', 'euclidean', 'inner_product'])
                ->defaultValue('cosine')
            ->end()
            ->stringNode('entity_manager')
                ->info('Service id of the entity manager owning the entity.')
                ->defaultValue('doctrine.orm.entity_manager')
            ->end()
            ->stringNode('vectorizer')
                ->info('Service id of the vectorizer embedding the entities on flush and through "ai:store:index".')
                ->defaultNull()
            ->end()
            ->stringNode('mirror_to')
                ->info('Service id of the store to mirror the entity into, needs a "vectorizer" and #[Embedding] on the entity class.')
                ->defaultNull()
            ->end()
        ->end()
    ->end();
