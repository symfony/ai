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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/*
 * @param 'static'|'api'|'static_or_api' $source the kinds of catalogs the bridge ships
 */
return static function (string $source): ArrayNodeDefinition {
    $supportsApi = \in_array($source, ['api', 'static_or_api'], true);

    $node = (new ArrayNodeDefinition('model_catalog'))
        ->info('Model catalog of the platform, a string is short for the "service" option')
        ->addDefaultsIfNotSet()
        ->beforeNormalization()
            ->ifString()
            ->then(static fn (string $service): array => ['service' => $service])
        ->end();

    $children = $node->children()
        ->stringNode('service')
            ->info('Service ID of a custom model catalog to use instead of the bridge one')
        ->end();

    if ('static_or_api' === $source) {
        $children
            ->booleanNode('api')
                ->info('Fetch the models from the provider API instead of using the bundled list')
                ->defaultFalse()
            ->end();
    }

    $children
        ->arrayNode('models')
            ->info('Additional models, taking precedence over the models of the catalog with the same name')
            ->useAttributeAsKey('model_name')
            ->normalizeKeys(false)
            ->arrayPrototype()
                ->children()
                    ->stringNode('class')
                        ->info('The fully qualified class name of the model (must extend '.Model::class.')')
                        ->defaultValue(Model::class)
                        ->cannotBeEmpty()
                        ->validate()
                            ->ifTrue(static fn (string $class): bool => !class_exists($class))
                            ->thenInvalid('The model class "%s" does not exist.')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn (string $class): bool => !is_a($class, Model::class, true))
                            ->thenInvalid('The model class "%s" must extend '.Model::class.'.')
                        ->end()
                    ->end()
                    ->arrayNode('capabilities')
                        ->info('Array of capabilities that this model supports')
                        ->enumPrototype(Capability::class)
                            ->enumFqcn(Capability::class)
                        ->end()
                        ->defaultValue([])
                        ->validate()
                            ->ifEmpty()
                            ->thenInvalid('At least one capability must be specified for each model.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
        ->arrayNode('cache')
            ->info('Caches the model lookups, "true" is short for enabling it with the defaults')
            ->canBeEnabled()
            ->children()
                ->stringNode('service')
                    ->info('The cache service id as defined under the "cache" configuration key')
                    ->defaultValue('cache.app')
                ->end()
                ->integerNode('ttl')
                    ->info('Lifetime of the cached lookups in seconds')
                    ->defaultValue(3600)
                    ->min(1)
                ->end()
            ->end()
        ->end();

    $usesApi = static fn (array $config): bool => 'api' === $source || ($config['api'] ?? false);

    if ('static_or_api' === $source) {
        $node->validate()
            ->ifTrue(static fn (array $config): bool => isset($config['service']) && $config['api'])
            ->thenInvalid('The "service" and "api" options of the model catalog cannot be combined.')
        ->end();
    }

    if ('api' !== $source) {
        $node->validate()
            ->ifTrue(static fn (array $config): bool => $config['cache']['enabled'] && !isset($config['service']) && !$usesApi($config))
            ->thenInvalid($supportsApi
                ? 'Caching the model catalog requires a custom "service" or "api: true", the bundled list needs no cache.'
                : 'Caching the model catalog requires a custom "service", the bundled list needs no cache.')
        ->end();
    }

    return $node;
};
