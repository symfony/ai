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
use MongoDB\Client as MongoDbClient;
use Probots\Pinecone\Client as PineconeClient;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Contracts\Translation\TranslatorInterface;

return static function (DefinitionConfigurator $configurator): void {
    $configurator->rootNode()
        ->children()
            ->arrayNode('platform')
                ->children()
                    ->arrayNode('anthropic')
                        ->children()
                            ->stringNode('api_key')->isRequired()->end()
                            ->stringNode('version')->defaultNull()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('azure')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('api_key')->isRequired()->end()
                                ->stringNode('base_url')->isRequired()->end()
                                ->stringNode('deployment')->isRequired()->end()
                                ->stringNode('api_version')->info('The used API version')->end()
                                ->stringNode('http_client')
                                    ->defaultValue('http_client')
                                    ->info('Service ID of the HTTP client to use')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('eleven_labs')
                        ->children()
                            ->stringNode('host')->end()
                            ->stringNode('api_key')->isRequired()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('gemini')
                        ->children()
                            ->stringNode('api_key')->isRequired()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('vertexai')
                        ->children()
                            ->stringNode('location')->isRequired()->end()
                            ->stringNode('project_id')->isRequired()->end()
                        ->end()
                    ->end()
                    ->arrayNode('openai')
                        ->children()
                            ->stringNode('api_key')->isRequired()->end()
                            ->scalarNode('region')
                                ->defaultNull()
                                ->validate()
                                    ->ifNotInArray([null, PlatformFactory::REGION_EU, PlatformFactory::REGION_US])
                                    ->thenInvalid('The region must be either "EU" (https://eu.api.openai.com), "US" (https://us.api.openai.com) or null (https://api.openai.com)')
                                ->end()
                                ->info('The region for OpenAI API (EU, US, or null for default)')
                            ->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('mistral')
                        ->children()
                            ->stringNode('api_key')->isRequired()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('openrouter')
                        ->children()
                            ->stringNode('api_key')->isRequired()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('lmstudio')
                        ->children()
                            ->stringNode('host_url')->defaultValue('http://127.0.0.1:1234')->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('ollama')
                        ->children()
                            ->stringNode('host_url')->defaultValue('http://127.0.0.1:11434')->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('cerebras')
                        ->children()
                            ->stringNode('api_key')->isRequired()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('voyage')
                        ->children()
                            ->stringNode('api_key')->isRequired()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('perplexity')
                        ->children()
                            ->stringNode('api_key')->isRequired()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('dockermodelrunner')
                        ->children()
                            ->stringNode('host_url')->defaultValue('http://127.0.0.1:12434')->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('scaleway')
                        ->children()
                            ->scalarNode('api_key')->isRequired()->end()
                            ->stringNode('http_client')
                                ->defaultValue('http_client')
                                ->info('Service ID of the HTTP client to use')
                            ->end()
                        ->end()
                    ->end()
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
                        ->booleanNode('track_token_usage')
                            ->info('Enable tracking of token usage for the agent')
                            ->defaultTrue()
                        ->end()
                        ->variableNode('model')
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return !\is_string($v) && (!\is_array($v) || !isset($v['name']));
                                })
                                ->thenInvalid('Model must be a string or an array with a "name" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) {
                                    // Check if both query parameters and options array are provided
                                    if (\is_array($v) && isset($v['name']) && isset($v['options']) && [] !== $v['options']) {
                                        return str_contains($v['name'], '?');
                                    }

                                    return false;
                                })
                                ->thenInvalid('Cannot use both query parameters in model name and options array.')
                            ->end()
                            ->beforeNormalization()
                                ->always(function ($v) {
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

                                    return $model.'?'.http_build_query($options);
                                })
                            ->end()
                        ->end()
                        ->booleanNode('structured_output')->defaultTrue()->end()
                        ->variableNode('memory')
                            ->info('Memory configuration: string for static memory, or array with "service" key for service reference')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return \is_string($v) && '' === $v;
                                })
                                ->thenInvalid('Memory cannot be empty.')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return \is_array($v) && !isset($v['service']);
                                })
                                ->thenInvalid('Memory array configuration must contain a "service" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return \is_array($v) && isset($v['service']) && '' === $v['service'];
                                })
                                ->thenInvalid('Memory service cannot be empty.')
                            ->end()
                        ->end()
                        ->arrayNode('prompt')
                            ->info('The system prompt configuration')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(function (string $v) {
                                    return ['text' => $v];
                                })
                            ->end()
                            ->beforeNormalization()
                                ->ifArray()
                                ->then(function (array $v) {
                                    if (!isset($v['text']) && !isset($v['include_tools'])) {
                                        throw new \InvalidArgumentException('Either "text" or "include_tools" must be configured for prompt.');
                                    }

                                    return $v;
                                })
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return \is_array($v) && '' === trim($v['text'] ?? '');
                                })
                                ->thenInvalid('The "text" cannot be empty.')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return \is_array($v) && ($v['enabled'] ?? false) && !interface_exists(TranslatorInterface::class);
                                })
                                ->thenInvalid('System prompt translation is enabled, but no translator is present. Try running `composer require symfony/translation`.')
                            ->end()
                            ->children()
                                ->stringNode('text')
                                    ->info('The system prompt text')
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
                                ->then(function (array $v) {
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
                                            ->then(function (string $v) {
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
                        ->booleanNode('fault_tolerant_toolbox')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('store')
                ->children()
                    ->arrayNode('azure_search')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('endpoint')->isRequired()->end()
                                ->stringNode('api_key')->isRequired()->end()
                                ->stringNode('index_name')->isRequired()->end()
                                ->stringNode('api_version')->isRequired()->end()
                                ->stringNode('vector_field')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('cache')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('service')->cannotBeEmpty()->defaultValue('cache.app')->end()
                                ->stringNode('cache_key')->end()
                                ->stringNode('strategy')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('chroma_db')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('client')
                                    ->cannotBeEmpty()
                                    ->defaultValue(ChromaDbClient::class)
                                ->end()
                                ->stringNode('collection')->isRequired()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('clickhouse')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('dsn')->cannotBeEmpty()->end()
                                ->stringNode('http_client')->cannotBeEmpty()->end()
                                ->stringNode('database')->isRequired()->cannotBeEmpty()->end()
                                ->stringNode('table')->isRequired()->cannotBeEmpty()->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn ($v) => !isset($v['dsn']) && !isset($v['http_client']))
                                ->thenInvalid('Either "dsn" or "http_client" must be configured.')
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('cloudflare')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('account_id')->cannotBeEmpty()->end()
                                ->stringNode('api_key')->cannotBeEmpty()->end()
                                ->stringNode('index_name')->cannotBeEmpty()->end()
                                ->integerNode('dimensions')->end()
                                ->stringNode('metric')->end()
                                ->stringNode('endpoint_url')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('meilisearch')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('endpoint')->cannotBeEmpty()->end()
                                ->stringNode('api_key')->cannotBeEmpty()->end()
                                ->stringNode('index_name')->cannotBeEmpty()->end()
                                ->stringNode('embedder')->end()
                                ->stringNode('vector_field')->end()
                                ->integerNode('dimensions')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('memory')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('strategy')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('milvus')
                    ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('endpoint')->cannotBeEmpty()->end()
                                ->stringNode('api_key')->isRequired()->end()
                                ->stringNode('database')->isRequired()->end()
                                ->stringNode('collection')->isRequired()->end()
                                ->stringNode('vector_field')->end()
                                ->integerNode('dimensions')->end()
                                ->stringNode('metric_type')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('mongodb')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('client')
                                    ->cannotBeEmpty()
                                    ->defaultValue(MongoDbClient::class)
                                ->end()
                                ->stringNode('database')->isRequired()->end()
                                ->stringNode('collection')->isRequired()->end()
                                ->stringNode('index_name')->isRequired()->end()
                                ->stringNode('vector_field')->end()
                                ->booleanNode('bulk_write')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('neo4j')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('endpoint')->cannotBeEmpty()->end()
                                ->stringNode('username')->cannotBeEmpty()->end()
                                ->stringNode('password')->cannotBeEmpty()->end()
                                ->stringNode('database')->cannotBeEmpty()->end()
                                ->stringNode('vector_index_name')->cannotBeEmpty()->end()
                                ->stringNode('node_name')->cannotBeEmpty()->end()
                                ->stringNode('vector_field')->end()
                                ->integerNode('dimensions')->end()
                                ->stringNode('distance')->end()
                                ->booleanNode('quantization')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('pinecone')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('client')
                                    ->cannotBeEmpty()
                                    ->defaultValue(PineconeClient::class)
                                ->end()
                                ->stringNode('namespace')->end()
                                ->arrayNode('filter')
                                    ->scalarPrototype()->end()
                                ->end()
                                ->integerNode('top_k')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('qdrant')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('endpoint')->cannotBeEmpty()->end()
                                ->stringNode('api_key')->cannotBeEmpty()->end()
                                ->stringNode('collection_name')->cannotBeEmpty()->end()
                                ->integerNode('dimensions')->end()
                                ->stringNode('distance')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('surreal_db')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('endpoint')->cannotBeEmpty()->end()
                                ->stringNode('username')->cannotBeEmpty()->end()
                                ->stringNode('password')->cannotBeEmpty()->end()
                                ->stringNode('namespace')->cannotBeEmpty()->end()
                                ->stringNode('database')->cannotBeEmpty()->end()
                                ->stringNode('table')->end()
                                ->stringNode('vector_field')->end()
                                ->stringNode('strategy')->end()
                                ->integerNode('dimensions')->end()
                                ->booleanNode('namespaced_user')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('typesense')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('endpoint')->cannotBeEmpty()->end()
                                ->stringNode('api_key')->isRequired()->end()
                                ->stringNode('collection')->isRequired()->end()
                                ->stringNode('vector_field')->end()
                                ->integerNode('dimensions')->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('weaviate')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->stringNode('endpoint')->cannotBeEmpty()->end()
                                ->stringNode('api_key')->isRequired()->end()
                                ->stringNode('collection')->isRequired()->end()
                            ->end()
                        ->end()
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
                                ->ifTrue(function ($v) {
                                    return !\is_string($v) && (!\is_array($v) || !isset($v['name']));
                                })
                                ->thenInvalid('Model must be a string or an array with a "name" key.')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) {
                                    // Check if both query parameters and options array are provided
                                    if (\is_array($v) && isset($v['name']) && isset($v['options']) && [] !== $v['options']) {
                                        return str_contains($v['name'], '?');
                                    }

                                    return false;
                                })
                                ->thenInvalid('Cannot use both query parameters in model name and options array.')
                            ->end()
                            ->beforeNormalization()
                                ->always(function ($v) {
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
                            ->isRequired()
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
        ->end()
    ;
};
