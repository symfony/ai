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

return (new ArrayNodeDefinition('workflow'))
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            ->arrayNode('places')
                ->isRequired()
                ->requiresAtLeastOneElement()
                ->scalarPrototype()->cannotBeEmpty()->end()
            ->end()
            ->arrayNode('transitions')
                ->isRequired()
                ->requiresAtLeastOneElement()
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('from')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('to')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
            ->scalarNode('initial_place')
                ->isRequired()
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('executors')
                ->isRequired()
                ->requiresAtLeastOneElement()
                ->useAttributeAsKey('place')
                ->arrayPrototype()
                    ->children()
                        ->enumNode('type')
                            ->values(['agent', 'service'])
                            ->isRequired()
                        ->end()
                        ->scalarNode('agent')
                            ->info('Agent name (references ai.agent.<name>) — required for type: agent')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('input_key')
                            ->defaultValue('input')
                        ->end()
                        ->scalarNode('output_key')
                            ->defaultValue('output')
                        ->end()
                        ->scalarNode('service')
                            ->info('Service ID implementing ExecutorInterface — required for type: service')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $v): bool => 'agent' === $v['type'] && !isset($v['agent']))
                        ->thenInvalid('Executor type "agent" requires the "agent" option.')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $v): bool => 'service' === $v['type'] && !isset($v['service']))
                        ->thenInvalid('Executor type "service" requires the "service" option.')
                    ->end()
                ->end()
            ->end()
            ->arrayNode('store')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('type')
                        ->values(['memory', 'cache', 'filesystem', 'redis', 'service'])
                        ->defaultValue('memory')
                    ->end()
                    ->scalarNode('cache_service')
                        ->info('PSR-6 cache pool service ID (for type: cache)')
                        ->defaultValue('cache.app')
                    ->end()
                    ->scalarNode('prefix')
                        ->info('Key prefix for cache/redis stores')
                        ->defaultValue('_workflow_state_')
                    ->end()
                    ->integerNode('ttl')
                        ->info('TTL in seconds (for type: cache)')
                        ->defaultValue(86400)
                    ->end()
                    ->scalarNode('directory')
                        ->info('Directory path (for type: filesystem)')
                    ->end()
                    ->scalarNode('redis_client')
                        ->info('Redis client service ID (for type: redis)')
                    ->end()
                    ->scalarNode('service')
                        ->info('Custom service ID implementing WorkflowStateStoreInterface (for type: service)')
                    ->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $v): bool => 'filesystem' === $v['type'] && !isset($v['directory']))
                    ->thenInvalid('Store type "filesystem" requires the "directory" option.')
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $v): bool => 'redis' === $v['type'] && !isset($v['redis_client']))
                    ->thenInvalid('Store type "redis" requires the "redis_client" option.')
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $v): bool => 'service' === $v['type'] && !isset($v['service']))
                    ->thenInvalid('Store type "service" requires the "service" option.')
                ->end()
            ->end()
            ->arrayNode('guards')
                ->useAttributeAsKey('place')
                ->arrayPrototype()
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
            ->end()
            ->scalarNode('transition_resolver')
                ->defaultNull()
                ->info('Service ID of a custom TransitionResolverInterface, or null for default')
            ->end()
        ->end()
        ->validate()
            ->ifTrue(static fn (array $v): bool => !\in_array($v['initial_place'], $v['places'], true))
            ->thenInvalid('The "initial_place" must be one of the configured places.')
        ->end()
        ->validate()
            ->ifTrue(static function (array $v): bool {
                foreach (array_keys($v['executors']) as $place) {
                    if (!\in_array($place, $v['places'], true)) {
                        return true;
                    }
                }

                return false;
            })
            ->then(static function (array $v): never {
                $invalidPlaces = array_diff(array_keys($v['executors']), $v['places']);

                throw new \InvalidArgumentException(\sprintf('Executor(s) configured for non-existent place(s): "%s". Available places: "%s".', implode('", "', $invalidPlaces), implode('", "', $v['places'])));
            })
        ->end()
        ->validate()
            ->ifTrue(static function (array $v): bool {
                foreach ($v['transitions'] as $transition) {
                    if (!\in_array($transition['from'], $v['places'], true)
                        || !\in_array($transition['to'], $v['places'], true)) {
                        return true;
                    }
                }

                return false;
            })
            ->thenInvalid('Transition "from" and "to" must reference configured places.')
        ->end()
        ->validate()
            ->ifTrue(static function (array $v): bool {
                foreach (array_keys($v['guards'] ?? []) as $place) {
                    if (!\in_array($place, $v['places'], true)) {
                        return true;
                    }
                }

                return false;
            })
            ->thenInvalid('Guard(s) configured for non-existent place(s).')
        ->end()
    ->end();
