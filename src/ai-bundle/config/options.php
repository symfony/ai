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
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Contracts\Translation\TranslatorInterface;

return static function (DefinitionConfigurator $configurator): void {
    $import = fn (string $resource): ArrayNodeDefinition => require __DIR__.'/'.$resource.'.php';

    $configurator->rootNode()
        ->children()
            ->arrayNode('platform')
                ->children()
                    ->append($import('platform/albert'))
                    ->append($import('platform/anthropic'))
                    ->append($import('platform/azure'))
                    ->append($import('platform/bedrock'))
                    ->append($import('platform/cache'))
                    ->append($import('platform/cartesia'))
                    ->append($import('platform/cerebras'))
                    ->append($import('platform/decart'))
                    ->append($import('platform/deepseek'))
                    ->append($import('platform/dockermodelrunner'))
                    ->append($import('platform/elevenlabs'))
                    ->append($import('platform/failover'))
                    ->append($import('platform/gemini'))
                    ->append($import('platform/generic'))
                    ->append($import('platform/huggingface'))
                    ->append($import('platform/lmstudio'))
                    ->append($import('platform/mistral'))
                    ->append($import('platform/ollama'))
                    ->append($import('platform/openai'))
                    ->append($import('platform/openrouter'))
                    ->append($import('platform/ovh'))
                    ->append($import('platform/perplexity'))
                    ->append($import('platform/scaleway'))
                    ->append($import('platform/transformersphp'))
                    ->append($import('platform/vertexai'))
                    ->append($import('platform/voyage'))
                ->end()
            ->end()
            ->arrayNode('model')
                ->useAttributeAsKey('platform')
                ->arrayPrototype()
                    ->useAttributeAsKey('model_name')
                    ->normalizeKeys(false)
                    ->validate()
                        ->ifEmpty()
                        ->thenInvalid('Model name cannot be empty.')
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->stringNode('class')
                                ->info('The fully qualified class name of the model (must extend '.Model::class.')')
                                ->defaultValue(Model::class)
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static function ($v) {
                                        return !class_exists($v);
                                    })
                                    ->thenInvalid('The model class "%s" does not exist.')
                                ->end()
                                ->validate()
                                    ->ifTrue(static function ($v) {
                                        return !is_a($v, Model::class, true);
                                    })
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
            ->end()
            ->arrayNode('agent')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('platform')
                            ->info('Service name of platform')
                            ->defaultValue(PlatformInterface::class)
                        ->end()
                        ->variableNode('model')
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return !\is_string($v) && (!\is_array($v) || !isset($v['name']));
                                })
                                ->thenInvalid('Model must be a string or an array with a "name" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    // Check if both query parameters and options array are provided
                                    if (\is_array($v) && isset($v['name']) && isset($v['options']) && [] !== $v['options']) {
                                        return str_contains($v['name'], '?');
                                    }

                                    return false;
                                })
                                ->thenInvalid('Cannot use both query parameters in model name and options array.')
                            ->end()
                            ->beforeNormalization()
                                ->always(static function ($v) {
                                    if (\is_string($v)) {
                                        return $v;
                                    }

                                    // It's an array with 'name' and optionally 'options'
                                    $model = $v['name'];
                                    $options = $v['options'] ?? [];

                                    // Parse query parameters from model name if present
                                    if (str_contains($model, '?')) {
                                        $parsed = parse_url($model);
                                        $model = $parsed['path'] ?? '';

                                        if ('' === $model) {
                                            throw new InvalidConfigurationException('Model name cannot be empty.');
                                        }

                                        if (isset($parsed['scheme'])) {
                                            $model = $parsed['scheme'].':'.$model;
                                        }

                                        if (isset($parsed['query'])) {
                                            // If options array is also provided, throw an error
                                            if ([] !== $options) {
                                                throw new InvalidConfigurationException('Cannot use both query parameters in model name and options array.');
                                            }
                                            parse_str($parsed['query'], $existingOptions);
                                            $options = $existingOptions;
                                        }
                                    }

                                    // Return model string with options as query parameters
                                    if ([] === $options) {
                                        return $model;
                                    }

                                    array_walk_recursive($options, static function (mixed &$value): void {
                                        if (\is_bool($value)) {
                                            $value = $value ? 'true' : 'false';
                                        }
                                    });

                                    return $model.'?'.http_build_query($options);
                                })
                            ->end()
                        ->end()
                        ->variableNode('memory')
                            ->info('Memory configuration: string for static memory, or array with "service" key for service reference')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_string($v) && '' === $v;
                                })
                                ->thenInvalid('Memory cannot be empty.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && !isset($v['service']);
                                })
                                ->thenInvalid('Memory array configuration must contain a "service" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && isset($v['service']) && '' === $v['service'];
                                })
                                ->thenInvalid('Memory service cannot be empty.')
                            ->end()
                        ->end()
                        ->arrayNode('prompt')
                            ->info('The system prompt configuration')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static function (string $v) {
                                    return ['text' => $v];
                                })
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    if (!\is_array($v)) {
                                        return false;
                                    }
                                    $hasTextOrFile = isset($v['text']) || isset($v['file']);

                                    return !$hasTextOrFile;
                                })
                                ->thenInvalid('Either "text" or "file" must be configured for prompt.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && isset($v['text']) && isset($v['file']);
                                })
                                ->thenInvalid('Cannot use both "text" and "file" for prompt. Choose one.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && isset($v['text']) && '' === trim($v['text']);
                                })
                                ->thenInvalid('The "text" cannot be empty.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && isset($v['file']) && '' === trim($v['file']);
                                })
                                ->thenInvalid('The "file" cannot be empty.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return \is_array($v) && ($v['enabled'] ?? false) && !interface_exists(TranslatorInterface::class);
                                })
                                ->thenInvalid('System prompt translation is enabled, but no translator is present. Try running `composer require symfony/translation`.')
                            ->end()
                            ->children()
                                ->stringNode('text')
                                    ->info('The system prompt text')
                                ->end()
                                ->stringNode('file')
                                    ->info('Path to file containing the system prompt')
                                ->end()
                                ->booleanNode('include_tools')
                                    ->info('Include tool definitions at the end of the system prompt')
                                    ->defaultFalse()
                                ->end()
                                ->booleanNode('enable_translation')
                                    ->info('Enable translation for the system prompt')
                                    ->defaultFalse()
                                ->end()
                                ->stringNode('translation_domain')
                                    ->info('The translation domain for the system prompt')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('tools')
                            ->addDefaultsIfNotSet()
                            ->treatFalseLike(['enabled' => false])
                            ->treatTrueLike(['enabled' => true])
                            ->treatNullLike(['enabled' => true])
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(static function (array $v): array {
                                    return [
                                        'enabled' => $v['enabled'] ?? true,
                                        'services' => $v['services'] ?? $v,
                                    ];
                                })
                            ->end()
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->arrayNode('services')
                                    ->arrayPrototype()
                                        ->children()
                                            ->stringNode('service')->cannotBeEmpty()->end()
                                            ->stringNode('agent')->cannotBeEmpty()->end()
                                            ->stringNode('name')->end()
                                            ->stringNode('description')->end()
                                            ->stringNode('method')->end()
                                        ->end()
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(static function (string $v) {
                                                return ['service' => $v];
                                            })
                                        ->end()
                                        ->validate()
                                            ->ifTrue(static fn ($v) => !(empty($v['agent']) xor empty($v['service'])))
                                            ->thenInvalid('Either "agent" or "service" must be configured, and never both.')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->booleanNode('keep_tool_messages')
                            ->info('Keep tool messages in the conversation history')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('include_sources')
                            ->info('Include sources exposed by tools as part of the tool result metadata')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('fault_tolerant_toolbox')
                            ->info('Continue the agent run even if a tool call fails')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('multi_agent')
                ->info('Multi-agent orchestration configuration')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('orchestrator')
                            ->info('Service ID of the orchestrator agent')
                            ->isRequired()
                        ->end()
                        ->arrayNode('handoffs')
                            ->info('Handoff rules mapping agent service IDs to trigger keywords')
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->useAttributeAsKey('service')
                            ->arrayPrototype()
                                ->info('Keywords or phrases that trigger handoff to this agent')
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                        ->stringNode('fallback')
                            ->info('Service ID of the fallback agent for unmatched requests')
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('store')
                ->children()
                    ->append($import('store/azuresearch'))
                    ->append($import('store/cache'))
                    ->append($import('store/chromadb'))
                    ->append($import('store/clickhouse'))
                    ->append($import('store/cloudflare'))
                    ->append($import('store/elasticsearch'))
                    ->append($import('store/manticoresearch'))
                    ->append($import('store/mariadb'))
                    ->append($import('store/meilisearch'))
                    ->append($import('store/memory'))
                    ->append($import('store/milvus'))
                    ->append($import('store/mongodb'))
                    ->append($import('store/neo4j'))
                    ->append($import('store/opensearch'))
                    ->append($import('store/pinecone'))
                    ->append($import('store/postgres'))
                    ->append($import('store/qdrant'))
                    ->append($import('store/redis'))
                    ->append($import('store/s3vectors'))
                    ->append($import('store/supabase'))
                    ->append($import('store/surrealdb'))
                    ->append($import('store/typesense'))
                    ->append($import('store/weaviate'))
                ->end()
            ->end()
            ->arrayNode('message_store')
                ->children()
                    ->append($import('message_store/cache'))
                    ->append($import('message_store/cloudflare'))
                    ->append($import('message_store/doctrine'))
                    ->append($import('message_store/meilisearch'))
                    ->append($import('message_store/memory'))
                    ->append($import('message_store/mongodb'))
                    ->append($import('message_store/pogocache'))
                    ->append($import('message_store/redis'))
                    ->append($import('message_store/session'))
                    ->append($import('message_store/surrealdb'))
                ->end()
            ->end()
            ->arrayNode('chat')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('agent')->cannotBeEmpty()->end()
                        ->stringNode('message_store')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('vectorizer')
                ->info('Vectorizers for converting strings to Vector objects and transforming TextDocument arrays to VectorDocument arrays')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('platform')
                            ->info('Service name of platform')
                            ->defaultValue(PlatformInterface::class)
                        ->end()
                        ->variableNode('model')
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    return !\is_string($v) && (!\is_array($v) || !isset($v['name']));
                                })
                                ->thenInvalid('Model must be a string or an array with a "name" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(static function ($v) {
                                    // Check if both query parameters and options array are provided
                                    if (\is_array($v) && isset($v['name']) && isset($v['options']) && [] !== $v['options']) {
                                        return str_contains($v['name'], '?');
                                    }

                                    return false;
                                })
                                ->thenInvalid('Cannot use both query parameters in model name and options array.')
                            ->end()
                            ->beforeNormalization()
                                ->always(static function ($v) {
                                    if (\is_string($v) || null === $v) {
                                        return $v;
                                    }

                                    // It's an array with 'name' and optionally 'options'
                                    $model = $v['name'];
                                    $options = $v['options'] ?? [];

                                    // Parse query parameters from model name if present
                                    if (str_contains($model, '?')) {
                                        $parsed = parse_url($model);
                                        $model = $parsed['path'] ?? '';

                                        if ('' === $model) {
                                            throw new InvalidConfigurationException('Model name cannot be empty.');
                                        }

                                        if (isset($parsed['scheme'])) {
                                            $model = $parsed['scheme'].':'.$model;
                                        }

                                        if (isset($parsed['query'])) {
                                            // If options array is also provided, throw an error
                                            if ([] !== $options) {
                                                throw new InvalidConfigurationException('Cannot use both query parameters in model name and options array.');
                                            }
                                            parse_str($parsed['query'], $existingOptions);
                                            $options = $existingOptions;
                                        }
                                    }

                                    // Return model string with options as query parameters
                                    if ([] === $options) {
                                        return $model;
                                    }

                                    array_walk_recursive($options, static function (mixed &$value): void {
                                        if (\is_bool($value)) {
                                            $value = $value ? 'true' : 'false';
                                        }
                                    });

                                    return $model.'?'.http_build_query($options);
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('indexer')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->stringNode('loader')
                            ->info('Service name of loader')
                            ->defaultNull()
                        ->end()
                        ->variableNode('source')
                            ->info('Source identifier (file path, URL, etc.) or array of sources')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('transformers')
                            ->info('Array of transformer service names')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('filters')
                            ->info('Array of filter service names')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('vectorizer')
                            ->info('Service name of vectorizer')
                            ->defaultValue(VectorizerInterface::class)
                        ->end()
                        ->stringNode('store')
                            ->info('Service name of store')
                            ->defaultValue(StoreInterface::class)
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('retriever')
                ->info('Retrievers for fetching documents from a vector store based on a query')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('vectorizer')
                            ->info('Service name of vectorizer')
                            ->defaultValue(VectorizerInterface::class)
                        ->end()
                        ->stringNode('store')
                            ->info('Service name of store')
                            ->defaultValue(StoreInterface::class)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end()
        ->validate()
            ->ifTrue(static function ($v) {
                if (!isset($v['agent']) || !isset($v['multi_agent'])) {
                    return false;
                }

                $agentNames = array_keys($v['agent']);
                $multiAgentNames = array_keys($v['multi_agent']);
                $duplicates = array_intersect($agentNames, $multiAgentNames);

                return !empty($duplicates);
            })
            ->then(static function ($v) {
                $agentNames = array_keys($v['agent'] ?? []);
                $multiAgentNames = array_keys($v['multi_agent'] ?? []);
                $duplicates = array_intersect($agentNames, $multiAgentNames);

                throw new \InvalidArgumentException(\sprintf('Agent names and multi-agent names must be unique. Duplicate name(s) found: "%s"', implode(', ', $duplicates)));
            })
        ->end()
        ->validate()
            ->ifTrue(static function ($v) {
                if (!isset($v['multi_agent']) || !isset($v['agent'])) {
                    return false;
                }

                $agentNames = array_keys($v['agent']);

                foreach ($v['multi_agent'] as $multiAgentName => $multiAgent) {
                    // Check orchestrator exists
                    if (!\in_array($multiAgent['orchestrator'], $agentNames, true)) {
                        return true;
                    }

                    // Check fallback exists
                    if (!\in_array($multiAgent['fallback'], $agentNames, true)) {
                        return true;
                    }

                    // Check handoff agents exist
                    foreach (array_keys($multiAgent['handoffs']) as $handoffAgent) {
                        if (!\in_array($handoffAgent, $agentNames, true)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->then(static function ($v) {
                $agentNames = array_keys($v['agent']);

                foreach ($v['multi_agent'] as $multiAgentName => $multiAgent) {
                    if (!\in_array($multiAgent['orchestrator'], $agentNames, true)) {
                        throw new \InvalidArgumentException(\sprintf('The agent "%s" referenced in multi-agent "%s" as orchestrator does not exist', $multiAgent['orchestrator'], $multiAgentName));
                    }

                    if (!\in_array($multiAgent['fallback'], $agentNames, true)) {
                        throw new \InvalidArgumentException(\sprintf('The agent "%s" referenced in multi-agent "%s" as fallback does not exist', $multiAgent['fallback'], $multiAgentName));
                    }

                    foreach (array_keys($multiAgent['handoffs']) as $handoffAgent) {
                        if (!\in_array($handoffAgent, $agentNames, true)) {
                            throw new \InvalidArgumentException(\sprintf('The agent "%s" referenced in multi-agent "%s" as handoff target does not exist', $handoffAgent, $multiAgentName));
                        }
                    }
                }
            })
        ->end()
    ;
};
