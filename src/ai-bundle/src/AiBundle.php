<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\FetchAuthTokenInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Attribute\AsInputProcessor;
use Symfony\AI\Agent\Attribute\AsOutputProcessor;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Agent\Memory\MemoryInputProcessor;
use Symfony\AI\Agent\Memory\StaticMemoryProvider;
use Symfony\AI\Agent\MultiAgent\Handoff;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\Tool\Subagent;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\AiBundle\DependencyInjection\ProcessorCompilerPass;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\AiBundle\Profiler\TraceableChat;
use Symfony\AI\AiBundle\Profiler\TraceableMessageStore;
use Symfony\AI\AiBundle\Profiler\TraceablePlatform;
use Symfony\AI\AiBundle\Profiler\TraceableToolbox;
use Symfony\AI\AiBundle\Security\Attribute\IsGrantedTool;
use Symfony\AI\Chat\Bridge\Cache\MessageStore as CacheMessageStore;
use Symfony\AI\Chat\Bridge\Cloudflare\MessageStore as CloudflareMessageStore;
use Symfony\AI\Chat\Bridge\Doctrine\DoctrineDbalMessageStore;
use Symfony\AI\Chat\Bridge\Meilisearch\MessageStore as MeilisearchMessageStore;
use Symfony\AI\Chat\Bridge\MongoDb\MessageStore as MongoDbMessageStore;
use Symfony\AI\Chat\Bridge\Pogocache\MessageStore as PogocacheMessageStore;
use Symfony\AI\Chat\Bridge\Redis\MessageStore as RedisMessageStore;
use Symfony\AI\Chat\Bridge\Session\MessageStore as SessionMessageStore;
use Symfony\AI\Chat\Bridge\SurrealDb\MessageStore as SurrealDbMessageStore;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\ChatInterface;
use Symfony\AI\Chat\InMemory\Store as InMemoryMessageStore;
use Symfony\AI\Chat\ManagedStoreInterface as ManagedMessageStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Bridge\Albert\PlatformFactory as AlbertPlatformFactory;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory as AnthropicPlatformFactory;
use Symfony\AI\Platform\Bridge\Azure\OpenAi\PlatformFactory as AzureOpenAiPlatformFactory;
use Symfony\AI\Platform\Bridge\Bedrock\PlatformFactory as BedrockFactory;
use Symfony\AI\Platform\Bridge\Cache\CachePlatform;
use Symfony\AI\Platform\Bridge\Cache\ResultNormalizer;
use Symfony\AI\Platform\Bridge\Cartesia\PlatformFactory as CartesiaPlatformFactory;
use Symfony\AI\Platform\Bridge\Cerebras\PlatformFactory as CerebrasPlatformFactory;
use Symfony\AI\Platform\Bridge\Decart\PlatformFactory as DecartPlatformFactory;
use Symfony\AI\Platform\Bridge\DeepSeek\PlatformFactory as DeepSeekPlatformFactory;
use Symfony\AI\Platform\Bridge\DockerModelRunner\PlatformFactory as DockerModelRunnerPlatformFactory;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsApiCatalog;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory as ElevenLabsPlatformFactory;
use Symfony\AI\Platform\Bridge\Failover\FailoverPlatform;
use Symfony\AI\Platform\Bridge\Failover\FailoverPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\Generic\PlatformFactory as GenericPlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory as HuggingFacePlatformFactory;
use Symfony\AI\Platform\Bridge\LmStudio\PlatformFactory as LmStudioPlatformFactory;
use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory as MistralPlatformFactory;
use Symfony\AI\Platform\Bridge\Ollama\OllamaApiCatalog;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory as OllamaPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenRouter\PlatformFactory as OpenRouterPlatformFactory;
use Symfony\AI\Platform\Bridge\Perplexity\PlatformFactory as PerplexityPlatformFactory;
use Symfony\AI\Platform\Bridge\Scaleway\PlatformFactory as ScalewayPlatformFactory;
use Symfony\AI\Platform\Bridge\TransformersPhp\PlatformFactory as TransformersPhpPlatformFactory;
use Symfony\AI\Platform\Bridge\VertexAi\PlatformFactory as VertexAiPlatformFactory;
use Symfony\AI\Platform\Bridge\Voyage\PlatformFactory as VoyagePlatformFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Store\Bridge\AzureSearch\SearchStore as AzureSearchStore;
use Symfony\AI\Store\Bridge\Cache\Store as CacheStore;
use Symfony\AI\Store\Bridge\ChromaDb\Store as ChromaDbStore;
use Symfony\AI\Store\Bridge\ClickHouse\Store as ClickHouseStore;
use Symfony\AI\Store\Bridge\Cloudflare\Store as CloudflareStore;
use Symfony\AI\Store\Bridge\Elasticsearch\Store as ElasticsearchStore;
use Symfony\AI\Store\Bridge\ManticoreSearch\Store as ManticoreSearchStore;
use Symfony\AI\Store\Bridge\MariaDb\Store as MariaDbStore;
use Symfony\AI\Store\Bridge\Meilisearch\Store as MeilisearchStore;
use Symfony\AI\Store\Bridge\Milvus\Store as MilvusStore;
use Symfony\AI\Store\Bridge\MongoDb\Store as MongoDbStore;
use Symfony\AI\Store\Bridge\Neo4j\Store as Neo4jStore;
use Symfony\AI\Store\Bridge\OpenSearch\Store as OpenSearchStore;
use Symfony\AI\Store\Bridge\Pinecone\Store as PineconeStore;
use Symfony\AI\Store\Bridge\Postgres\Distance as PostgresDistance;
use Symfony\AI\Store\Bridge\Postgres\Store as PostgresStore;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\Bridge\Redis\Distance as RedisDistance;
use Symfony\AI\Store\Bridge\Redis\Store as RedisStore;
use Symfony\AI\Store\Bridge\Supabase\Store as SupabaseStore;
use Symfony\AI\Store\Bridge\SurrealDb\Store as SurrealDbStore;
use Symfony\AI\Store\Bridge\Typesense\Store as TypesenseStore;
use Symfony\AI\Store\Bridge\Weaviate\Store as WeaviateStore;
use Symfony\AI\Store\Distance\DistanceCalculator;
use Symfony\AI\Store\Distance\DistanceStrategy;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Indexer\ConfiguredSourceIndexer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Indexer\SourceIndexer;
use Symfony\AI\Store\IndexerInterface;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Retriever;
use Symfony\AI\Store\RetrieverInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AiBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ProcessorCompilerPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/options.php');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        foreach ($config['platform'] ?? [] as $type => $platform) {
            $this->processPlatformConfig($type, $platform, $builder);
        }

        // Process model configuration and pass to ModelCatalog services
        foreach ($config['model'] ?? [] as $platformName => $models) {
            $this->processModelConfig($platformName, $models, $builder);
        }

        $platforms = array_keys($builder->findTaggedServiceIds('ai.platform'));
        if (1 === \count($platforms)) {
            $builder->setAlias(PlatformInterface::class, reset($platforms));
        }
        if ($builder->getParameter('kernel.debug')) {
            foreach ($platforms as $platform) {
                $traceablePlatformDefinition = (new Definition(TraceablePlatform::class))
                    ->setDecoratedService($platform, priority: -1024)
                    ->setArguments([new Reference('.inner')])
                    ->addTag('ai.traceable_platform');
                $suffix = u($platform)->after('ai.platform.')->toString();
                $builder->setDefinition('ai.traceable_platform.'.$suffix, $traceablePlatformDefinition);
            }
        }

        if ([] !== ($config['agent'] ?? [])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-agent', Agent::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Agent configuration requires "symfony/ai-agent" package. Try running "composer require symfony/ai-agent".');
            }

            foreach ($config['agent'] as $agentName => $agent) {
                $this->processAgentConfig($agentName, $agent, $builder);
            }
            if (1 === \count($config['agent']) && isset($agentName)) {
                $builder->setAlias(AgentInterface::class, 'ai.agent.'.$agentName);
            }
        }

        if ([] !== ($config['multi_agent'] ?? [])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-agent', Agent::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Multi-agent configuration requires "symfony/ai-agent" package. Try running "composer require symfony/ai-agent".');
            }

            foreach ($config['multi_agent'] as $multiAgentName => $multiAgent) {
                $this->processMultiAgentConfig($multiAgentName, $multiAgent, $builder);
            }
        }

        $setupStoresOptions = [];
        if ([] !== ($config['store'] ?? [])) {
            $storeConfigured = \count($config['store']) !== \count($config['store'], \COUNT_RECURSIVE);
            if ($storeConfigured && !ContainerBuilder::willBeAvailable('symfony/ai-store', StoreInterface::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Store configuration requires "symfony/ai-store" package. Try running "composer require symfony/ai-store".');
            }

            foreach ($config['store'] as $type => $store) {
                $this->processStoreConfig($type, $store, $builder, $setupStoresOptions);
            }
        }
        $builder->getDefinition('ai.command.setup_store')->setArgument(1, $setupStoresOptions);

        $stores = array_keys($builder->findTaggedServiceIds('ai.store'));

        if (1 === \count($stores)) {
            $builder->setAlias(StoreInterface::class, reset($stores));
        }

        if ([] === $stores) {
            $builder->removeDefinition('ai.command.setup_store');
            $builder->removeDefinition('ai.command.drop_store');
        }

        if ([] !== ($config['message_store'] ?? [])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-chat', Chat::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Message store configuration requires "symfony/ai-chat" package. Try running "composer require symfony/ai-chat".');
            }

            foreach ($config['message_store'] as $type => $store) {
                $this->processMessageStoreConfig($type, $store, $builder);
            }
        }

        $messageStores = array_keys($builder->findTaggedServiceIds('ai.message_store'));

        if (1 === \count($messageStores)) {
            $builder->setAlias(MessageStoreInterface::class, reset($messageStores));
        }

        if ($builder->getParameter('kernel.debug')) {
            foreach ($messageStores as $messageStore) {
                $traceableMessageStoreDefinition = (new Definition(TraceableMessageStore::class))
                    ->setDecoratedService($messageStore, priority: -1024)
                    ->setArguments([
                        new Reference('.inner'),
                        new Reference(ClockInterface::class),
                    ])
                    ->addTag('ai.traceable_message_store');
                $suffix = u($messageStore)->afterLast('.')->toString();
                $builder->setDefinition('ai.traceable_message_store.'.$suffix, $traceableMessageStoreDefinition);
            }
        }

        if ([] === $messageStores) {
            $builder->removeDefinition('ai.command.setup_message_store');
            $builder->removeDefinition('ai.command.drop_message_store');
        }

        if ([] !== ($config['chat'] ?? [])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-chat', Chat::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Chat configuration requires "symfony/ai-chat" package. Try running "composer require symfony/ai-chat".');
            }

            foreach ($config['chat'] as $name => $chat) {
                $this->processChatConfig($name, $chat, $builder);
            }
        }

        $chats = array_keys($builder->findTaggedServiceIds('ai.chat'));

        if (1 === \count($chats)) {
            $builder->setAlias(ChatInterface::class, reset($chats));
        }

        if ($builder->getParameter('kernel.debug')) {
            foreach ($chats as $chat) {
                $traceableChatDefinition = (new Definition(TraceableChat::class))
                    ->setDecoratedService($chat, priority: -1024)
                    ->setArguments([
                        new Reference('.inner'),
                        new Reference(ClockInterface::class),
                    ])
                    ->addTag('ai.traceable_chat');
                $suffix = u($chat)->afterLast('.')->toString();
                $builder->setDefinition('ai.traceable_chat.'.$suffix, $traceableChatDefinition);
            }
        }

        if ([] !== ($config['vectorizer'] ?? [])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-store', StoreInterface::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Vectorizer configuration requires "symfony/ai-store" package. Try running "composer require symfony/ai-store".');
            }

            foreach ($config['vectorizer'] as $vectorizerName => $vectorizer) {
                $this->processVectorizerConfig($vectorizerName, $vectorizer, $builder);
            }
        }

        if ([] !== ($config['indexer'] ?? [])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-store', StoreInterface::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Indexer configuration requires "symfony/ai-store" package. Try running "composer require symfony/ai-store".');
            }

            foreach ($config['indexer'] as $indexerName => $indexer) {
                $this->processIndexerConfig($indexerName, $indexer, $builder);
            }
            if (1 === \count($config['indexer']) && isset($indexerName)) {
                $builder->setAlias(IndexerInterface::class, 'ai.indexer.'.$indexerName);
            }
        }

        if ([] !== ($config['retriever'] ?? [])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-store', StoreInterface::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Retriever configuration requires "symfony/ai-store" package. Try running "composer require symfony/ai-store".');
            }

            foreach ($config['retriever'] as $retrieverName => $retriever) {
                $this->processRetrieverConfig($retrieverName, $retriever, $builder);
            }
            if (1 === \count($config['retriever']) && isset($retrieverName)) {
                $builder->setAlias(RetrieverInterface::class, 'ai.retriever.'.$retrieverName);
            }
        }

        if (ContainerBuilder::willBeAvailable('symfony/ai-agent', Agent::class, ['symfony/ai-bundle'])) {
            $builder->registerAttributeForAutoconfiguration(AsTool::class, static function (ChildDefinition $definition, AsTool $attribute): void {
                $definition->addTag('ai.tool', [
                    'name' => $attribute->name,
                    'description' => $attribute->description,
                    'method' => $attribute->method,
                ]);
            });

            $builder->registerAttributeForAutoconfiguration(AsInputProcessor::class, static function (ChildDefinition $definition, AsInputProcessor $attribute): void {
                $definition->addTag('ai.agent.input_processor', [
                    'agent' => $attribute->agent,
                    'priority' => $attribute->priority,
                ]);
            });

            $builder->registerAttributeForAutoconfiguration(AsOutputProcessor::class, static function (ChildDefinition $definition, AsOutputProcessor $attribute): void {
                $definition->addTag('ai.agent.output_processor', [
                    'agent' => $attribute->agent,
                    'priority' => $attribute->priority,
                ]);
            });

            $builder->registerForAutoconfiguration(InputProcessorInterface::class)
                ->addTag('ai.agent.input_processor', ['tagged_by' => 'interface']);
            $builder->registerForAutoconfiguration(OutputProcessorInterface::class)
                ->addTag('ai.agent.output_processor', ['tagged_by' => 'interface']);
        }

        $builder->registerForAutoconfiguration(ModelClientInterface::class)
            ->addTag('ai.platform.model_client');
        $builder->registerForAutoconfiguration(ResultConverterInterface::class)
            ->addTag('ai.platform.result_converter');

        if (!ContainerBuilder::willBeAvailable('symfony/security-core', AuthorizationCheckerInterface::class, ['symfony/ai-bundle'])) {
            $builder->removeDefinition('ai.security.is_granted_attribute_listener');
            $builder->registerAttributeForAutoconfiguration(
                IsGrantedTool::class,
                static fn () => throw new InvalidArgumentException('Using #[IsGrantedTool] attribute requires additional dependencies. Try running "composer install symfony/security-core".'),
            );
        }

        if (false === $builder->getParameter('kernel.debug')) {
            $builder->removeDefinition('ai.data_collector');
            $builder->removeDefinition('ai.traceable_toolbox');
        }

        // Remove service definitions for optional packages that are not installed
        if (!ContainerBuilder::willBeAvailable('symfony/ai-agent', Agent::class, ['symfony/ai-bundle'])) {
            $builder->removeDefinition('ai.command.chat');
            $builder->removeDefinition('ai.toolbox.abstract');
            $builder->removeDefinition('ai.tool_factory.abstract');
            $builder->removeDefinition('ai.tool_factory');
            $builder->removeDefinition('ai.tool_result_converter');
            $builder->removeDefinition('ai.tool_call_argument_resolver');
            $builder->removeDefinition('ai.tool.agent_processor.abstract');
        }

        if (!ContainerBuilder::willBeAvailable('symfony/ai-store', StoreInterface::class, ['symfony/ai-bundle'])) {
            $builder->removeDefinition('ai.command.setup_store');
            $builder->removeDefinition('ai.command.drop_store');
            $builder->removeDefinition('ai.command.index');
            $builder->removeDefinition('ai.command.retrieve');
        }

        if (!ContainerBuilder::willBeAvailable('symfony/ai-chat', Chat::class, ['symfony/ai-bundle'])) {
            $builder->removeDefinition('ai.chat.message_bag.normalizer');
            $builder->removeDefinition('ai.command.setup_message_store');
            $builder->removeDefinition('ai.command.drop_message_store');
        }
    }

    /**
     * @param array<string, mixed> $platform
     */
    private function processPlatformConfig(string $type, array $platform, ContainerBuilder $container): void
    {
        if ('albert' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-albert-platform', AlbertPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Albert platform configuration requires "symfony/ai-albert-platform" package. Try running "composer require symfony/ai-albert-platform".');
            }

            $platformId = 'ai.platform.albert';
            $definition = (new Definition(Platform::class))
                ->setFactory(AlbertPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    $platform['base_url'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.albert'),
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'albert']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('anthropic' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-anthropic-platform', AnthropicPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Anthropic platform configuration requires "symfony/ai-anthropic-platform" package. Try running "composer require symfony/ai-anthropic-platform".');
            }

            $platformId = 'ai.platform.anthropic';
            $definition = (new Definition(Platform::class))
                ->setFactory(AnthropicPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.anthropic'),
                    new Reference('ai.platform.contract.anthropic'),
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'anthropic']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('azure' === $type) {
            foreach ($platform as $name => $config) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-azure-platform', AzureOpenAiPlatformFactory::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Azure platform configuration requires "symfony/ai-azure-platform" package. Try running "composer require symfony/ai-azure-platform".');
                }

                $platformId = 'ai.platform.azure.'.$name;
                $definition = (new Definition(Platform::class))
                    ->setFactory(AzureOpenAiPlatformFactory::class.'::create')
                    ->setLazy(true)
                    ->addTag('proxy', ['interface' => PlatformInterface::class])
                    ->setArguments([
                        $config['base_url'],
                        $config['deployment'],
                        $config['api_version'],
                        $config['api_key'],
                        new Reference($config['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                        new Reference('ai.platform.model_catalog.azure.openai'),
                        new Reference('ai.platform.contract.openai'),
                        new Reference('event_dispatcher'),
                    ])
                    ->addTag('ai.platform', ['name' => 'azure.'.$name]);

                $container->setDefinition($platformId, $definition);
            }

            return;
        }

        if ('bedrock' === $type) {
            foreach ($platform as $name => $config) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-bedrock-platform', BedrockFactory::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Bedrock platform configuration requires "symfony/ai-bedrock-platform" package. Try running "composer require symfony/ai-bedrock-platform".');
                }

                $platformId = 'ai.platform.bedrock.'.$name;
                $definition = (new Definition(Platform::class))
                    ->setFactory(BedrockFactory::class.'::create')
                    ->setLazy(true)
                    ->addTag('proxy', ['interface' => PlatformInterface::class])
                    ->setArguments([
                        $config['bedrock_runtime_client'] ? new Reference($config['bedrock_runtime_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE) : null,
                        $config['model_catalog'] ? new Reference($config['model_catalog']) : new Reference('ai.platform.model_catalog.bedrock'),
                        null,
                        new Reference('event_dispatcher'),
                    ])
                    ->addTag('ai.platform', ['name' => 'bedrock.'.$name]);

                $container->setDefinition($platformId, $definition);
            }

            return;
        }

        if ('cache' === $type) {
            foreach ($platform as $name => $cachePlatformConfig) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-cache-platform', CachePlatform::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('CachePlatform platform configuration requires "symfony/ai-cache-platform" package. Try running "composer require symfony/ai-cache-platform".');
                }

                $definition = (new Definition(CachePlatform::class))
                    ->setLazy(true)
                    ->setArguments([
                        new Reference($cachePlatformConfig['platform']),
                        new Reference(ClockInterface::class),
                        new Reference($cachePlatformConfig['service'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                        new Reference('serializer'),
                        $cachePlatformConfig['cache_key'] ?? $name,
                        $cachePlatformConfig['ttl'] ?? null,
                    ])
                    ->addTag('proxy', ['interface' => PlatformInterface::class])
                    ->addTag('ai.platform', ['name' => 'cache.'.$name]);

                $container->setDefinition('ai.platform.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.platform.'.$type.'.'.$name, PlatformInterface::class, $type.'_'.$name);
            }

            return;
        }

        if (ContainerBuilder::willBeAvailable('symfony/ai-cache-platform', CachePlatform::class, ['symfony/ai-bundle'])) {
            $container->register('ai.platform.cache.result_normalizer', ResultNormalizer::class)
                ->setArguments([
                    new Reference('serializer.normalizer.object'),
                ])
                ->addTag('serializer.normalizer');
        }

        if ('cartesia' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-cartesia-platform', CartesiaPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Cartesia platform configuration requires "symfony/ai-cartesia-platform" package. Try running "composer require symfony/ai-cartesia-platform".');
            }

            $definition = (new Definition(Platform::class))
                ->setFactory(CartesiaPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    $platform['version'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.cartesia'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'cartesia']);

            $container->setDefinition('ai.platform.cartesia', $definition);

            return;
        }

        if ('decart' === $type) {
            $definition = (new Definition(Platform::class))
                ->setFactory(DecartPlatformFactory::class.'::create')
                ->setLazy(true)
                ->setArguments([
                    $platform['api_key'],
                    $platform['host'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.'.$type),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->addTag('ai.platform', ['name' => $type]);

            $container->setDefinition('ai.platform.'.$type, $definition);
            $container->registerAliasForArgument('ai.platform.'.$type, PlatformInterface::class, $type);

            return;
        }

        if ('elevenlabs' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-eleven-labs-platform', ElevenLabsPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('ElevenLabs platform configuration requires "symfony/ai-eleven-labs-platform" package. Try running "composer require symfony/ai-eleven-labs-platform".');
            }

            if (\array_key_exists('api_catalog', $platform) && $platform['api_catalog']) {
                $catalogDefinition = (new Definition(ElevenLabsApiCatalog::class))
                    ->setLazy(true)
                    ->setArguments([
                        new Reference($platform['http_client']),
                        $platform['api_key'],
                        $platform['host'],
                    ])
                    ->addTag('proxy', ['interface' => ModelCatalogInterface::class]);

                $container->setDefinition('ai.platform.model_catalog.'.$type, $catalogDefinition);
            }

            $definition = (new Definition(Platform::class))
                ->setFactory(ElevenLabsPlatformFactory::class.'::create')
                ->setLazy(true)
                ->setArguments([
                    $platform['api_key'],
                    $platform['host'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.'.$type),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->addTag('ai.platform', ['name' => $type]);

            $container->setDefinition('ai.platform.'.$type, $definition);
            $container->registerAliasForArgument('ai.platform.'.$type, PlatformInterface::class, $type);

            return;
        }

        if ('failover' === $type) {
            foreach ($platform as $name => $config) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-failover-platform', FailoverPlatformFactory::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Failover platform configuration requires "symfony/ai-failover-platform" package. Try running "composer require symfony/ai-failover-platform".');
                }

                $definition = (new Definition(FailoverPlatform::class))
                    ->setFactory(FailoverPlatformFactory::class.'::create')
                    ->setLazy(true)
                    ->setArguments([
                        array_map(
                            static fn (string $wrappedPlatform): Reference => new Reference($wrappedPlatform),
                            $config['platforms'],
                        ),
                        new Reference($config['rate_limiter']),
                        new Reference(ClockInterface::class),
                        new Reference(LoggerInterface::class),
                    ])
                    ->addTag('proxy', ['interface' => PlatformInterface::class])
                    ->addTag('ai.platform', ['name' => $type]);

                $container->setDefinition('ai.platform.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.platform.'.$type.'.'.$name, PlatformInterface::class, $name);
            }

            return;
        }

        if ('gemini' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-gemini-platform', GeminiPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Gemini platform configuration requires "symfony/ai-gemini-platform" package. Try running "composer require symfony/ai-gemini-platform".');
            }

            $platformId = 'ai.platform.gemini';
            $definition = (new Definition(Platform::class))
                ->setFactory(GeminiPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.gemini'),
                    new Reference('ai.platform.contract.gemini'),
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'gemini']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('generic' === $type) {
            foreach ($platform as $name => $config) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-generic-platform', GenericPlatformFactory::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Generic platform configuration requires "symfony/ai-generic-platform" package. Try running "composer require symfony/ai-generic-platform".');
                }

                $platformId = 'ai.platform.generic.'.$name;
                $definition = (new Definition(Platform::class))
                    ->setFactory(GenericPlatformFactory::class.'::create')
                    ->setLazy(true)
                    ->addTag('proxy', ['interface' => PlatformInterface::class])
                    ->setArguments([
                        $config['base_url'],
                        $config['api_key'],
                        new Reference($config['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                        new Reference($config['model_catalog'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                        null,
                        new Reference('event_dispatcher'),
                        $config['supports_completions'],
                        $config['supports_embeddings'],
                        $config['completions_path'],
                        $config['embeddings_path'],
                    ])
                    ->addTag('ai.platform', ['name' => 'generic.'.$name]);

                $container->setDefinition($platformId, $definition);
            }

            return;
        }

        if ('huggingface' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-hugging-face-platform', HuggingFacePlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('HuggingFace platform configuration requires "symfony/ai-hugging-face-platform" package. Try running "composer require symfony/ai-hugging-face-platform".');
            }

            $platformId = 'ai.platform.huggingface';
            $definition = (new Definition(Platform::class))
                ->setFactory(HuggingFacePlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    $platform['provider'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.huggingface'),
                    new Reference('ai.platform.contract.huggingface'),
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'huggingface']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('vertexai' === $type && isset($platform['location'], $platform['project_id'])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-vertex-ai-platform', VertexAiPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('VertexAI platform configuration requires "symfony/ai-vertex-ai-platform" package. Try running "composer require symfony/ai-vertex-ai-platform".');
            }

            if (!class_exists(ApplicationDefaultCredentials::class)) {
                throw new RuntimeException('For using the Vertex AI platform, google/auth package is required. Try running "composer require google/auth".');
            }

            $credentials = (new Definition(FetchAuthTokenInterface::class))
                ->setFactory([ApplicationDefaultCredentials::class, 'getCredentials'])
                ->setArguments([
                    'https://www.googleapis.com/auth/cloud-platform',
                ])
            ;

            $credentialsObject = new Definition(\ArrayObject::class, [(new Definition('array'))->setFactory([$credentials, 'fetchAuthToken'])]);

            $httpClient = (new Definition(HttpClientInterface::class))
                ->setFactory([new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE), 'withOptions'])
                ->setArgument(0, [
                    'auth_bearer' => (new Definition('string', ['access_token']))->setFactory([$credentialsObject, 'offsetGet']),
                ])
            ;

            $platformId = 'ai.platform.vertexai';
            $definition = (new Definition(Platform::class))
                ->setFactory([VertexAiPlatformFactory::class, 'create'])
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['location'],
                    $platform['project_id'],
                    $platform['api_key'] ?? null,
                    $httpClient,
                    new Reference('ai.platform.model_catalog.vertexai.gemini'),
                    new Reference('ai.platform.contract.vertexai.gemini'),
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'vertexai']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('openai' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-open-ai-platform', OpenAiPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('OpenAI platform configuration requires "symfony/ai-open-ai-platform" package. Try running "composer require symfony/ai-open-ai-platform".');
            }

            $platformId = 'ai.platform.openai';
            $definition = (new Definition(Platform::class))
                ->setFactory(OpenAiPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.openai'),
                    new Reference('ai.platform.contract.openai'),
                    $platform['region'] ?? null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'openai']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('openrouter' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-open-router-platform', OpenRouterPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('OpenRouter platform configuration requires "symfony/ai-open-router-platform" package. Try running "composer require symfony/ai-open-router-platform".');
            }

            $platformId = 'ai.platform.openrouter';
            $definition = (new Definition(Platform::class))
                ->setFactory(OpenRouterPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.openrouter'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'openrouter']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('mistral' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-mistral-platform', MistralPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Mistral platform configuration requires "symfony/ai-mistral-platform" package. Try running "composer require symfony/ai-mistral-platform".');
            }

            $platformId = 'ai.platform.mistral';
            $definition = (new Definition(Platform::class))
                ->setFactory(MistralPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.mistral'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'mistral']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('lmstudio' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-lm-studio-platform', LmStudioPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('LmStudio platform configuration requires "symfony/ai-lm-studio-platform" package. Try running "composer require symfony/ai-lm-studio-platform".');
            }

            $platformId = 'ai.platform.lmstudio';
            $definition = (new Definition(Platform::class))
                ->setFactory(LmStudioPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['host_url'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.lmstudio'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'lmstudio']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('ollama' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-ollama-platform', OllamaPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Ollama platform configuration requires "symfony/ai-ollama-platform" package. Try running "composer require symfony/ai-ollama-platform".');
            }

            if (\array_key_exists('api_catalog', $platform)) {
                $catalogDefinition = (new Definition(OllamaApiCatalog::class))
                    ->setLazy(true)
                    ->setArguments([
                        $platform['host_url'],
                        new Reference($platform['http_client']),
                    ])
                    ->addTag('proxy', ['interface' => ModelCatalogInterface::class])
                ;

                $container->setDefinition('ai.platform.model_catalog.ollama', $catalogDefinition);
            }

            $definition = (new Definition(Platform::class))
                ->setFactory(OllamaPlatformFactory::class.'::create')
                ->setLazy(true)
                ->setArguments([
                    $platform['host_url'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.ollama'),
                    new Reference('ai.platform.contract.ollama'),
                    new Reference('event_dispatcher'),
                ])
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->addTag('ai.platform', ['name' => 'ollama']);

            $container->setDefinition('ai.platform.ollama', $definition);

            return;
        }

        if ('cerebras' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-cerebras-platform', CerebrasPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Cerebras platform configuration requires "symfony/ai-cerebras-platform" package. Try running "composer require symfony/ai-cerebras-platform".');
            }

            $platformId = 'ai.platform.cerebras';
            $definition = (new Definition(Platform::class))
                ->setFactory(CerebrasPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.cerebras'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'cerebras']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('deepseek' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-deep-seek-platform', DeepSeekPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('DeepSeek platform configuration requires "symfony/ai-deep-seek-platform" package. Try running "composer require symfony/ai-deep-seek-platform".');
            }

            $platformId = 'ai.platform.deepseek';
            $definition = (new Definition(Platform::class))
                ->setFactory(DeepSeekPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.deepseek'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'deepseek']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('voyage' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-voyage-platform', VoyagePlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Voyage platform configuration requires "symfony/ai-voyage-platform" package. Try running "composer require symfony/ai-voyage-platform".');
            }

            $platformId = 'ai.platform.voyage';
            $definition = (new Definition(Platform::class))
                ->setFactory(VoyagePlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.voyage'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform', ['name' => 'voyage']);

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('perplexity' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-perplexity-platform', PerplexityPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Perplexity platform configuration requires "symfony/ai-perplexity-platform" package. Try running "composer require symfony/ai-perplexity-platform".');
            }

            $platformId = 'ai.platform.perplexity';
            $definition = (new Definition(Platform::class))
                ->setFactory(PerplexityPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.perplexity'),
                    new Reference('ai.platform.contract.perplexity'),
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('dockermodelrunner' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-docker-model-runner-platform', DockerModelRunnerPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Docker Model Runner platform configuration requires "symfony/ai-docker-model-runner-platform" package. Try running "composer require symfony/ai-docker-model-runner-platform".');
            }

            $platformId = 'ai.platform.dockermodelrunner';
            $definition = (new Definition(Platform::class))
                ->setFactory(DockerModelRunnerPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['host_url'],
                    new Reference($platform['http_client'], ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.dockermodelrunner'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('scaleway' === $type && isset($platform['api_key'])) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-scaleway-platform', ScalewayPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Scaleway platform configuration requires "symfony/ai-scaleway-platform" package. Try running "composer require symfony/ai-scaleway-platform".');
            }

            $platformId = 'ai.platform.scaleway';
            $definition = (new Definition(Platform::class))
                ->setFactory(ScalewayPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    $platform['api_key'],
                    new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('ai.platform.model_catalog.scaleway'),
                    null,
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        if ('transformersphp' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-transformers-php-platform', TransformersPhpPlatformFactory::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('TransformersPhp platform configuration requires "symfony/ai-transformers-php-platform" package. Try running "composer require symfony/ai-transformers-php-platform".');
            }

            $platformId = 'ai.platform.transformersphp';
            $definition = (new Definition(Platform::class))
                ->setFactory(TransformersPhpPlatformFactory::class.'::create')
                ->setLazy(true)
                ->addTag('proxy', ['interface' => PlatformInterface::class])
                ->setArguments([
                    new Reference('ai.platform.model_catalog.transformersphp'),
                    new Reference('event_dispatcher'),
                ])
                ->addTag('ai.platform');

            $container->setDefinition($platformId, $definition);

            return;
        }

        throw new InvalidArgumentException(\sprintf('Platform "%s" is not supported for configuration via bundle at this point.', $type));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processAgentConfig(string $name, array $config, ContainerBuilder $container): void
    {
        // AGENT
        $agentId = 'ai.agent.'.$name;
        $agentDefinition = (new Definition(Agent::class))
            ->addTag('ai.agent', ['name' => $name])
            ->setArgument(0, new Reference($config['platform']))
            ->setArgument(1, $config['model']);

        // TOOLBOX
        if ($config['tools']['enabled']) {
            // Setup toolbox for agent
            $memoryFactoryDefinition = new ChildDefinition('ai.tool_factory.abstract');
            $memoryFactoryDefinition->setClass(MemoryToolFactory::class);
            $container->setDefinition('ai.toolbox.'.$name.'.memory_factory', $memoryFactoryDefinition);
            $chainFactoryDefinition = new Definition(ChainFactory::class, [
                [new Reference('ai.toolbox.'.$name.'.memory_factory'), new Reference('ai.tool_factory')],
            ]);
            $container->setDefinition('ai.toolbox.'.$name.'.chain_factory', $chainFactoryDefinition);

            $toolboxDefinition = (new ChildDefinition('ai.toolbox.abstract'))
                ->replaceArgument(1, new Reference('ai.toolbox.'.$name.'.chain_factory'))
                ->addTag('ai.toolbox', ['name' => $name]);
            $container->setDefinition('ai.toolbox.'.$name, $toolboxDefinition);

            if ($config['fault_tolerant_toolbox']) {
                $container->setDefinition('ai.fault_tolerant_toolbox.'.$name, new Definition(FaultTolerantToolbox::class))
                    ->setArguments([new Reference('.inner')])
                    ->setDecoratedService('ai.toolbox.'.$name, priority: -1024);
            }

            if ($container->getParameter('kernel.debug')) {
                $traceableToolboxDefinition = (new Definition('ai.traceable_toolbox.'.$name))
                    ->setClass(TraceableToolbox::class)
                    ->setArguments([new Reference('.inner')])
                    ->setDecoratedService('ai.toolbox.'.$name, priority: -1024)
                    ->addTag('ai.traceable_toolbox');
                $container->setDefinition('ai.traceable_toolbox.'.$name, $traceableToolboxDefinition);
            }

            $toolProcessorDefinition = (new ChildDefinition('ai.tool.agent_processor.abstract'))
                ->replaceArgument(0, new Reference('ai.toolbox.'.$name))
                ->replaceArgument(3, $config['keep_tool_messages'])
                ->replaceArgument(4, $config['include_sources']);

            $container->setDefinition('ai.tool.agent_processor.'.$name, $toolProcessorDefinition)
                ->addTag('ai.agent.input_processor', ['agent' => $agentId, 'priority' => -10])
                ->addTag('ai.agent.output_processor', ['agent' => $agentId, 'priority' => -10]);

            // Define specific list of tools if are explicitly defined
            if ([] !== $config['tools']['services']) {
                $tools = [];
                foreach ($config['tools']['services'] as $tool) {
                    if (isset($tool['agent'])) {
                        $tool['name'] ??= $tool['agent'];
                        $tool['service'] = \sprintf('ai.agent.%s', $tool['agent']);
                    }
                    $reference = new Reference($tool['service']);
                    // We use the memory factory in case method, description and name are set
                    if (isset($tool['name'], $tool['description'])) {
                        if (isset($tool['agent'])) {
                            $subagentDefinition = new Definition(Subagent::class, [$reference]);
                            $container->setDefinition('ai.toolbox.'.$name.'.subagent.'.$tool['name'], $subagentDefinition);
                            $reference = new Reference('ai.toolbox.'.$name.'.subagent.'.$tool['name']);
                        }
                        $memoryFactoryDefinition->addMethodCall('addTool', [$reference, $tool['name'], $tool['description'], $tool['method'] ?? '__invoke']);
                    }
                    $tools[] = $reference;
                }

                $toolboxDefinition->replaceArgument(0, $tools);
            }
        }

        // SYSTEM PROMPT
        if (isset($config['prompt'])) {
            $includeTools = isset($config['prompt']['include_tools']) && $config['prompt']['include_tools'];

            // Create prompt from file if configured, otherwise use text
            if (isset($config['prompt']['file'])) {
                $filePath = $config['prompt']['file'];
                // File::fromFile() handles validation, so no need to check here
                // Use Definition with factory method because File objects cannot be serialized during container compilation
                $prompt = (new Definition(File::class))
                    ->setFactory([File::class, 'fromFile'])
                    ->setArguments([$filePath]);
            } elseif (isset($config['prompt']['text'])) {
                $promptText = $config['prompt']['text'];

                if ($config['prompt']['enable_translation']) {
                    if (!class_exists(TranslatableMessage::class)) {
                        throw new RuntimeException('For using prompt translataion, symfony/translation package is required. Try running "composer require symfony/translation".');
                    }

                    $promptId = 'ai.agent.prompt.'.$name;
                    $translatableDefinition = (new Definition(TranslatableMessage::class))
                        ->setArguments([$promptText, [], $config['prompt']['translation_domain']]);

                    $container->setDefinition($promptId, $translatableDefinition);
                    $prompt = new Reference($promptId);
                } else {
                    $prompt = $promptText;
                }
            } else {
                $prompt = '';
            }

            $systemPromptInputProcessorDefinition = (new Definition(SystemPromptInputProcessor::class))
                ->setArguments([
                    $prompt,
                    $includeTools ? new Reference('ai.toolbox.'.$name) : null,
                    new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                ])
                ->addTag('ai.agent.input_processor', ['agent' => $agentId, 'priority' => -30]);

            $container->setDefinition('ai.agent.'.$name.'.system_prompt_processor', $systemPromptInputProcessorDefinition);
        }

        // MEMORY PROVIDER
        if (isset($config['memory'])) {
            $memoryValue = $config['memory'];

            if (\is_array($memoryValue) && isset($memoryValue['service'])) {
                // Array configuration with service key - use the service directly
                $memoryProviderReference = new Reference($memoryValue['service']);
            } else {
                // String configuration - always create StaticMemoryProvider
                $staticMemoryProviderDefinition = (new Definition(StaticMemoryProvider::class))
                    ->setArguments([$memoryValue]);

                $staticMemoryServiceId = 'ai.agent.'.$name.'.static_memory_provider';
                $container->setDefinition($staticMemoryServiceId, $staticMemoryProviderDefinition);
                $memoryProviderReference = new Reference($staticMemoryServiceId);
            }

            $memoryInputProcessorDefinition = (new Definition(MemoryInputProcessor::class))
                ->setArguments([[$memoryProviderReference]])
                ->addTag('ai.agent.input_processor', ['agent' => $agentId, 'priority' => -40]);

            $container->setDefinition('ai.agent.'.$name.'.memory_input_processor', $memoryInputProcessorDefinition);
        }

        $agentDefinition
            ->setArgument(2, []) // placeholder until ProcessorCompilerPass process.
            ->setArgument(3, []) // placeholder until ProcessorCompilerPass process.
            ->setArgument(4, $name)
            ->setArgument(5, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
        ;

        $container->setDefinition($agentId, $agentDefinition);
        $container->registerAliasForArgument($agentId, AgentInterface::class, (new Target($name))->getParsedName());
    }

    /**
     * @param array<string, mixed> $stores
     * @param array<string, mixed> $setupStoresOptions
     */
    private function processStoreConfig(string $type, array $stores, ContainerBuilder $container, array &$setupStoresOptions): void
    {
        if ([] === $stores) {
            return;
        }

        if ('azuresearch' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-azure-search-store', AzureSearchStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Azure Search store configuration requires "symfony/ai-azure-search-store" package. Try running "composer require symfony/ai-azure-search-store".');
            }

            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['index_name'],
                    $store['api_version'],
                ];

                if (\array_key_exists('vector_field', $store)) {
                    $arguments[5] = $store['vector_field'];
                }

                $definition = new Definition(AzureSearchStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('cache' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-cache-store', CacheStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Cache store configuration requires "symfony/ai-cache-store" package. Try running "composer require symfony/ai-cache-store".');
            }

            foreach ($stores as $name => $store) {
                $distanceCalculatorDefinition = new Definition(DistanceCalculator::class);
                $distanceCalculatorDefinition->setLazy(true);

                $container->setDefinition('ai.store.distance_calculator.'.$name, $distanceCalculatorDefinition);

                if (\array_key_exists('strategy', $store) && null !== $store['strategy']) {
                    $distanceCalculatorDefinition = $container->getDefinition('ai.store.distance_calculator.'.$name);
                    $distanceCalculatorDefinition->setArgument(0, DistanceStrategy::from($store['strategy']));
                }

                $definition = new Definition(CacheStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference($store['service']),
                        new Reference('ai.store.distance_calculator.'.$name),
                        $store['cache_key'] ?? $name,
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('chromadb' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-chroma-db-store', ChromaDbStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('ChromaDB store configuration requires "symfony/ai-chroma-db-store" package. Try running "composer require symfony/ai-chroma-db-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(ChromaDbStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference($store['client']),
                        $store['collection'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('clickhouse' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-click-house-store', ClickHouseStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('ClickHouse store configuration requires "symfony/ai-click-house-store" package. Try running "composer require symfony/ai-click-house-store".');
            }

            foreach ($stores as $name => $store) {
                if (isset($store['http_client'])) {
                    $httpClient = new Reference($store['http_client']);
                } else {
                    $httpClient = new Definition(HttpClientInterface::class);
                    $httpClient
                        ->setLazy(true)
                        ->setFactory([HttpClient::class, 'createForBaseUri'])
                        ->setArguments([$store['dsn']]);
                }

                $definition = new Definition(ClickHouseStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        $httpClient,
                        $store['database'],
                        $store['table'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('cloudflare' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-cloudflare-store', CloudflareStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Cloudflare store configuration requires "symfony/ai-cloudflare-store" package. Try running "composer require symfony/ai-cloudflare-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(CloudflareStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference('http_client'),
                        $store['account_id'],
                        $store['api_key'],
                        $store['index_name'] ?? $name,
                        $store['dimensions'],
                        $store['metric'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                if (\array_key_exists('endpoint', $store)) {
                    $definition->setArgument(6, $store['endpoint']);
                }

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('manticoresearch' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-manticore-search-store', ManticoreSearchStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('ManticoreSearch store configuration requires "symfony/ai-manticore-search-store" package. Try running "composer require symfony/ai-manticore-search-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(ManticoreSearchStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference('http_client'),
                        $store['endpoint'],
                        $store['table'] ?? $name,
                        $store['field'],
                        $store['type'],
                        $store['similarity'],
                        $store['dimensions'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                if (\array_key_exists('quantization', $store)) {
                    $definition->setArgument(7, $store['quantization']);
                }

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('mariadb' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-maria-db-store', MariaDbStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('MariaDB store configuration requires "symfony/ai-maria-db-store" package. Try running "composer require symfony/ai-maria-db-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(MariaDbStore::class);
                $definition
                    ->setLazy(true)
                    ->setFactory([MariaDbStore::class, 'fromDbal'])
                    ->setArguments([
                        new Reference(\sprintf('doctrine.dbal.%s_connection', $store['connection'])),
                        $store['table_name'] ?? $name,
                        $store['index_name'],
                        $store['vector_field_name'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);

                $setupStoresOptions['ai.store.'.$type.'.'.$name] = $store['setup_options'] ?? [];
            }
        }

        if ('meilisearch' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-meilisearch-store', MeilisearchStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Meilisearch store configuration requires "symfony/ai-meilisearch-store" package. Try running "composer require symfony/ai-meilisearch-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(MeilisearchStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference('http_client'),
                        $store['endpoint'],
                        $store['api_key'],
                        $store['index_name'] ?? $name,
                        $store['embedder'],
                        $store['vector_field'],
                        $store['dimensions'],
                        $store['semantic_ratio'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('memory' === $type) {
            foreach ($stores as $name => $store) {
                $arguments = [
                    new Definition(DistanceCalculator::class),
                ];

                if (\array_key_exists('strategy', $store) && null !== $store['strategy']) {
                    $distanceCalculatorDefinition = new Definition(DistanceCalculator::class);
                    $distanceCalculatorDefinition->setLazy(true);
                    $distanceCalculatorDefinition->setArgument(0, DistanceStrategy::from($store['strategy']));

                    $container->setDefinition('ai.store.distance_calculator.'.$name, $distanceCalculatorDefinition);

                    $arguments[0] = new Reference('ai.store.distance_calculator.'.$name);
                }

                $definition = new Definition(InMemoryStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('milvus' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-milvus-store', MilvusStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Milvus store configuration requires "symfony/ai-milvus-store" package. Try running "composer require symfony/ai-milvus-store".');
            }

            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['database'] ?? $name,
                    $store['collection'],
                    $store['vector_field'],
                    $store['dimensions'],
                ];

                if (\array_key_exists('metric_type', $store)) {
                    $arguments[7] = $store['metric_type'];
                }

                $definition = new Definition(MilvusStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('mongodb' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-mongo-db-store', MongoDbStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('MongoDB store configuration requires "symfony/ai-mongo-db-store" package. Try running "composer require symfony/ai-mongo-db-store".');
            }

            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference($store['client']),
                    $store['database'],
                    $store['collection'] ?? $name,
                    $store['index_name'],
                    $store['vector_field'],
                ];

                if (\array_key_exists('bulk_write', $store)) {
                    $arguments[5] = $store['bulk_write'];
                }

                $definition = new Definition(MongoDbStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('neo4j' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-neo4j-store', Neo4jStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Neo4j store configuration requires "symfony/ai-neo4j-store" package. Try running "composer require symfony/ai-neo4j-store".');
            }

            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['username'],
                    $store['password'],
                    $store['database'] ?? $name,
                    $store['vector_index_name'],
                    $store['node_name'],
                    $store['vector_field'],
                    $store['dimensions'],
                    $store['distance'],
                ];

                if (\array_key_exists('quantization', $store)) {
                    $arguments[10] = $store['quantization'];
                }

                $definition = new Definition(Neo4jStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('elasticsearch' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-elasticsearch-store', ElasticsearchStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Elasticsearch store configuration requires "symfony/ai-elasticsearch-store" package. Try running "composer require symfony/ai-elasticsearch-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(ElasticsearchStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference($store['http_client']),
                        $store['endpoint'],
                        $store['index_name'] ?? $name,
                        $store['vectors_field'],
                        $store['dimensions'],
                        $store['similarity'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('opensearch' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-open-search-store', OpenSearchStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('OpenSearch store configuration requires "symfony/ai-open-search-store" package. Try running "composer require symfony/ai-open-search-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(OpenSearchStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference($store['http_client']),
                        $store['endpoint'],
                        $store['index_name'] ?? $name,
                        $store['vectors_field'],
                        $store['dimensions'],
                        $store['space_type'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('pinecone' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-pinecone-store', PineconeStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Pinecone store configuration requires "symfony/ai-pinecone-store" package. Try running "composer require symfony/ai-pinecone-store".');
            }

            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference($store['client']),
                    $store['index_name'],
                    $store['namespace'] ?? $name,
                    $store['filter'],
                ];

                if (\array_key_exists('top_k', $store)) {
                    $arguments[4] = $store['top_k'];
                }

                $definition = new Definition(PineconeStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('postgres' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-postgres-store', PostgresStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Postgres store configuration requires "symfony/ai-postgres-store" package. Try running "composer require symfony/ai-postgres-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(PostgresStore::class);

                if (\array_key_exists('dbal_connection', $store)) {
                    $definition->setFactory([PostgresStore::class, 'fromDbal']);
                    $arguments = [
                        new Reference($store['dbal_connection']),
                        $store['table_name'] ?? $name,
                        $store['vector_field'],
                    ];
                } else {
                    $pdo = new Definition(\PDO::class);
                    $pdo->setArguments([
                        $store['dsn'],
                        $store['username'] ?? null,
                        $store['password'] ?? null,
                    ]);

                    $arguments = [
                        $pdo,
                        $store['table_name'] ?? $name,
                        $store['vector_field'],
                    ];
                }

                $arguments[3] = PostgresDistance::from($store['distance']);

                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('qdrant' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-qdrant-store', QdrantStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Qdrant store configuration requires "symfony/ai-qdrant-store" package. Try running "composer require symfony/ai-qdrant-store".');
            }

            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['api_key'],
                    $store['collection_name'] ?? $name,
                    $store['dimensions'],
                    $store['distance'],
                ];

                if (\array_key_exists('async', $store)) {
                    $arguments[6] = $store['async'];
                }

                $definition = new Definition(QdrantStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('redis' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-redis-store', RedisStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Redis store configuration requires "symfony/ai-redis-store" package. Try running "composer require symfony/ai-redis-store".');
            }

            foreach ($stores as $name => $store) {
                if (isset($store['client'])) {
                    $redisClient = new Reference($store['client']);
                } else {
                    $redisClient = new Definition(\Redis::class);
                    $redisClient->setArguments([$store['connection_parameters']]);
                }

                $definition = new Definition(RedisStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        $redisClient,
                        $store['index_name'] ?? $name,
                        $store['key_prefix'],
                        RedisDistance::from($store['distance']),
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('supabase' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-supabase-store', SupabaseStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Supabase store configuration requires "symfony/ai-supabase-store" package. Try running "composer require symfony/ai-supabase-store".');
            }

            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference($store['http_client']),
                    $store['url'],
                    $store['api_key'],
                    $store['table'] ?? $name,
                    $store['vector_field'],
                    $store['vector_dimension'],
                ];

                if (\array_key_exists('function_name', $store)) {
                    $arguments[6] = $store['function_name'];
                }

                $definition = new Definition(SupabaseStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.supabase.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('surrealdb' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-surreal-db-store', SurrealDbStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('SurrealDB store configuration requires "symfony/ai-surreal-db-store" package. Try running "composer require symfony/ai-surreal-db-store".');
            }

            foreach ($stores as $name => $store) {
                $arguments = [
                    new Reference('http_client'),
                    $store['endpoint'],
                    $store['username'],
                    $store['password'],
                    $store['namespace'],
                    $store['database'],
                    $store['table'] ?? $name,
                    $store['vector_field'],
                    $store['strategy'],
                    $store['dimensions'],
                ];

                if (\array_key_exists('namespaced_user', $store)) {
                    $arguments[10] = $store['namespaced_user'];
                }

                $definition = new Definition(SurrealDbStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('typesense' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-typesense-store', TypesenseStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Typesense store configuration requires "symfony/ai-typesense-store" package. Try running "composer require symfony/ai-typesense-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(TypesenseStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference('http_client'),
                        $store['endpoint'],
                        $store['api_key'],
                        $store['collection'] ?? $name,
                        $store['vector_field'],
                        $store['dimensions'],
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('weaviate' === $type) {
            if (!ContainerBuilder::willBeAvailable('symfony/ai-weaviate-store', WeaviateStore::class, ['symfony/ai-bundle'])) {
                throw new RuntimeException('Weaviate store configuration requires "symfony/ai-weaviate-store" package. Try running "composer require symfony/ai-weaviate-store".');
            }

            foreach ($stores as $name => $store) {
                $definition = new Definition(WeaviateStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference('http_client'),
                        $store['endpoint'],
                        $store['api_key'],
                        $store['collection'] ?? $name,
                    ])
                    ->addTag('proxy', ['interface' => StoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedStoreInterface::class])
                    ->addTag('ai.store');

                $container->setDefinition('ai.store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }
    }

    /**
     * @param array<string, mixed> $messageStores
     */
    private function processMessageStoreConfig(string $type, array $messageStores, ContainerBuilder $container): void
    {
        if ('cache' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-cache-message-store', CacheMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Cache message store configuration requires "symfony/ai-cache-message-store" package. Try running "composer require symfony/ai-cache-message-store".');
                }

                $arguments = [
                    new Reference($messageStore['service']),
                    $messageStore['key'] ?? $name,
                ];

                if (\array_key_exists('ttl', $messageStore)) {
                    $arguments[2] = $messageStore['ttl'];
                }

                $definition = new Definition(CacheMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $type.'_'.$name);
            }
        }

        if ('cloudflare' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-cloudflare-message-store', CloudflareMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Cloudflare message store configuration requires "symfony/ai-cloudflare-message-store" package. Try running "composer require symfony/ai-cloudflare-message-store".');
                }

                $arguments = [
                    new Reference('http_client'),
                    $messageStore['namespace'],
                    $messageStore['account_id'],
                    $messageStore['api_key'],
                    new Reference('serializer'),
                ];

                if (\array_key_exists('endpoint_url', $messageStore)) {
                    $arguments[5] = $messageStore['endpoint_url'];
                }

                $definition = new Definition(CloudflareMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments($arguments)
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $type.'_'.$name);
            }
        }

        if ('doctrine' === $type) {
            foreach ($messageStores['dbal'] ?? [] as $name => $dbalMessageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-doctrine-message-store', DoctrineDbalMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Doctrine Dbal message store configuration requires "symfony/ai-doctrine-message-store" package. Try running "composer require symfony/ai-doctrine-message-store".');
                }

                $definition = new Definition(DoctrineDbalMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        $dbalMessageStore['table_name'] ?? $name,
                        new Reference(\sprintf('doctrine.dbal.%s_connection', $dbalMessageStore['connection'])),
                        new Reference('serializer'),
                    ])
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.dbal.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $type.'_'.$name);
            }
        }

        if ('meilisearch' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-meilisearch-message-store', MeilisearchMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Meilisearch message store configuration requires "symfony/ai-meilisearch-message-store" package. Try running "composer require symfony/ai-meilisearch-message-store".');
                }

                $definition = new Definition(MeilisearchMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        $messageStore['endpoint'],
                        $messageStore['api_key'],
                        new Reference(ClockInterface::class),
                        $messageStore['index_name'],
                        new Reference('serializer'),
                    ])
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $type.'_'.$name);
            }
        }

        if ('memory' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                $definition = new Definition(InMemoryMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArgument(0, $messageStore['identifier'])
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $type.'_'.$name);
            }
        }

        if ('mongodb' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-mongo-db-message-store', MongoDbMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('MongoDb message store configuration requires "symfony/ai-mongo-db-message-store" package. Try running "composer require symfony/ai-mongo-db-message-store".');
                }

                $definition = new Definition(MongoDbMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference($messageStore['client']),
                        $messageStore['database'],
                        $messageStore['collection'],
                        new Reference('serializer'),
                    ])
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }

        if ('pogocache' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-pogocache-message-store', PogocacheMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Pogocache message store configuration requires "symfony/ai-pogocache-message-store" package. Try running "composer require symfony/ai-pogocache-message-store".');
                }

                $definition = new Definition(PogocacheMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference('http_client'),
                        $messageStore['endpoint'],
                        $messageStore['password'],
                        $messageStore['key'],
                        new Reference('serializer'),
                    ])
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $type.'_'.$name);
            }
        }

        if ('redis' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-redis-message-store', RedisMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Redis message store configuration requires "symfony/ai-redis-message-store" package. Try running "composer require symfony/ai-redis-message-store".');
                }

                if (isset($messageStore['client'])) {
                    $redisClient = new Reference($messageStore['client']);
                } else {
                    $redisClient = new Definition(\Redis::class);
                    $redisClient->setArguments([$messageStore['connection_parameters']]);
                }

                $definition = new Definition(RedisMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        $redisClient,
                        $messageStore['index_name'],
                        new Reference('serializer'),
                    ])
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $type.'_'.$name);
            }
        }

        if ('session' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-session-message-store', SessionMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('Session message store configuration requires "symfony/ai-session-message-store" package. Try running "composer require symfony/ai-session-message-store".');
                }

                $definition = new Definition(SessionMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->setArguments([
                        new Reference('request_stack'),
                        $messageStore['identifier'],
                    ])
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store');

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, MessageStoreInterface::class, $type.'_'.$name);
            }
        }

        if ('surrealdb' === $type) {
            foreach ($messageStores as $name => $messageStore) {
                if (!ContainerBuilder::willBeAvailable('symfony/ai-surreal-db-message-store', SurrealDbMessageStore::class, ['symfony/ai-bundle'])) {
                    throw new RuntimeException('SurrealDb message store configuration requires "symfony/ai-surreal-db-message-store" package. Try running "composer require symfony/ai-surreal-db-message-store".');
                }

                $arguments = [
                    new Reference('http_client'),
                    $messageStore['endpoint'],
                    $messageStore['username'],
                    $messageStore['password'],
                    $messageStore['namespace'],
                    $messageStore['database'],
                    new Reference('serializer'),
                    $messageStore['table'] ?? $name,
                ];

                if (\array_key_exists('namespaced_user', $messageStore)) {
                    $arguments[8] = $messageStore['namespaced_user'];
                }

                $definition = new Definition(SurrealDbMessageStore::class);
                $definition
                    ->setLazy(true)
                    ->addTag('proxy', ['interface' => MessageStoreInterface::class])
                    ->addTag('proxy', ['interface' => ManagedMessageStoreInterface::class])
                    ->addTag('ai.message_store')
                    ->setArguments($arguments);

                $container->setDefinition('ai.message_store.'.$type.'.'.$name, $definition);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, StoreInterface::class, $name);
                $container->registerAliasForArgument('ai.message_store.'.$type.'.'.$name, StoreInterface::class, $type.'_'.$name);
            }
        }
    }

    /**
     * @param array{
     *     agent: string,
     *     message_store: string,
     * } $configuration
     */
    private function processChatConfig(string $name, array $configuration, ContainerBuilder $container): void
    {
        $definition = new Definition(Chat::class);
        $definition
            ->setLazy(true)
            ->setArguments([
                new Reference($configuration['agent']),
                new Reference($configuration['message_store']),
            ])
            ->addTag('proxy', ['interface' => ChatInterface::class])
            ->addTag('ai.chat');

        $container->setDefinition('ai.chat.'.$name, $definition);
        $container->registerAliasForArgument('ai.chat.'.$name, ChatInterface::class, $name);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processVectorizerConfig(string $name, array $config, ContainerBuilder $container): void
    {
        $vectorizerDefinition = new Definition(Vectorizer::class, [
            new Reference($config['platform']),
            $config['model'],
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]);
        $vectorizerDefinition->addTag('ai.vectorizer', ['name' => $name]);
        $serviceId = 'ai.vectorizer.'.$name;
        $container->setDefinition($serviceId, $vectorizerDefinition);
        $container->registerAliasForArgument($serviceId, VectorizerInterface::class, (new Target((string) $name))->getParsedName());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processIndexerConfig(int|string $name, array $config, ContainerBuilder $container): void
    {
        $transformers = [];
        foreach ($config['transformers'] as $transformer) {
            $transformers[] = new Reference($transformer);
        }

        $filters = [];
        foreach ($config['filters'] as $filter) {
            $filters[] = new Reference($filter);
        }

        $processorServiceId = 'ai.indexer.'.$name.'.processor';
        $processorDefinition = new Definition(DocumentProcessor::class, [
            new Reference($config['vectorizer']),
            new Reference($config['store']),
            $filters,
            $transformers,
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]);
        $container->setDefinition($processorServiceId, $processorDefinition);

        $serviceId = 'ai.indexer.'.$name;

        if (null === $config['loader']) {
            if (null !== $config['source']) {
                throw new InvalidArgumentException(\sprintf('Indexer "%s" has a "source" configured but no "loader". A loader is required to process sources.', $name));
            }

            // No loader configured: DocumentIndexer for direct document indexing
            $definition = new Definition(DocumentIndexer::class, [
                new Reference($processorServiceId),
            ]);
        } elseif (null !== $config['source']) {
            // Loader and source configured: ConfiguredSourceIndexer wrapping SourceIndexer
            $innerServiceId = 'ai.indexer.'.$name.'.inner';
            $indexerDefinition = new Definition(SourceIndexer::class, [
                new Reference($config['loader']),
                new Reference($processorServiceId),
            ]);
            $container->setDefinition($innerServiceId, $indexerDefinition);

            $definition = new Definition(ConfiguredSourceIndexer::class, [
                new Reference($innerServiceId),
                $config['source'],
            ]);
        } else {
            // Loader configured without source: SourceIndexer for runtime source input
            $definition = new Definition(SourceIndexer::class, [
                new Reference($config['loader']),
                new Reference($processorServiceId),
            ]);
        }

        $definition->addTag('ai.indexer', ['name' => $name]);
        $container->setDefinition($serviceId, $definition);
        $container->registerAliasForArgument($serviceId, IndexerInterface::class, (new Target((string) $name))->getParsedName());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processRetrieverConfig(int|string $name, array $config, ContainerBuilder $container): void
    {
        $definition = new Definition(Retriever::class, [
            new Reference($config['store']),
            new Reference($config['vectorizer']),
            new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]);
        $definition->addTag('ai.retriever', ['name' => $name]);

        $serviceId = 'ai.retriever.'.$name;
        $container->setDefinition($serviceId, $definition);
        $container->registerAliasForArgument($serviceId, RetrieverInterface::class, (new Target((string) $name))->getParsedName());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processMultiAgentConfig(string $name, array $config, ContainerBuilder $container): void
    {
        $orchestratorServiceId = self::normalizeAgentServiceId($config['orchestrator']);

        $handoffReferences = [];

        foreach ($config['handoffs'] as $agentName => $whenConditions) {
            // Create handoff definitions directly (not as separate services)
            // The container will inline simple value objects like Handoff
            $handoffReferences[] = new Definition(Handoff::class, [
                new Reference(self::normalizeAgentServiceId($agentName)),
                $whenConditions,
            ]);
        }

        $multiAgentId = 'ai.multi_agent.'.$name;
        $multiAgentDefinition = new Definition(MultiAgent::class, [
            new Reference($orchestratorServiceId),
            $handoffReferences,
            new Reference(self::normalizeAgentServiceId($config['fallback'])),
            $name,
        ]);

        $multiAgentDefinition->addTag('ai.multi_agent', ['name' => $name]);
        $multiAgentDefinition->addTag('ai.agent', ['name' => $name]);

        $container->setDefinition($multiAgentId, $multiAgentDefinition);
        $container->registerAliasForArgument($multiAgentId, AgentInterface::class, (new Target($name))->getParsedName());
    }

    /**
     * Ensures an agent name has the 'ai.agent.' prefix for service resolution.
     *
     * @param non-empty-string $agentName
     *
     * @return non-empty-string
     */
    private static function normalizeAgentServiceId(string $agentName): string
    {
        return str_starts_with($agentName, 'ai.agent.') ? $agentName : 'ai.agent.'.$agentName;
    }

    /**
     * Process model configuration and pass it to ModelCatalog services.
     *
     * @param array<string, array{class: class-string, capabilities: list<Capability|string>}> $models
     */
    private function processModelConfig(string $platformName, array $models, ContainerBuilder $builder): void
    {
        $modelCatalogId = 'ai.platform.model_catalog.'.$platformName;

        // Handle special cases for platform name mapping
        if ('vertexai' === $platformName) {
            $modelCatalogId = 'ai.platform.model_catalog.vertexai.gemini';
        }

        if (!$builder->hasDefinition($modelCatalogId)) {
            return;
        }

        $additionalModels = [];
        foreach ($models as $modelName => $modelConfig) {
            $additionalModels[$modelName] = [
                'class' => $modelConfig['class'],
                'capabilities' => $modelConfig['capabilities'],
            ];
        }

        if ([] === $additionalModels) {
            return;
        }

        $definition = $builder->getDefinition($modelCatalogId);
        $definition->setArguments([$additionalModels]);
    }
}
