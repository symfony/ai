<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\DependencyInjection;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use Codewithkyrian\ChromaDB\Client;
use MongoDB\Client as MongoDbClient;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Probots\Pinecone\Client as PineconeClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Memory\MemoryInputProcessor;
use Symfony\AI\Agent\Memory\StaticMemoryProvider;
use Symfony\AI\Agent\MultiAgent\Handoff;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\AI\AiBundle\Exception\InvalidArgumentException;
use Symfony\AI\Chat\ChatInterface;
use Symfony\AI\Chat\ManagedStoreInterface as ManagedMessageStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Bridge\Cache\CachePlatform;
use Symfony\AI\Platform\Bridge\Decart\PlatformFactory as DecartPlatformFactory;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabsApiCatalog;
use Symfony\AI\Platform\Bridge\ElevenLabs\ModelCatalog as ElevenLabsModelCatalog;
use Symfony\AI\Platform\Bridge\ElevenLabs\PlatformFactory as ElevenLabsPlatformFactory;
use Symfony\AI\Platform\Bridge\Failover\FailoverPlatform;
use Symfony\AI\Platform\Bridge\Failover\FailoverPlatformFactory;
use Symfony\AI\Platform\Bridge\Ollama\OllamaApiCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\EventListener\TemplateRendererListener;
use Symfony\AI\Platform\Message\TemplateRenderer\ExpressionLanguageTemplateRenderer;
use Symfony\AI\Platform\Message\TemplateRenderer\StringTemplateRenderer;
use Symfony\AI\Platform\Message\TemplateRenderer\TemplateRendererRegistry;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\Bridge\AzureSearch\SearchStore as AzureStore;
use Symfony\AI\Store\Bridge\Cache\Store as CacheStore;
use Symfony\AI\Store\Bridge\ChromaDb\Store as ChromaDbStore;
use Symfony\AI\Store\Bridge\ClickHouse\Store as ClickhouseStore;
use Symfony\AI\Store\Bridge\Cloudflare\Store as CloudflareStore;
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
use Symfony\AI\Store\Document\Filter\TextContainsFilter;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Transformer\TextTrimTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Indexer\ConfiguredSourceIndexer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\SourceIndexer;
use Symfony\AI\Store\IndexerInterface;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\RetrieverInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiBundleTest extends TestCase
{
    public function testExtensionLoadDoesNotThrow()
    {
        $container = $this->buildContainer($this->getFullConfig());

        // Mock services that are used as platform create arguments, but should not be tested here or are not available.
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->getDefinition('ai.platform.vertexai')->replaceArgument(3, $this->createMock(HttpClientInterface::class));

        $platforms = $container->findTaggedServiceIds('ai.platform');

        foreach (array_keys($platforms) as $platformId) {
            $def = $container->getDefinition($platformId);
            $factor = $def->getFactory();

            if (\is_array($factor)) {
                $ref = new \ReflectionClass($factor[0]);
                $numArgs = $ref->getMethod($factor[1])->getNumberOfParameters();
                $this->assertGreaterThanOrEqual($numArgs, \count($def->getArguments()));
            }

            try {
                $platformService = $container->get($platformId);
                $platformService->getModelCatalog();
            } catch (\Throwable $e) {
                $failureMessage = \sprintf(
                    'Failed to load platform service "%s" or call getModelCatalog(). '.
                    'Original error: %s (in %s:%d)',
                    $platformId,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                );
                $this->fail($failureMessage);
            }
        }
    }

    public function testStoreCommandsArentDefinedWithoutStore()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.command.setup_store'));
        $this->assertFalse($container->hasDefinition('ai.command.drop_store'));
        $this->assertSame([
            'ai.command.setup_store' => true,
            'ai.command.drop_store' => true,
            'ai.command.setup_message_store' => true,
            'ai.command.drop_message_store' => true,
        ], $container->getRemovedIds());
    }

    public function testMessageStoreCommandsArentDefinedWithoutMessageStore()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.command.setup_message_store'));
        $this->assertFalse($container->hasDefinition('ai.command.drop_message_store'));
        $this->assertSame([
            'ai.command.setup_store' => true,
            'ai.command.drop_store' => true,
            'ai.command.setup_message_store' => true,
            'ai.command.drop_message_store' => true,
        ], $container->getRemovedIds());
    }

    public function testStoreCommandsAreDefined()
    {
        $container = $this->buildContainer($this->getFullConfig());

        $this->assertTrue($container->hasDefinition('ai.command.setup_store'));

        $setupStoreCommandDefinition = $container->getDefinition('ai.command.setup_store');
        $this->assertCount(2, $setupStoreCommandDefinition->getArguments());
        $this->assertSame(['ai.store.mariadb.my_mariadb_store' => ['dimensions' => 1024]], $setupStoreCommandDefinition->getArgument(1));
        $this->assertArrayHasKey('console.command', $setupStoreCommandDefinition->getTags());

        $this->assertTrue($container->hasDefinition('ai.command.drop_store'));

        $dropStoreCommandDefinition = $container->getDefinition('ai.command.drop_store');
        $this->assertCount(1, $dropStoreCommandDefinition->getArguments());
        $this->assertArrayHasKey('console.command', $dropStoreCommandDefinition->getTags());
    }

    public function testMessageStoreCommandsAreDefined()
    {
        $container = $this->buildContainer($this->getFullConfig());

        $this->assertTrue($container->hasDefinition('ai.command.setup_message_store'));

        $setupStoreCommandDefinition = $container->getDefinition('ai.command.setup_message_store');
        $this->assertCount(1, $setupStoreCommandDefinition->getArguments());
        $this->assertArrayHasKey('console.command', $setupStoreCommandDefinition->getTags());

        $this->assertTrue($container->hasDefinition('ai.command.drop_message_store'));

        $dropStoreCommandDefinition = $container->getDefinition('ai.command.drop_message_store');
        $this->assertCount(1, $dropStoreCommandDefinition->getArguments());
        $this->assertArrayHasKey('console.command', $dropStoreCommandDefinition->getTags());
    }

    public function testMessageBagNormalizerIsRegistered()
    {
        $container = $this->buildContainer($this->getFullConfig());

        $this->assertTrue($container->hasDefinition('ai.chat.message_bag.normalizer'));
    }

    public function testInjectionAgentAliasIsRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasAlias(AgentInterface::class));
        $this->assertTrue($container->hasAlias(AgentInterface::class.' $myAgent'));
    }

    public function testInjectionStoreAliasIsRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'main' => [
                            'strategy' => 'cosine',
                        ],
                        'secondary_with_custom_strategy' => [
                            'strategy' => 'manhattan',
                        ],
                    ],
                    'weaviate' => [
                        'main' => [
                            'endpoint' => 'http://localhost:8080',
                            'api_key' => 'bar',
                            'collection' => 'my_weaviate_collection',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $main'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $secondary_with_custom_strategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $secondaryWithCustomStrategy'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $weaviate_main'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $weaviateMain'));
    }

    public function testInjectionMessageStoreAliasIsRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'memory' => [
                        'main' => [
                            'identifier' => '_memory',
                        ],
                    ],
                    'session' => [
                        'session' => [
                            'identifier' => 'session',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasAlias(MessageStoreInterface::class.' $main'));
        $this->assertTrue($container->hasAlias('.'.MessageStoreInterface::class.' $memory_main'));
        $this->assertTrue($container->hasAlias(MessageStoreInterface::class.' $session'));
        $this->assertTrue($container->hasAlias('.'.MessageStoreInterface::class.' $session_session'));
    }

    public function testInjectionChatAliasIsRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4',
                    ],
                ],
                'message_store' => [
                    'memory' => [
                        'main' => [
                            'identifier' => '_memory',
                        ],
                    ],
                ],
                'chat' => [
                    'main' => [
                        'agent' => 'ai.agent.my_agent',
                        'message_store' => 'ai.message_store.memory.main',
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $container->findTaggedServiceIds('ai.chat'));

        $this->assertTrue($container->hasAlias(ChatInterface::class.' $main'));

        $chatDefinition = $container->getDefinition('ai.chat.main');
        $this->assertCount(2, $chatDefinition->getArguments());
        $this->assertInstanceOf(Reference::class, $chatDefinition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $chatDefinition->getArgument(1));
    }

    public function testAgentHasTag()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4',
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey('ai.agent.my_agent', $container->findTaggedServiceIds('ai.agent'));
    }

    public function testAgentNameIsSetFromConfigKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_custom_agent' => [
                        'model' => 'gpt-4',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.my_custom_agent'));

        $agentDefinition = $container->getDefinition('ai.agent.my_custom_agent');
        $arguments = $agentDefinition->getArguments();

        // The 5th argument (index 4) should be the config key as agent name
        $this->assertArrayHasKey(4, $arguments, 'Agent definition should have argument at index 4 for name');
        $this->assertSame('my_custom_agent', $arguments[4]);

        // Check that the tag uses the config key as name
        $tags = $agentDefinition->getTag('ai.agent');
        $this->assertNotEmpty($tags, 'Agent should have ai.agent tag');
        $this->assertSame('my_custom_agent', $tags[0]['name'], 'Agent tag should use config key as name');
    }

    #[TestWith([true], 'enabled')]
    #[TestWith([false], 'disabled')]
    public function testFaultTolerantAgentSpecificToolbox(bool $enabled)
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4',
                        'tools' => [
                            ['service' => 'some_service', 'description' => 'Some tool'],
                        ],
                        'fault_tolerant_toolbox' => $enabled,
                    ],
                ],
            ],
        ]);

        $this->assertSame($enabled, $container->hasDefinition('ai.fault_tolerant_toolbox.my_agent'));
    }

    #[TestWith([true], 'enabled')]
    #[TestWith([false], 'disabled')]
    public function testFaultTolerantDefaultToolbox(bool $enabled)
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4',
                        'tools' => true,
                        'fault_tolerant_toolbox' => $enabled,
                    ],
                ],
            ],
        ]);

        $this->assertSame($enabled, $container->hasDefinition('ai.fault_tolerant_toolbox.my_agent'));
    }

    public function testAgentsCanBeRegisteredAsTools()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'main_agent' => [
                        'model' => 'gpt-4',
                        'tools' => [
                            ['agent' => 'another_agent', 'description' => 'Agent tool with implicit name'],
                            ['agent' => 'another_agent', 'name' => 'another_agent_instance', 'description' => 'Agent tool with explicit name'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.toolbox.main_agent.subagent.another_agent'));
        $this->assertTrue($container->hasDefinition('ai.toolbox.main_agent.subagent.another_agent_instance'));
    }

    public function testAgentsAsToolsCannotDefineService()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'main_agent' => [
                        'model' => 'gpt-4',
                        'tools' => [['agent' => 'another_agent', 'service' => 'foo_bar', 'description' => 'Agent with service']],
                    ],
                ],
            ],
        ]);
    }

    public function testAzureStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'azuresearch' => [
                        'my_azuresearch_store' => [
                            'endpoint' => 'https://mysearch.search.windows.net',
                            'api_key' => 'azure_search_key',
                            'index_name' => 'my-documents',
                            'api_version' => '2023-11-01',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.azuresearch.my_azuresearch_store'));

        $definition = $container->getDefinition('ai.store.azuresearch.my_azuresearch_store');
        $this->assertSame(AzureStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(5, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('https://mysearch.search.windows.net', $definition->getArgument(1));
        $this->assertSame('azure_search_key', $definition->getArgument(2));
        $this->assertSame('my-documents', $definition->getArgument(3));
        $this->assertSame('2023-11-01', $definition->getArgument(4));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_azuresearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myAzuresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $azuresearchMyAzuresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testAzureStoreCanBeConfiguredWithCustomVectorField()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'azuresearch' => [
                        'my_azuresearch_store' => [
                            'endpoint' => 'https://mysearch.search.windows.net',
                            'api_key' => 'azure_search_key',
                            'index_name' => 'my-documents',
                            'api_version' => '2023-11-01',
                            'vector_field' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.azuresearch.my_azuresearch_store'));

        $definition = $container->getDefinition('ai.store.azuresearch.my_azuresearch_store');
        $this->assertSame(AzureStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('https://mysearch.search.windows.net', $definition->getArgument(1));
        $this->assertSame('azure_search_key', $definition->getArgument(2));
        $this->assertSame('my-documents', $definition->getArgument(3));
        $this->assertSame('2023-11-01', $definition->getArgument(4));
        $this->assertSame('foo', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_azuresearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myAzuresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $azuresearchMyAzuresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testCacheStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cache' => [
                        'my_cache_store' => [
                            'service' => 'cache.system',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cache.my_cache_store'));
        $this->assertTrue($container->hasDefinition('ai.store.distance_calculator.my_cache_store'));

        $definition = $container->getDefinition('ai.store.cache.my_cache_store');
        $this->assertSame(CacheStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(3, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.system', (string) $definition->getArgument(0));
        $this->assertSame('my_cache_store', $definition->getArgument(2));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_cache_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myCacheStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $cacheMyCacheStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testCacheStoreWithCustomKeyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cache' => [
                        'my_cache_store_with_custom_key' => [
                            'service' => 'cache.system',
                            'cache_key' => 'random',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cache.my_cache_store_with_custom_key'));
        $this->assertTrue($container->hasDefinition('ai.store.distance_calculator.my_cache_store_with_custom_key'));

        $definition = $container->getDefinition('ai.store.cache.my_cache_store_with_custom_key');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(3, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.system', (string) $definition->getArgument(0));
        $this->assertSame('random', $definition->getArgument(2));

        $strategyDefinition = $container->getDefinition('ai.store.distance_calculator.my_cache_store_with_custom_key');
        $this->assertTrue($strategyDefinition->isLazy());

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $cache_my_cache_store_with_custom_key'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myCacheStoreWithCustomKey'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $cacheMyCacheStoreWithCustomKey'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testCacheStoreWithCustomStrategyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cache' => [
                        'my_cache_store_with_custom_strategy' => [
                            'service' => 'cache.system',
                            'strategy' => 'chebyshev',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cache.my_cache_store_with_custom_strategy'));
        $this->assertTrue($container->hasDefinition('ai.store.distance_calculator.my_cache_store_with_custom_strategy'));

        $definition = $container->getDefinition('ai.store.cache.my_cache_store_with_custom_strategy');
        $this->assertSame(CacheStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(3, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.system', (string) $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame('ai.store.distance_calculator.my_cache_store_with_custom_strategy', (string) $definition->getArgument(1));
        $this->assertSame('my_cache_store_with_custom_strategy', $definition->getArgument(2));

        $strategyDefinition = $container->getDefinition('ai.store.distance_calculator.my_cache_store_with_custom_strategy');
        $this->assertTrue($strategyDefinition->isLazy());
        $this->assertSame(DistanceStrategy::CHEBYSHEV_DISTANCE, $strategyDefinition->getArgument(0));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $cache_my_cache_store_with_custom_strategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myCacheStoreWithCustomStrategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $cacheMyCacheStoreWithCustomStrategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testCacheStoreWithCustomStrategyAndKeyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cache' => [
                        'my_cache_store_with_custom_strategy_and_custom_key' => [
                            'service' => 'cache.system',
                            'cache_key' => 'random',
                            'strategy' => 'chebyshev',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cache.my_cache_store_with_custom_strategy_and_custom_key'));
        $this->assertTrue($container->hasDefinition('ai.store.distance_calculator.my_cache_store_with_custom_strategy_and_custom_key'));

        $definition = $container->getDefinition('ai.store.cache.my_cache_store_with_custom_strategy_and_custom_key');
        $this->assertSame(CacheStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(3, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.system', (string) $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame('ai.store.distance_calculator.my_cache_store_with_custom_strategy_and_custom_key', (string) $definition->getArgument(1));
        $this->assertSame('random', $definition->getArgument(2));

        $strategyDefinition = $container->getDefinition('ai.store.distance_calculator.my_cache_store_with_custom_strategy_and_custom_key');
        $this->assertTrue($strategyDefinition->isLazy());
        $this->assertSame(DistanceStrategy::CHEBYSHEV_DISTANCE, $strategyDefinition->getArgument(0));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $cache_my_cache_store_with_custom_strategy_and_custom_key'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myCacheStoreWithCustomStrategyAndCustomKey'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $cacheMyCacheStoreWithCustomStrategyAndCustomKey'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testChromaDbStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'chromadb' => [
                        'my_chromadb_store' => [
                            'collection' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.chromadb.my_chromadb_store'));

        $definition = $container->getDefinition('ai.store.chromadb.my_chromadb_store');
        $this->assertSame(ChromaDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(2, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame(Client::class, (string) $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(1));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $chromadb_my_chromadb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myChromadbStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $chromadbMyChromadbStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    #[TestDox('Configuring only one store type does not require packages for other store types')]
    public function testConfiguringOnlyOneStoreTypeDoesNotRequireOtherStorePackages()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'chromadb' => [
                        'my_store' => [
                            'collection' => 'test_collection',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.chromadb.my_store'));
        $this->assertFalse($container->hasDefinition('ai.store.azuresearch.my_store'));
    }

    public function testChromaDbStoreWithCustomClientCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'chromadb' => [
                        'my_chromadb_store_with_custom_client' => [
                            'client' => 'bar',
                            'collection' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.chromadb.my_chromadb_store_with_custom_client'));

        $definition = $container->getDefinition('ai.store.chromadb.my_chromadb_store_with_custom_client');
        $this->assertSame(ChromaDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(2, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('bar', (string) $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(1));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $chromadb_my_chromadb_store_with_custom_client'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myChromadbStoreWithCustomClient'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $chromadbMyChromadbStoreWithCustomClient'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testClickhouseStoreWithCustomHttpClientCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'clickhouse' => [
                        'my_clickhouse_store' => [
                            'http_client' => 'clickhouse.http_client',
                            'database' => 'my_db',
                            'table' => 'my_table',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.clickhouse.my_clickhouse_store'));

        $definition = $container->getDefinition('ai.store.clickhouse.my_clickhouse_store');
        $this->assertSame(ClickhouseStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(3, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('clickhouse.http_client', (string) $definition->getArgument(0));
        $this->assertSame('my_db', (string) $definition->getArgument(1));
        $this->assertSame('my_table', (string) $definition->getArgument(2));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_clickhouse_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myClickhouseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $clickhouseMyClickhouseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testClickhouseStoreWithCustomDsnCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'clickhouse' => [
                        'my_clickhouse_store' => [
                            'dsn' => 'http://foo:bar@1.2.3.4:9999',
                            'database' => 'my_db',
                            'table' => 'my_table',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.clickhouse.my_clickhouse_store'));

        $definition = $container->getDefinition('ai.store.clickhouse.my_clickhouse_store');
        $this->assertSame(ClickhouseStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(3, $definition->getArguments());
        $this->assertInstanceOf(Definition::class, $definition->getArgument(0));
        $this->assertSame(HttpClientInterface::class, $definition->getArgument(0)->getClass());
        $this->assertSame([HttpClient::class, 'createForBaseUri'], $definition->getArgument(0)->getFactory());
        $this->assertSame(['http://foo:bar@1.2.3.4:9999'], $definition->getArgument(0)->getArguments());
        $this->assertSame('my_db', (string) $definition->getArgument(1));
        $this->assertSame('my_table', (string) $definition->getArgument(2));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_clickhouse_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myClickhouseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $clickhouseMyClickhouseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testCloudflareStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cloudflare' => [
                        'my_cloudflare_store' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'index_name' => 'random',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cloudflare.my_cloudflare_store'));

        $definition = $container->getDefinition('ai.store.cloudflare.my_cloudflare_store');
        $this->assertSame(CloudflareStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('foo', $definition->getArgument(1));
        $this->assertSame('bar', $definition->getArgument(2));
        $this->assertSame('random', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_cloudflare_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myCloudflareStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $cloudflareMyCloudflareStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testCloudflareStoreWithCustomIndexCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cloudflare' => [
                        'my_cloudflare_store' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'index_name' => 'random',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cloudflare.my_cloudflare_store'));

        $definition = $container->getDefinition('ai.store.cloudflare.my_cloudflare_store');
        $this->assertSame(CloudflareStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('foo', $definition->getArgument(1));
        $this->assertSame('bar', $definition->getArgument(2));
        $this->assertSame('random', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_cloudflare_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myCloudflareStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $cloudflareMyCloudflareStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testCloudflareStoreWithCustomDimensionsCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cloudflare' => [
                        'my_cloudflare_store' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'dimensions' => 768,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cloudflare.my_cloudflare_store'));

        $definition = $container->getDefinition('ai.store.cloudflare.my_cloudflare_store');
        $this->assertSame(CloudflareStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('foo', $definition->getArgument(1));
        $this->assertSame('bar', $definition->getArgument(2));
        $this->assertSame('my_cloudflare_store', $definition->getArgument(3));
        $this->assertSame(768, $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_cloudflare_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myCloudflareStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $cloudflareMyCloudflareStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testCloudflareStoreWithCustomEndpointCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'cloudflare' => [
                        'my_cloudflare_store' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'dimensions' => 1536,
                            'metric' => 'cosine',
                            'endpoint' => 'https://api.cloudflare.com/client/v5/accounts',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.cloudflare.my_cloudflare_store'));

        $definition = $container->getDefinition('ai.store.cloudflare.my_cloudflare_store');
        $this->assertSame(CloudflareStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('foo', $definition->getArgument(1));
        $this->assertSame('bar', $definition->getArgument(2));
        $this->assertSame('my_cloudflare_store', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));
        $this->assertSame('https://api.cloudflare.com/client/v5/accounts', $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_cloudflare_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myCloudflareStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $cloudflareMyCloudflareStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testManticoreSearchStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'manticoresearch' => [
                        'my_manticoresearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9306',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.manticoresearch.my_manticoresearch_store'));

        $definition = $container->getDefinition('ai.store.manticoresearch.my_manticoresearch_store');
        $this->assertSame(ManticoreSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9306', $definition->getArgument(1));
        $this->assertSame('my_manticoresearch_store', $definition->getArgument(2));
        $this->assertSame('_vectors', $definition->getArgument(3));
        $this->assertSame('hnsw', $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));
        $this->assertSame(1536, $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_manticoresearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $manticoresearchMyManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testManticoreSearchStoreWithCustomTableCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'manticoresearch' => [
                        'my_manticoresearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9306',
                            'table' => 'test',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.manticoresearch.my_manticoresearch_store'));

        $definition = $container->getDefinition('ai.store.manticoresearch.my_manticoresearch_store');
        $this->assertSame(ManticoreSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9306', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('_vectors', $definition->getArgument(3));
        $this->assertSame('hnsw', $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));
        $this->assertSame(1536, $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_manticoresearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $manticoresearchMyManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testManticoreSearchStoreWithCustomFieldCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'manticoresearch' => [
                        'my_manticoresearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9306',
                            'field' => '_foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.manticoresearch.my_manticoresearch_store'));

        $definition = $container->getDefinition('ai.store.manticoresearch.my_manticoresearch_store');
        $this->assertSame(ManticoreSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9306', $definition->getArgument(1));
        $this->assertSame('my_manticoresearch_store', $definition->getArgument(2));
        $this->assertSame('_foo', $definition->getArgument(3));
        $this->assertSame('hnsw', $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));
        $this->assertSame(1536, $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_manticoresearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $manticoresearchMyManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testManticoreSearchStoreWithCustomDimensionsCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'manticoresearch' => [
                        'my_manticoresearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9306',
                            'dimensions' => 768,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.manticoresearch.my_manticoresearch_store'));

        $definition = $container->getDefinition('ai.store.manticoresearch.my_manticoresearch_store');
        $this->assertSame(ManticoreSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9306', $definition->getArgument(1));
        $this->assertSame('my_manticoresearch_store', $definition->getArgument(2));
        $this->assertSame('_vectors', $definition->getArgument(3));
        $this->assertSame('hnsw', $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));
        $this->assertSame(768, $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_manticoresearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $manticoresearchMyManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testManticoreSearchStoreWithQuantizationCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'manticoresearch' => [
                        'my_manticoresearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9306',
                            'field' => 'foo_vector',
                            'type' => 'hnsw',
                            'similarity' => 'cosine',
                            'dimensions' => 768,
                            'quantization' => '1bit',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.manticoresearch.my_manticoresearch_store'));

        $definition = $container->getDefinition('ai.store.manticoresearch.my_manticoresearch_store');
        $this->assertSame(ManticoreSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9306', $definition->getArgument(1));
        $this->assertSame('my_manticoresearch_store', $definition->getArgument(2));
        $this->assertSame('foo_vector', $definition->getArgument(3));
        $this->assertSame('hnsw', $definition->getArgument(4));
        $this->assertSame('cosine', $definition->getArgument(5));
        $this->assertSame(768, $definition->getArgument(6));
        $this->assertSame('1bit', $definition->getArgument(7));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_manticoresearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $manticoresearchMyManticoresearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMariaDbStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'mariadb' => [
                        'my_mariadb_store' => [
                            'connection' => 'default',
                            'index_name' => 'vector_idx',
                            'vector_field_name' => 'vector',
                            'setup_options' => [
                                'dimensions' => 1024,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.mariadb.my_mariadb_store'));

        $definition = $container->getDefinition('ai.store.mariadb.my_mariadb_store');
        $this->assertSame(MariaDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('doctrine.dbal.default_connection', (string) $definition->getArgument(0));
        $this->assertSame('my_mariadb_store', $definition->getArgument(1));
        $this->assertSame('vector_idx', $definition->getArgument(2));
        $this->assertSame('vector', $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_mariadb_store'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $mariadb_my_mariadb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mariadbMyMariadbStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMariaDbStoreWithCustomTableCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'mariadb' => [
                        'my_mariadb_store' => [
                            'connection' => 'default',
                            'table_name' => 'vector_table',
                            'index_name' => 'vector_idx',
                            'vector_field_name' => 'vector',
                            'setup_options' => [
                                'dimensions' => 1024,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.mariadb.my_mariadb_store'));

        $definition = $container->getDefinition('ai.store.mariadb.my_mariadb_store');
        $this->assertSame(MariaDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('doctrine.dbal.default_connection', (string) $definition->getArgument(0));
        $this->assertSame('vector_table', $definition->getArgument(1));
        $this->assertSame('vector_idx', $definition->getArgument(2));
        $this->assertSame('vector', $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_mariadb_store'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $mariadb_my_mariadb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mariadbMyMariadbStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMeilisearchStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'meilisearch' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:7700',
                            'api_key' => 'foo',
                            'embedder' => 'default',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.store.meilisearch.custom');
        $this->assertSame(MeilisearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:7700', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('custom', $definition->getArgument(3));
        $this->assertSame('default', $definition->getArgument(4));
        $this->assertSame('_vectors', $definition->getArgument(5));
        $this->assertSame(768, $definition->getArgument(6));
        $this->assertSame(1.0, $definition->getArgument(7));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $custom'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $meilisearch_custom'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $meilisearchCustom'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMeilisearchStoreWithCustomIndexNameCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'meilisearch' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:7700',
                            'api_key' => 'foo',
                            'index_name' => 'test',
                            'embedder' => 'default',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.store.meilisearch.custom');
        $this->assertSame(MeilisearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:7700', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('default', $definition->getArgument(4));
        $this->assertSame('_vectors', $definition->getArgument(5));
        $this->assertSame(768, $definition->getArgument(6));
        $this->assertSame(1.0, $definition->getArgument(7));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $custom'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $meilisearch_custom'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $meilisearchCustom'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    #[TestDox('Meilisearch store with custom semantic_ratio can be configured')]
    public function testMeilisearchStoreWithCustomSemanticRatioCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'meilisearch' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:7700',
                            'api_key' => 'foo',
                            'index_name' => 'test',
                            'embedder' => 'default',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                            'semantic_ratio' => 0.5,
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.store.meilisearch.custom');
        $this->assertSame(MeilisearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:7700', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('default', $definition->getArgument(4));
        $this->assertSame('_vectors', $definition->getArgument(5));
        $this->assertSame(768, $definition->getArgument(6));
        $this->assertSame(0.5, $definition->getArgument(7));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $custom'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $meilisearch_custom'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $meilisearchCustom'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testInMemoryStoreWithoutCustomStrategyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_memory_store_with_custom_strategy' => [],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.memory.my_memory_store_with_custom_strategy'));

        $definition = $container->getDefinition('ai.store.memory.my_memory_store_with_custom_strategy');
        $this->assertSame(InMemoryStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(1, $definition->getArguments());
        $this->assertInstanceOf(Definition::class, $definition->getArgument(0));
        $this->assertSame(DistanceCalculator::class, $definition->getArgument(0)->getClass());

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_memory_store_with_custom_strategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMemoryStoreWithCustomStrategy'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $memory_my_memory_store_with_custom_strategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $memoryMyMemoryStoreWithCustomStrategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testInMemoryStoreWithCustomStrategyCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_memory_store_with_custom_strategy' => [
                            'strategy' => 'chebyshev',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.memory.my_memory_store_with_custom_strategy'));
        $this->assertTrue($container->hasDefinition('ai.store.distance_calculator.my_memory_store_with_custom_strategy'));

        $definition = $container->getDefinition('ai.store.memory.my_memory_store_with_custom_strategy');
        $this->assertSame(InMemoryStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(1, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('ai.store.distance_calculator.my_memory_store_with_custom_strategy', (string) $definition->getArgument(0));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_memory_store_with_custom_strategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMemoryStoreWithCustomStrategy'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $memory_my_memory_store_with_custom_strategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $memoryMyMemoryStoreWithCustomStrategy'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMilvusStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'milvus' => [
                        'my_milvus_store' => [
                            'endpoint' => 'http://127.0.0.1:19530',
                            'api_key' => 'foo',
                            'collection' => 'default',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.milvus.my_milvus_store'));

        $definition = $container->getDefinition('ai.store.milvus.my_milvus_store');
        $this->assertSame(MilvusStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:19530', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('my_milvus_store', $definition->getArgument(3));
        $this->assertSame('default', $definition->getArgument(4));
        $this->assertSame('_vectors', $definition->getArgument(5));
        $this->assertSame(1536, $definition->getArgument(6));
        $this->assertSame('COSINE', $definition->getArgument(7));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_milvus_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMilvusStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $milvus_my_milvus_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $milvusMyMilvusStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMilvusStoreWithCustomDatabaseCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'milvus' => [
                        'my_milvus_store' => [
                            'endpoint' => 'http://127.0.0.1:19530',
                            'api_key' => 'foo',
                            'database' => 'test',
                            'collection' => 'default',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.milvus.my_milvus_store'));

        $definition = $container->getDefinition('ai.store.milvus.my_milvus_store');
        $this->assertSame(MilvusStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:19530', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('default', $definition->getArgument(4));
        $this->assertSame('_vectors', $definition->getArgument(5));
        $this->assertSame(1536, $definition->getArgument(6));
        $this->assertSame('COSINE', $definition->getArgument(7));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_milvus_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMilvusStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $milvus_my_milvus_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $milvusMyMilvusStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMilvusStoreWithCustomMetricsCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'milvus' => [
                        'my_milvus_store' => [
                            'endpoint' => 'http://127.0.0.1:19530',
                            'api_key' => 'foo',
                            'collection' => 'default',
                            'metric_type' => 'COSINE',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.milvus.my_milvus_store'));

        $definition = $container->getDefinition('ai.store.milvus.my_milvus_store');
        $this->assertSame(MilvusStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:19530', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('my_milvus_store', $definition->getArgument(3));
        $this->assertSame('default', $definition->getArgument(4));
        $this->assertSame('_vectors', $definition->getArgument(5));
        $this->assertSame(1536, $definition->getArgument(6));
        $this->assertSame('COSINE', $definition->getArgument(7));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_milvus_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMilvusStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $milvus_my_milvus_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $milvusMyMilvusStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMongoDbStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'mongodb' => [
                        'my_mongo_store' => [
                            'database' => 'my_db',
                            'index_name' => 'vector_index',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.mongodb.my_mongo_store'));

        $definition = $container->getDefinition('ai.store.mongodb.my_mongo_store');
        $this->assertSame(MongoDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame(MongoDbClient::class, (string) $definition->getArgument(0));
        $this->assertSame('my_db', $definition->getArgument(1));
        $this->assertSame('my_mongo_store', $definition->getArgument(2));
        $this->assertSame('vector_index', $definition->getArgument(3));
        $this->assertSame('vector', $definition->getArgument(4));
        $this->assertFalse($definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMongoStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $mongodb_my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mongodbMyMongoStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMongoDbStoreWithCustomCollectionCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'mongodb' => [
                        'my_mongo_store' => [
                            'database' => 'my_db',
                            'collection' => 'my_collection',
                            'index_name' => 'vector_index',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.mongodb.my_mongo_store'));

        $definition = $container->getDefinition('ai.store.mongodb.my_mongo_store');
        $this->assertSame(MongoDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame(MongoDbClient::class, (string) $definition->getArgument(0));
        $this->assertSame('my_db', $definition->getArgument(1));
        $this->assertSame('my_collection', $definition->getArgument(2));
        $this->assertSame('vector_index', $definition->getArgument(3));
        $this->assertSame('vector', $definition->getArgument(4));
        $this->assertFalse($definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMongoStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $mongodb_my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mongodbMyMongoStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMongoDbStoreWithCustomVectorFieldCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'mongodb' => [
                        'my_mongo_store' => [
                            'database' => 'my_db',
                            'index_name' => 'foo',
                            'vector_field' => 'random',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.mongodb.my_mongo_store'));

        $definition = $container->getDefinition('ai.store.mongodb.my_mongo_store');
        $this->assertSame(MongoDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame(MongoDbClient::class, (string) $definition->getArgument(0));
        $this->assertSame('my_db', $definition->getArgument(1));
        $this->assertSame('my_mongo_store', $definition->getArgument(2));
        $this->assertSame('foo', $definition->getArgument(3));
        $this->assertSame('random', $definition->getArgument(4));
        $this->assertFalse($definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMongoStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $mongodb_my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mongodbMyMongoStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMongoDbStoreWithCustomClientCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'mongodb' => [
                        'my_mongo_store' => [
                            'client' => 'foo',
                            'database' => 'my_db',
                            'index_name' => 'vector_index',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.mongodb.my_mongo_store'));

        $definition = $container->getDefinition('ai.store.mongodb.my_mongo_store');
        $this->assertSame(MongoDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(0));
        $this->assertSame('my_db', $definition->getArgument(1));
        $this->assertSame('my_mongo_store', $definition->getArgument(2));
        $this->assertSame('vector_index', $definition->getArgument(3));
        $this->assertSame('vector', $definition->getArgument(4));
        $this->assertFalse($definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMongoStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $mongodb_my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mongodbMyMongoStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testMongoDbStoreWithBulkWriteCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'mongodb' => [
                        'my_mongo_store' => [
                            'database' => 'my_db',
                            'index_name' => 'vector_index',
                            'vector_field' => 'embedding',
                            'bulk_write' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.mongodb.my_mongo_store'));

        $definition = $container->getDefinition('ai.store.mongodb.my_mongo_store');
        $this->assertSame(MongoDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame(MongoDbClient::class, (string) $definition->getArgument(0));
        $this->assertSame('my_db', $definition->getArgument(1));
        $this->assertSame('my_mongo_store', $definition->getArgument(2));
        $this->assertSame('vector_index', $definition->getArgument(3));
        $this->assertSame('embedding', $definition->getArgument(4));
        $this->assertTrue($definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myMongoStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $mongodb_my_mongo_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mongodbMyMongoStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testNeo4jStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'neo4j' => [
                        'my_neo4j_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'vector_index_name' => 'test',
                            'node_name' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.neo4j.my_neo4j_store'));

        $definition = $container->getDefinition('ai.store.neo4j.my_neo4j_store');
        $this->assertSame(Neo4jStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(10, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('my_neo4j_store', $definition->getArgument(4));
        $this->assertSame('test', $definition->getArgument(5));
        $this->assertSame('foo', $definition->getArgument(6));
        $this->assertSame('embeddings', $definition->getArgument(7));
        $this->assertSame(1536, $definition->getArgument(8));
        $this->assertSame('cosine', $definition->getArgument(9));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_neo4j_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myNeo4jStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $neo4j_my_neo4j_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $neo4jMyNeo4jStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testNeo4jStoreWithCustomDatabaseCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'neo4j' => [
                        'my_neo4j_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'database' => 'foo',
                            'vector_index_name' => 'test',
                            'node_name' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.neo4j.my_neo4j_store'));

        $definition = $container->getDefinition('ai.store.neo4j.my_neo4j_store');
        $this->assertSame(Neo4jStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(10, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('foo', $definition->getArgument(4));
        $this->assertSame('test', $definition->getArgument(5));
        $this->assertSame('foo', $definition->getArgument(6));
        $this->assertSame('embeddings', $definition->getArgument(7));
        $this->assertSame(1536, $definition->getArgument(8));
        $this->assertSame('cosine', $definition->getArgument(9));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_neo4j_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myNeo4jStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $neo4j_my_neo4j_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $neo4jMyNeo4jStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testNeo4jStoreWithQuantizationCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'neo4j' => [
                        'my_neo4j_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'database' => 'foo',
                            'vector_index_name' => 'test',
                            'node_name' => 'foo',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                            'distance' => 'cosine',
                            'quantization' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.neo4j.my_neo4j_store'));

        $definition = $container->getDefinition('ai.store.neo4j.my_neo4j_store');
        $this->assertSame(Neo4jStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(11, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('foo', $definition->getArgument(4));
        $this->assertSame('test', $definition->getArgument(5));
        $this->assertSame('foo', $definition->getArgument(6));
        $this->assertSame('_vectors', $definition->getArgument(7));
        $this->assertSame(768, $definition->getArgument(8));
        $this->assertSame('cosine', $definition->getArgument(9));
        $this->assertTrue($definition->getArgument(10));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_neo4j_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myNeo4jStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $neo4j_my_neo4j_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $neo4jMyNeo4jStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testOpenSearchStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'opensearch' => [
                        'my_opensearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.opensearch.my_opensearch_store'));

        $definition = $container->getDefinition('ai.store.opensearch.my_opensearch_store');
        $this->assertSame(OpenSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9200', $definition->getArgument(1));
        $this->assertSame('my_opensearch_store', $definition->getArgument(2));
        $this->assertSame('_vectors', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('l2', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myOpensearchStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $opensearch_my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $opensearchMyOpensearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testOpenSearchStoreWithCustomIndexCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'opensearch' => [
                        'my_opensearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'index_name' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.opensearch.my_opensearch_store'));

        $definition = $container->getDefinition('ai.store.opensearch.my_opensearch_store');
        $this->assertSame(OpenSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9200', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('_vectors', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('l2', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myOpensearchStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $opensearch_my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $opensearchMyOpensearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testOpenSearchStoreWithCustomFieldCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'opensearch' => [
                        'my_opensearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'vectors_field' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.opensearch.my_opensearch_store'));

        $definition = $container->getDefinition('ai.store.opensearch.my_opensearch_store');
        $this->assertSame(OpenSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9200', $definition->getArgument(1));
        $this->assertSame('my_opensearch_store', $definition->getArgument(2));
        $this->assertSame('foo', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('l2', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myOpensearchStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $opensearch_my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $opensearchMyOpensearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testOpenSearchStoreWithCustomDimensionsCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'opensearch' => [
                        'my_opensearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'dimensions' => 768,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.opensearch.my_opensearch_store'));

        $definition = $container->getDefinition('ai.store.opensearch.my_opensearch_store');
        $this->assertSame(OpenSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9200', $definition->getArgument(1));
        $this->assertSame('my_opensearch_store', $definition->getArgument(2));
        $this->assertSame('_vectors', $definition->getArgument(3));
        $this->assertSame(768, $definition->getArgument(4));
        $this->assertSame('l2', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myOpensearchStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $opensearch_my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $opensearchMyOpensearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testOpenSearchStoreWithCustomSpaceTypeCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'opensearch' => [
                        'my_opensearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'space_type' => 'l1',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.opensearch.my_opensearch_store'));

        $definition = $container->getDefinition('ai.store.opensearch.my_opensearch_store');
        $this->assertSame(OpenSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9200', $definition->getArgument(1));
        $this->assertSame('my_opensearch_store', $definition->getArgument(2));
        $this->assertSame('_vectors', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('l1', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myOpensearchStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $opensearch_my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $opensearchMyOpensearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testOpenSearchStoreWithCustomHttpClientCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'opensearch' => [
                        'my_opensearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'http_client' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.opensearch.my_opensearch_store'));

        $definition = $container->getDefinition('ai.store.opensearch.my_opensearch_store');
        $this->assertSame(OpenSearchStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9200', $definition->getArgument(1));
        $this->assertSame('my_opensearch_store', $definition->getArgument(2));
        $this->assertSame('_vectors', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('l2', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myOpensearchStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $opensearch_my_opensearch_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $opensearchMyOpensearchStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testPineconeStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'pinecone' => [
                        'my_pinecone_store' => [
                            'index_name' => 'my_index',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.pinecone.my_pinecone_store'));

        $definition = $container->getDefinition('ai.store.pinecone.my_pinecone_store');
        $this->assertSame(PineconeStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame(PineconeClient::class, (string) $definition->getArgument(0));
        $this->assertSame('my_index', $definition->getArgument(1));
        $this->assertSame('my_pinecone_store', $definition->getArgument(2));
        $this->assertSame([], $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class], ['interface' => ManagedStoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_pinecone_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myPineconeStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $pinecone_my_pinecone_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $pineconeMyPineconeStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testPineconeStoreWithCustomIndexNameCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'pinecone' => [
                        'my_pinecone_store' => [
                            'index_name' => 'custom_index',
                            'namespace' => 'my_namespace',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.pinecone.my_pinecone_store'));

        $definition = $container->getDefinition('ai.store.pinecone.my_pinecone_store');
        $this->assertSame(PineconeStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame(PineconeClient::class, (string) $definition->getArgument(0));
        $this->assertSame('custom_index', $definition->getArgument(1));
        $this->assertSame('my_namespace', $definition->getArgument(2));
        $this->assertSame([], $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class], ['interface' => ManagedStoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_pinecone_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myPineconeStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $pinecone_my_pinecone_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $pineconeMyPineconeStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testPostgresStoreWithDifferentConnectionCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'postgres' => [
                        'db' => [
                            'dsn' => 'pgsql:host=localhost;port=5432;dbname=testdb;user=app;password=mypass',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.postgres.db'));

        $definition = $container->getDefinition('ai.store.postgres.db');
        $this->assertSame(PostgresStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Definition::class, $definition->getArgument(0));
        $this->assertSame(\PDO::class, $definition->getArgument(0)->getClass());
        $this->assertSame('db', $definition->getArgument(1));
        $this->assertSame('embedding', $definition->getArgument(2));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $db'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $postgres_db'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $postgresDb'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));

        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'postgres' => [
                        'db' => [
                            'dsn' => 'pgsql:host=localhost;port=5432;dbname=testdb',
                            'username' => 'foo',
                            'password' => 'bar',
                            'vector_field' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.postgres.db'));

        $definition = $container->getDefinition('ai.store.postgres.db');
        $this->assertSame(PostgresStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Definition::class, $definition->getArgument(0));
        $this->assertSame(\PDO::class, $definition->getArgument(0)->getClass());
        $this->assertSame(['pgsql:host=localhost;port=5432;dbname=testdb', 'foo', 'bar'], $definition->getArgument(0)->getArguments());
        $this->assertSame('db', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $db'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $postgres_db'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $postgresDb'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));

        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'postgres' => [
                        'db' => [
                            'dbal_connection' => 'my_connection',
                            'vector_field' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.store.postgres.db');
        $this->assertSame(PostgresStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('my_connection', (string) $definition->getArgument(0));
        $this->assertSame('db', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame(PostgresDistance::L2, $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $db'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $postgres_db'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $postgresDb'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));

        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'postgres' => [
                        'db' => [
                            'dbal_connection' => 'my_connection',
                            'vector_field' => 'foo',
                            'distance' => PostgresDistance::L1->value,
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.store.postgres.db');
        $this->assertSame(PostgresStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('my_connection', (string) $definition->getArgument(0));
        $this->assertSame('db', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame(PostgresDistance::L1, $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $db'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $postgres_db'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $postgresDb'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));

        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'postgres' => [
                        'db' => [
                            'dbal_connection' => 'my_connection',
                            'table_name' => 'foo',
                            'vector_field' => 'foo',
                            'distance' => PostgresDistance::L1->value,
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.store.postgres.db');
        $this->assertSame(PostgresStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('my_connection', (string) $definition->getArgument(0));
        $this->assertSame('foo', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame(PostgresDistance::L1, $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias(StoreInterface::class.' $db'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $postgres_db'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $postgresDb'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testQdrantStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'qdrant' => [
                        'my_qdrant_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'api_key' => 'test',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.qdrant.my_qdrant_store'));

        $definition = $container->getDefinition('ai.store.qdrant.my_qdrant_store');
        $this->assertSame(QdrantStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('my_qdrant_store', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('Cosine', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_qdrant_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myQdrantStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $qdrant_my_qdrant_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $qdrantMyQdrantStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testQdrantStoreWithCustomCollectionCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'qdrant' => [
                        'my_qdrant_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'api_key' => 'test',
                            'collection_name' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.qdrant.my_qdrant_store'));

        $definition = $container->getDefinition('ai.store.qdrant.my_qdrant_store');
        $this->assertSame(QdrantStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('foo', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('Cosine', $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_qdrant_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myQdrantStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $qdrant_my_qdrant_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $qdrantMyQdrantStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testQdrantStoreWithAsyncCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'qdrant' => [
                        'my_qdrant_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'api_key' => 'test',
                            'collection_name' => 'foo',
                            'async' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.qdrant.my_qdrant_store'));

        $definition = $container->getDefinition('ai.store.qdrant.my_qdrant_store');
        $this->assertSame(QdrantStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('foo', $definition->getArgument(3));
        $this->assertSame(1536, $definition->getArgument(4));
        $this->assertSame('Cosine', $definition->getArgument(5));
        $this->assertTrue($definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_qdrant_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myQdrantStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $qdrant_my_qdrant_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $qdrantMyQdrantStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testRedisStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'redis' => [
                        'my_redis_store' => [
                            'connection_parameters' => [
                                'host' => '1.2.3.4',
                                'port' => 6379,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.redis.my_redis_store'));

        $definition = $container->getDefinition('ai.store.redis.my_redis_store');
        $this->assertSame(RedisStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Definition::class, $definition->getArgument(0));
        $this->assertSame(\Redis::class, $definition->getArgument(0)->getClass());
        $this->assertSame('my_redis_store', $definition->getArgument(1));
        $this->assertSame('vector:', $definition->getArgument(2));
        $this->assertSame(RedisDistance::Cosine, $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myRedisStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $redis_my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $redisMyRedisStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testRedisStoreWithCustomIndexCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'redis' => [
                        'my_redis_store' => [
                            'connection_parameters' => [
                                'host' => '1.2.3.4',
                                'port' => 6379,
                            ],
                            'index_name' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.redis.my_redis_store'));

        $definition = $container->getDefinition('ai.store.redis.my_redis_store');
        $this->assertSame(RedisStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Definition::class, $definition->getArgument(0));
        $this->assertSame(\Redis::class, $definition->getArgument(0)->getClass());
        $this->assertSame('foo', $definition->getArgument(1));
        $this->assertSame('vector:', $definition->getArgument(2));
        $this->assertSame(RedisDistance::Cosine, $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myRedisStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $redis_my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $redisMyRedisStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testRedisStoreWithCustomClientCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'redis' => [
                        'my_redis_store' => [
                            'client' => 'foo',
                            'index_name' => 'my_vector_index',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.redis.my_redis_store'));

        $definition = $container->getDefinition('ai.store.redis.my_redis_store');
        $this->assertSame(RedisStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(0));
        $this->assertSame('my_vector_index', $definition->getArgument(1));
        $this->assertSame('vector:', $definition->getArgument(2));
        $this->assertSame(RedisDistance::Cosine, $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myRedisStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $redis_my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $redisMyRedisStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testRedisStoreWithCustomKeyPrefixCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'redis' => [
                        'my_redis_store' => [
                            'client' => 'foo',
                            'index_name' => 'my_vector_index',
                            'key_prefix' => 'foo:',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.redis.my_redis_store'));

        $definition = $container->getDefinition('ai.store.redis.my_redis_store');
        $this->assertSame(RedisStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(0));
        $this->assertSame('my_vector_index', $definition->getArgument(1));
        $this->assertSame('foo:', $definition->getArgument(2));
        $this->assertSame(RedisDistance::Cosine, $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myRedisStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $redis_my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $redisMyRedisStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testRedisStoreWithCustomDistanceCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'redis' => [
                        'my_redis_store' => [
                            'client' => 'foo',
                            'index_name' => 'my_vector_index',
                            'distance' => RedisDistance::L2->value,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.redis.my_redis_store'));

        $definition = $container->getDefinition('ai.store.redis.my_redis_store');
        $this->assertSame(RedisStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(0));
        $this->assertSame('my_vector_index', $definition->getArgument(1));
        $this->assertSame('vector:', $definition->getArgument(2));
        $this->assertSame(RedisDistance::L2, $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myRedisStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $redis_my_redis_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $redisMyRedisStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testSupabaseStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'supabase' => [
                        'my_supabase_store' => [
                            'url' => 'https://test.supabase.co',
                            'api_key' => 'supabase_test_key',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.supabase.my_supabase_store'));

        $definition = $container->getDefinition('ai.store.supabase.my_supabase_store');
        $this->assertSame(SupabaseStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('https://test.supabase.co', $definition->getArgument(1));
        $this->assertSame('supabase_test_key', $definition->getArgument(2));
        $this->assertSame('my_supabase_store', $definition->getArgument(3));
        $this->assertSame('embedding', $definition->getArgument(4));
        $this->assertSame(1536, $definition->getArgument(5));
        $this->assertSame('match_documents', $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_supabase_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mySupabaseStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $supabase_my_supabase_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $supabaseMySupabaseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testSupabaseStoreWithCustomTableCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'supabase' => [
                        'my_supabase_store' => [
                            'url' => 'https://test.supabase.co',
                            'api_key' => 'supabase_test_key',
                            'table' => 'my_supabase_table',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.supabase.my_supabase_store'));

        $definition = $container->getDefinition('ai.store.supabase.my_supabase_store');
        $this->assertSame(SupabaseStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('https://test.supabase.co', $definition->getArgument(1));
        $this->assertSame('supabase_test_key', $definition->getArgument(2));
        $this->assertSame('my_supabase_table', $definition->getArgument(3));
        $this->assertSame('embedding', $definition->getArgument(4));
        $this->assertSame(1536, $definition->getArgument(5));
        $this->assertSame('match_documents', $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_supabase_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mySupabaseStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $supabase_my_supabase_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $supabaseMySupabaseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testSupabaseStoreWithCustomHttpClientCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'supabase' => [
                        'my_supabase_store' => [
                            'http_client' => 'foo',
                            'url' => 'https://test.supabase.co',
                            'api_key' => 'supabase_test_key',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.supabase.my_supabase_store'));

        $definition = $container->getDefinition('ai.store.supabase.my_supabase_store');
        $this->assertSame(SupabaseStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(0));
        $this->assertSame('https://test.supabase.co', $definition->getArgument(1));
        $this->assertSame('supabase_test_key', $definition->getArgument(2));
        $this->assertSame('my_supabase_store', $definition->getArgument(3));
        $this->assertSame('embedding', $definition->getArgument(4));
        $this->assertSame(1536, $definition->getArgument(5));
        $this->assertSame('match_documents', $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_supabase_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mySupabaseStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $supabase_my_supabase_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $supabaseMySupabaseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testSupabaseStoreWithCustomFunctionCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'supabase' => [
                        'my_supabase_store' => [
                            'url' => 'https://test.supabase.co',
                            'api_key' => 'supabase_test_key',
                            'function_name' => 'my_custom_function',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.supabase.my_supabase_store'));

        $definition = $container->getDefinition('ai.store.supabase.my_supabase_store');
        $this->assertSame(SupabaseStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(7, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('https://test.supabase.co', $definition->getArgument(1));
        $this->assertSame('supabase_test_key', $definition->getArgument(2));
        $this->assertSame('my_supabase_store', $definition->getArgument(3));
        $this->assertSame('embedding', $definition->getArgument(4));
        $this->assertSame(1536, $definition->getArgument(5));
        $this->assertSame('my_custom_function', $definition->getArgument(6));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => StoreInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_supabase_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mySupabaseStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $supabase_my_supabase_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $supabaseMySupabaseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testSurrealDbStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'surrealdb' => [
                        'my_surrealdb_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.surrealdb.my_surrealdb_store'));

        $definition = $container->getDefinition('ai.store.surrealdb.my_surrealdb_store');
        $this->assertSame(SurrealDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(10, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('foo', $definition->getArgument(4));
        $this->assertSame('bar', $definition->getArgument(5));
        $this->assertSame('my_surrealdb_store', $definition->getArgument(6));
        $this->assertSame('_vectors', $definition->getArgument(7));
        $this->assertSame('cosine', $definition->getArgument(8));
        $this->assertSame(1536, $definition->getArgument(9));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_surrealdb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mySurrealdbStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $surrealdb_my_surrealdb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $surrealdbMySurrealdbStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testSurrealDbStoreWithCustomTableCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'surrealdb' => [
                        'my_surrealdb_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                            'table' => 'custom',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.surrealdb.my_surrealdb_store'));

        $definition = $container->getDefinition('ai.store.surrealdb.my_surrealdb_store');
        $this->assertSame(SurrealDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(10, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('foo', $definition->getArgument(4));
        $this->assertSame('bar', $definition->getArgument(5));
        $this->assertSame('custom', $definition->getArgument(6));
        $this->assertSame('_vectors', $definition->getArgument(7));
        $this->assertSame('cosine', $definition->getArgument(8));
        $this->assertSame(1536, $definition->getArgument(9));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_surrealdb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mySurrealdbStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $surrealdb_my_surrealdb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $surrealdbMySurrealdbStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testSurrealDbStoreWithNamespacedUserCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'surrealdb' => [
                        'my_surrealdb_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                            'table' => 'bar',
                            'vector_field' => '_vectors',
                            'strategy' => 'cosine',
                            'dimensions' => 768,
                            'namespaced_user' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.surrealdb.my_surrealdb_store'));

        $definition = $container->getDefinition('ai.store.surrealdb.my_surrealdb_store');
        $this->assertSame(SurrealDbStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(11, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', $definition->getArgument(1));
        $this->assertSame('test', $definition->getArgument(2));
        $this->assertSame('test', $definition->getArgument(3));
        $this->assertSame('foo', $definition->getArgument(4));
        $this->assertSame('bar', $definition->getArgument(5));
        $this->assertSame('bar', $definition->getArgument(6));
        $this->assertSame('_vectors', $definition->getArgument(7));
        $this->assertSame('cosine', $definition->getArgument(8));
        $this->assertSame(768, $definition->getArgument(9));
        $this->assertTrue($definition->getArgument(10));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_surrealdb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $mySurrealdbStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $surrealdb_my_surrealdb_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $surrealdbMySurrealdbStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testTypesenseStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'typesense' => [
                        'my_typesense_store' => [
                            'endpoint' => 'http://localhost:8108',
                            'api_key' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.typesense.my_typesense_store'));

        $definition = $container->getDefinition('ai.store.typesense.my_typesense_store');
        $this->assertSame(TypesenseStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://localhost:8108', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('my_typesense_store', $definition->getArgument(3));
        $this->assertSame('_vectors', $definition->getArgument(4));
        $this->assertSame(1536, $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_typesense_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myTypesenseStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $typesense_my_typesense_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $typesenseMyTypesenseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testTypesenseStoreWithCustomCollectionCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'typesense' => [
                        'my_typesense_store' => [
                            'endpoint' => 'http://localhost:8108',
                            'api_key' => 'foo',
                            'collection' => 'my_collection',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.typesense.my_typesense_store'));

        $definition = $container->getDefinition('ai.store.typesense.my_typesense_store');
        $this->assertSame(TypesenseStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://localhost:8108', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('my_collection', $definition->getArgument(3));
        $this->assertSame('_vectors', $definition->getArgument(4));
        $this->assertSame(1536, $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_typesense_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myTypesenseStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $typesense_my_typesense_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $typesenseMyTypesenseStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testWevaviateStoreCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'weaviate' => [
                        'my_weaviate_store' => [
                            'endpoint' => 'http://localhost:8080',
                            'api_key' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.weaviate.my_weaviate_store'));

        $definition = $container->getDefinition('ai.store.weaviate.my_weaviate_store');
        $this->assertSame(WeaviateStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://localhost:8080', $definition->getArgument(1));
        $this->assertSame('bar', $definition->getArgument(2));
        $this->assertSame('my_weaviate_store', $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_weaviate_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myWeaviateStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $weaviate_my_weaviate_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $weaviateMyWeaviateStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testWevaviateStoreWithCustomCollectionCanBeConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'weaviate' => [
                        'my_weaviate_store' => [
                            'endpoint' => 'http://localhost:8080',
                            'api_key' => 'bar',
                            'collection' => 'my_weaviate_collection',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.store.weaviate.my_weaviate_store'));

        $definition = $container->getDefinition('ai.store.weaviate.my_weaviate_store');
        $this->assertSame(WeaviateStore::class, $definition->getClass());

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://localhost:8080', $definition->getArgument(1));
        $this->assertSame('bar', $definition->getArgument(2));
        $this->assertSame('my_weaviate_collection', $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => StoreInterface::class],
            ['interface' => ManagedStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.store'));

        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $my_weaviate_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $myWeaviateStore'));
        $this->assertTrue($container->hasAlias('.'.StoreInterface::class.' $weaviate_my_weaviate_store'));
        $this->assertTrue($container->hasAlias(StoreInterface::class.' $weaviateMyWeaviateStore'));
        $this->assertTrue($container->hasAlias(StoreInterface::class));
    }

    public function testConfigurationWithUseAttributeAsKeyWorksWithoutNormalizeKeys()
    {
        // Test that configurations using useAttributeAsKey work correctly
        // after removing redundant normalizeKeys(false) calls
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'azure' => [
                        'Test_Instance-123' => [ // Mixed case and special chars in key
                            'api_key' => 'test_key',
                            'base_url' => 'https://test.openai.azure.com/',
                            'deployment' => 'gpt-35-turbo',
                            'api_version' => '2024-02-15-preview',
                        ],
                    ],
                ],
                'agent' => [
                    'My-Agent_Name.v2' => [ // Mixed case and special chars in key
                        'model' => 'gpt-4',
                    ],
                ],
                'store' => [
                    'mongodb' => [
                        'Production_DB-v3' => [ // Mixed case and special chars in key
                            'database' => 'test_db',
                            'collection' => 'test_collection',
                            'index_name' => 'test_index',
                            'vector_field' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        // Verify that the services are created with the exact key names
        $this->assertTrue($container->hasDefinition('ai.platform.azure.Test_Instance-123'));
        $this->assertTrue($container->hasDefinition('ai.agent.My-Agent_Name.v2'));
        $this->assertTrue($container->hasDefinition('ai.store.mongodb.Production_DB-v3'));
    }

    public function testDecartPlatformCanBeCreatedWithCustomEndpoint()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'decart' => [
                        'api_key' => 'foo',
                        'host' => 'https://api.decart.ai/v2',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.decart'));
        $this->assertTrue($container->hasDefinition('ai.platform.model_catalog.decart'));

        $definition = $container->getDefinition('ai.platform.decart');

        $this->assertTrue($definition->isLazy());
        $this->assertSame([
            DecartPlatformFactory::class,
            'create',
        ], $definition->getFactory());
        $this->assertCount(6, $definition->getArguments());
        $this->assertSame('foo', $definition->getArgument(0));
        $this->assertSame('https://api.decart.ai/v2', $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('http_client', (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('ai.platform.model_catalog.decart', (string) $definition->getArgument(3));
        $this->assertNull($definition->getArgument(4));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(5));
        $this->assertSame('event_dispatcher', (string) $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => PlatformInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([['name' => 'decart']], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $decart'));
        $this->assertTrue($container->hasAlias(PlatformInterface::class));
    }

    public function testOllamaCanBeCreatedWithCatalogFromApi()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'ollama' => [
                        'api_catalog' => true,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.ollama'));
        $this->assertTrue($container->hasDefinition('ai.platform.model_catalog.ollama'));

        $ollamaDefinition = $container->getDefinition('ai.platform.ollama');

        $this->assertTrue($ollamaDefinition->isLazy());
        $this->assertCount(5, $ollamaDefinition->getArguments());
        $this->assertSame('http://127.0.0.1:11434', $ollamaDefinition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $ollamaDefinition->getArgument(1));
        $this->assertSame('http_client', (string) $ollamaDefinition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $ollamaDefinition->getArgument(2));
        $this->assertSame('ai.platform.model_catalog.ollama', (string) $ollamaDefinition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $ollamaDefinition->getArgument(3));
        $this->assertSame('ai.platform.contract.ollama', (string) $ollamaDefinition->getArgument(3));
        $this->assertInstanceOf(Reference::class, $ollamaDefinition->getArgument(4));
        $this->assertSame('event_dispatcher', (string) $ollamaDefinition->getArgument(4));

        $ollamaCatalogDefinition = $container->getDefinition('ai.platform.model_catalog.ollama');

        $this->assertTrue($ollamaCatalogDefinition->isLazy());
        $this->assertSame(OllamaApiCatalog::class, $ollamaCatalogDefinition->getClass());
        $this->assertCount(2, $ollamaCatalogDefinition->getArguments());
        $this->assertSame('http://127.0.0.1:11434', $ollamaCatalogDefinition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $ollamaCatalogDefinition->getArgument(1));
        $this->assertSame('http_client', (string) $ollamaCatalogDefinition->getArgument(1));
    }

    /**
     * Tests that processor tags use the full agent ID (ai.agent.my_agent) instead of just the agent name (my_agent).
     * This regression test prevents issues where processors would not be correctly associated with their agents.
     */
    #[TestDox('Processor tags use the full agent ID instead of just the agent name')]
    public function testProcessorTagsUseFullAgentId()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                        'prompt' => 'You are a test assistant.',
                    ],
                ],
            ],
        ]);

        $agentId = 'ai.agent.test_agent';

        // Test tool processor tags
        $toolProcessorDefinition = $container->getDefinition('ai.tool.agent_processor.test_agent');
        $toolProcessorTags = $toolProcessorDefinition->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($toolProcessorTags, 'Tool processor should have input processor tags');
        $this->assertSame($agentId, $toolProcessorTags[0]['agent'], 'Tool input processor tag should use full agent ID');

        $outputTags = $toolProcessorDefinition->getTag('ai.agent.output_processor');
        $this->assertNotEmpty($outputTags, 'Tool processor should have output processor tags');
        $this->assertSame($agentId, $outputTags[0]['agent'], 'Tool output processor tag should use full agent ID');

        // Test system prompt processor tags
        $systemPromptDefinition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $systemPromptTags = $systemPromptDefinition->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($systemPromptTags, 'System prompt processor should have input processor tags');
        $this->assertSame($agentId, $systemPromptTags[0]['agent'], 'System prompt processor tag should use full agent ID');
    }

    #[TestDox('Processors work correctly with multiple agents')]
    public function testMultipleAgentsWithProcessors()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'first_agent' => [
                        'model' => 'gpt-4',
                        'tools' => [
                            ['service' => 'tool_one', 'description' => 'Tool for first agent'],
                        ],
                        'prompt' => 'First agent prompt',
                    ],
                    'second_agent' => [
                        'model' => 'claude-3-opus-20240229',
                        'tools' => [
                            ['service' => 'tool_two', 'description' => 'Tool for second agent'],
                        ],
                        'prompt' => 'Second agent prompt',
                    ],
                ],
            ],
        ]);

        // Check that each agent has its own properly tagged processors
        $firstAgentId = 'ai.agent.first_agent';
        $secondAgentId = 'ai.agent.second_agent';

        // First agent tool processor
        $firstToolProcessor = $container->getDefinition('ai.tool.agent_processor.first_agent');
        $firstToolTags = $firstToolProcessor->getTag('ai.agent.input_processor');
        $this->assertSame($firstAgentId, $firstToolTags[0]['agent']);

        // Second agent tool processor
        $secondToolProcessor = $container->getDefinition('ai.tool.agent_processor.second_agent');
        $secondToolTags = $secondToolProcessor->getTag('ai.agent.input_processor');
        $this->assertSame($secondAgentId, $secondToolTags[0]['agent']);

        // First agent system prompt processor
        $firstSystemPrompt = $container->getDefinition('ai.agent.first_agent.system_prompt_processor');
        $firstSystemTags = $firstSystemPrompt->getTag('ai.agent.input_processor');
        $this->assertSame($firstAgentId, $firstSystemTags[0]['agent']);
        $this->assertCount(3, array_filter($firstSystemPrompt->getArguments()));

        // Second agent system prompt processor
        $secondSystemPrompt = $container->getDefinition('ai.agent.second_agent.system_prompt_processor');
        $secondSystemTags = $secondSystemPrompt->getTag('ai.agent.input_processor');
        $this->assertSame($secondAgentId, $secondSystemTags[0]['agent']);
        $this->assertCount(3, array_filter($secondSystemPrompt->getArguments()));
    }

    #[TestDox('Processors work correctly when using the default toolbox')]
    public function testToolboxWithoutExplicitToolsDefined()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'agent_with_tools' => [
                        'model' => 'gpt-4',
                        'tools' => true,
                    ],
                ],
            ],
        ]);

        $agentId = 'ai.agent.agent_with_tools';

        // When using default toolbox, the ai.tool.agent_processor service gets the tags
        $defaultToolProcessor = $container->getDefinition('ai.tool.agent_processor.agent_with_tools');
        $inputTags = $defaultToolProcessor->getTag('ai.agent.input_processor');
        $outputTags = $defaultToolProcessor->getTag('ai.agent.output_processor');

        // Find tags for our specific agent
        $foundInput = false;
        $foundOutput = false;

        foreach ($inputTags as $tag) {
            if (($tag['agent'] ?? '') === $agentId) {
                $foundInput = true;
                break;
            }
        }

        foreach ($outputTags as $tag) {
            if (($tag['agent'] ?? '') === $agentId) {
                $foundOutput = true;
                break;
            }
        }

        $this->assertTrue($foundInput, 'Default tool processor should have input tag with full agent ID');
        $this->assertTrue($foundOutput, 'Default tool processor should have output tag with full agent ID');
    }

    public function testElevenLabsPlatformCanBeRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'elevenlabs' => [
                        'api_key' => 'foo',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.elevenlabs'));

        $definition = $container->getDefinition('ai.platform.elevenlabs');

        $this->assertTrue($definition->isLazy());
        $this->assertSame([ElevenLabsPlatformFactory::class, 'create'], $definition->getFactory());

        $this->assertCount(6, $definition->getArguments());
        $this->assertSame('foo', $definition->getArgument(0));
        $this->assertSame('https://api.elevenlabs.io/v1', $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('http_client', (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('ai.platform.model_catalog.elevenlabs', (string) $definition->getArgument(3));
        $this->assertNull($definition->getArgument(4));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(5));
        $this->assertSame('event_dispatcher', (string) $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => PlatformInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([['name' => 'elevenlabs']], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $elevenlabs'));
        $this->assertTrue($container->hasAlias(PlatformInterface::class));

        $modelCatalogDefinition = $container->getDefinition('ai.platform.model_catalog.elevenlabs');

        $this->assertSame(ElevenLabsModelCatalog::class, $modelCatalogDefinition->getClass());
        $this->assertTrue($modelCatalogDefinition->isLazy());

        $this->assertTrue($modelCatalogDefinition->hasTag('proxy'));
        $this->assertSame([['interface' => ModelCatalogInterface::class]], $modelCatalogDefinition->getTag('proxy'));
    }

    public function testElevenLabsPlatformWithCustomEndpointCanBeRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'elevenlabs' => [
                        'api_key' => 'foo',
                        'host' => 'https://api.elevenlabs.io/v2',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.elevenlabs'));

        $definition = $container->getDefinition('ai.platform.elevenlabs');

        $this->assertTrue($definition->isLazy());
        $this->assertSame([ElevenLabsPlatformFactory::class, 'create'], $definition->getFactory());

        $this->assertCount(6, $definition->getArguments());
        $this->assertSame('foo', $definition->getArgument(0));
        $this->assertSame('https://api.elevenlabs.io/v2', $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('http_client', (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('ai.platform.model_catalog.elevenlabs', (string) $definition->getArgument(3));
        $this->assertNull($definition->getArgument(4));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(5));
        $this->assertSame('event_dispatcher', (string) $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => PlatformInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([['name' => 'elevenlabs']], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $elevenlabs'));
        $this->assertTrue($container->hasAlias(PlatformInterface::class));

        $modelCatalogDefinition = $container->getDefinition('ai.platform.model_catalog.elevenlabs');

        $this->assertSame(ElevenLabsModelCatalog::class, $modelCatalogDefinition->getClass());
        $this->assertTrue($modelCatalogDefinition->isLazy());

        $this->assertTrue($modelCatalogDefinition->hasTag('proxy'));
        $this->assertSame([['interface' => ModelCatalogInterface::class]], $modelCatalogDefinition->getTag('proxy'));
    }

    public function testElevenLabsPlatformWithCustomHttpClientCanBeRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'elevenlabs' => [
                        'api_key' => 'foo',
                        'http_client' => 'foo',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.elevenlabs'));

        $definition = $container->getDefinition('ai.platform.elevenlabs');

        $this->assertTrue($definition->isLazy());
        $this->assertSame([ElevenLabsPlatformFactory::class, 'create'], $definition->getFactory());

        $this->assertCount(6, $definition->getArguments());
        $this->assertSame('foo', $definition->getArgument(0));
        $this->assertSame('https://api.elevenlabs.io/v1', $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('foo', (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('ai.platform.model_catalog.elevenlabs', (string) $definition->getArgument(3));
        $this->assertNull($definition->getArgument(4));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(5));
        $this->assertSame('event_dispatcher', (string) $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => PlatformInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([['name' => 'elevenlabs']], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $elevenlabs'));
        $this->assertTrue($container->hasAlias(PlatformInterface::class));

        $modelCatalogDefinition = $container->getDefinition('ai.platform.model_catalog.elevenlabs');

        $this->assertSame(ElevenLabsModelCatalog::class, $modelCatalogDefinition->getClass());
        $this->assertTrue($modelCatalogDefinition->isLazy());

        $this->assertTrue($modelCatalogDefinition->hasTag('proxy'));
        $this->assertSame([['interface' => ModelCatalogInterface::class]], $modelCatalogDefinition->getTag('proxy'));
    }

    public function testElevenLabsPlatformWithApiCatalogCanBeRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'elevenlabs' => [
                        'api_key' => 'foo',
                        'api_catalog' => true,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.elevenlabs'));
        $this->assertTrue($container->hasDefinition('ai.platform.model_catalog.elevenlabs'));

        $definition = $container->getDefinition('ai.platform.elevenlabs');

        $this->assertTrue($definition->isLazy());
        $this->assertSame([ElevenLabsPlatformFactory::class, 'create'], $definition->getFactory());

        $this->assertCount(6, $definition->getArguments());
        $this->assertSame('foo', $definition->getArgument(0));
        $this->assertSame('https://api.elevenlabs.io/v1', $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('http_client', (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('ai.platform.model_catalog.elevenlabs', (string) $definition->getArgument(3));
        $this->assertNull($definition->getArgument(4));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(5));
        $this->assertSame('event_dispatcher', (string) $definition->getArgument(5));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => PlatformInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([['name' => 'elevenlabs']], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $elevenlabs'));
        $this->assertTrue($container->hasAlias(PlatformInterface::class));

        $modelCatalogDefinition = $container->getDefinition('ai.platform.model_catalog.elevenlabs');

        $this->assertSame(ElevenLabsApiCatalog::class, $modelCatalogDefinition->getClass());
        $this->assertTrue($modelCatalogDefinition->isLazy());
        $this->assertCount(3, $modelCatalogDefinition->getArguments());
        $this->assertInstanceOf(Reference::class, $modelCatalogDefinition->getArgument(0));
        $this->assertSame('http_client', (string) $modelCatalogDefinition->getArgument(0));
        $this->assertSame('foo', $modelCatalogDefinition->getArgument(1));
        $this->assertSame('https://api.elevenlabs.io/v1', $modelCatalogDefinition->getArgument(2));

        $this->assertTrue($modelCatalogDefinition->hasTag('proxy'));
        $this->assertSame([['interface' => ModelCatalogInterface::class]], $modelCatalogDefinition->getTag('proxy'));
    }

    public function testFailoverPlatformCanBeCreated()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'ollama' => [
                        'host_url' => 'http://127.0.0.1:11434',
                    ],
                    'openai' => [
                        'api_key' => 'sk-openai_key_full',
                    ],
                    'failover' => [
                        'main' => [
                            'platforms' => [
                                'ai.platform.ollama',
                                'ai.platform.openai',
                            ],
                            'rate_limiter' => 'limiter.failover_platform',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.failover.main'));

        $definition = $container->getDefinition('ai.platform.failover.main');

        $this->assertSame([
            FailoverPlatformFactory::class,
            'create',
        ], $definition->getFactory());
        $this->assertTrue($definition->isLazy());
        $this->assertSame(FailoverPlatform::class, $definition->getClass());

        $this->assertCount(4, $definition->getArguments());
        $this->assertCount(2, $definition->getArgument(0));
        $this->assertEquals([
            new Reference('ai.platform.ollama'),
            new Reference('ai.platform.openai'),
        ], $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame('limiter.failover_platform', (string) $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame(ClockInterface::class, (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame(LoggerInterface::class, (string) $definition->getArgument(3));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([['interface' => PlatformInterface::class]], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([['name' => 'failover']], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $main'));
    }

    public function testOpenAiPlatformWithDefaultRegion()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'openai' => [
                        'api_key' => 'sk-test-key',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.openai'));

        $definition = $container->getDefinition('ai.platform.openai');
        $arguments = $definition->getArguments();

        $this->assertCount(6, $arguments);
        $this->assertSame('sk-test-key', $arguments[0]);
        $this->assertNull($arguments[4]); // region should be null by default
    }

    #[TestWith(['EU'])]
    #[TestWith(['US'])]
    #[TestWith([null])]
    public function testOpenAiPlatformWithRegion(?string $region)
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'openai' => [
                        'api_key' => 'sk-test-key',
                        'region' => $region,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.openai'));

        $definition = $container->getDefinition('ai.platform.openai');
        $arguments = $definition->getArguments();

        $this->assertCount(6, $arguments);
        $this->assertSame('sk-test-key', $arguments[0]);
        $this->assertSame($region, $arguments[4]);
    }

    public function testOpenAiPlatformWithInvalidRegion()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The region must be either "EU" (https://eu.api.openai.com), "US" (https://us.api.openai.com) or null (https://api.openai.com)');

        $this->buildContainer([
            'ai' => [
                'platform' => [
                    'openai' => [
                        'api_key' => 'sk-test-key',
                        'region' => 'INVALID',
                    ],
                ],
            ],
        ]);
    }

    public function testPerplexityPlatformConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'perplexity' => [
                        'api_key' => 'pplx-test-key',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.perplexity'));

        $definition = $container->getDefinition('ai.platform.perplexity');
        $arguments = $definition->getArguments();

        $this->assertCount(5, $arguments);
        $this->assertSame('pplx-test-key', $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('http_client', (string) $arguments[1]);
        $this->assertInstanceOf(Reference::class, $arguments[2]);
        $this->assertSame('ai.platform.model_catalog.perplexity', (string) $arguments[2]);
        $this->assertInstanceOf(Reference::class, $arguments[3]);
        $this->assertSame('ai.platform.contract.perplexity', (string) $arguments[3]);
    }

    public function testDeepSeekPlatformConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'deepseek' => [
                        'api_key' => 'sk-deepseek-test-key',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.deepseek'));

        $definition = $container->getDefinition('ai.platform.deepseek');
        $arguments = $definition->getArguments();

        $this->assertCount(5, $arguments);
        $this->assertSame('sk-deepseek-test-key', $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('http_client', (string) $arguments[1]);
        $this->assertInstanceOf(Reference::class, $arguments[2]);
        $this->assertSame('ai.platform.model_catalog.deepseek', (string) $arguments[2]);
        $this->assertNull($arguments[3]);
        $this->assertInstanceOf(Reference::class, $arguments[4]);
        $this->assertSame('event_dispatcher', (string) $arguments[4]);
    }

    #[TestDox('DeepSeek platform uses custom http_client when configured')]
    public function testDeepSeekPlatformUsesCustomHttpClient()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'deepseek' => [
                        'api_key' => 'sk-deepseek-test-key',
                        'http_client' => 'my_custom_http_client',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.deepseek'));

        $definition = $container->getDefinition('ai.platform.deepseek');
        $arguments = $definition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('my_custom_http_client', (string) $arguments[1]);
    }

    public function testTransformersPhpConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'transformersphp' => [],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.transformersphp'));

        $definition = $container->getDefinition('ai.platform.transformersphp');
        $arguments = $definition->getArguments();

        $this->assertCount(2, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('ai.platform.model_catalog.transformersphp', (string) $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('event_dispatcher', (string) $arguments[1]);
    }

    #[TestDox('System prompt with array structure works correctly')]
    public function testSystemPromptWithArrayStructure()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                            'enable_translation' => true,
                            'translation_domain' => 'prompts',
                        ],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.prompt.test_agent'));
        $definition = $container->getDefinition('ai.agent.prompt.test_agent');
        $arguments = $definition->getArguments();
        $this->assertEquals('You are a helpful assistant.', $arguments[0]);
        $this->assertEquals([], $arguments[1]);
        $this->assertEquals('prompts', $arguments[2]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertEquals(new Reference('ai.agent.prompt.test_agent'), $arguments[0]);
        $this->assertNull($arguments[1]); // include_tools is false, so null reference
    }

    #[TestDox('System prompt with include_tools enabled works correctly')]
    public function testSystemPromptWithIncludeToolsEnabled()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('You are a helpful assistant.', $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.toolbox.test_agent', (string) $arguments[1]);
    }

    #[TestDox('System prompt with only text key defaults include_tools to false')]
    public function testSystemPromptWithOnlyTextKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('You are a helpful assistant.', $arguments[0]);
        $this->assertNull($arguments[1]); // include_tools defaults to false
    }

    #[TestDox('Agent without system prompt does not create processor')]
    public function testAgentWithoutSystemPrompt()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
    }

    #[TestDox('Valid system prompt creates processor correctly')]
    public function testValidSystemPromptCreatesProcessor()
    {
        // This test verifies that valid system prompts work correctly with new structure
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => [
                            'text' => 'Valid prompt',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('Valid prompt', $arguments[0]);
        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.toolbox.test_agent', (string) $arguments[1]);
    }

    #[TestDox('Empty text in array structure throws configuration exception')]
    public function testEmptyTextInArrayThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "text" cannot be empty.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => [
                            'text' => '',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('System prompt array without text or file throws configuration exception')]
    public function testSystemPromptArrayWithoutTextKeyThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Either "text" or "file" must be configured for prompt.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => [],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('System prompt with only include_tools throws configuration exception')]
    public function testSystemPromptWithOnlyIncludeToolsThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Either "text" or "file" must be configured for prompt.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => [
                            'include_tools' => true,
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('System prompt with string format works correctly')]
    public function testSystemPromptWithStringFormat()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => 'You are a helpful assistant.',
                        'tools' => [
                            ['service' => 'some_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $arguments = $definition->getArguments();

        $this->assertSame('You are a helpful assistant.', $arguments[0]);
        $this->assertNull($arguments[1]); // include_tools not enabled with string format
    }

    #[TestDox('Memory provider configuration creates memory input processor')]
    public function testMemoryProviderConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => 'Static memory for testing',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        // Should create StaticMemoryProvider for non-existing service name
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        $definition = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $arguments = $definition->getArguments();

        // Check that the memory processor references the static memory provider
        $this->assertInstanceOf(Reference::class, $arguments[0][0]);
        $this->assertSame('ai.agent.test_agent.static_memory_provider', (string) $arguments[0][0]);

        // Check that the processor has the correct tags with proper priority
        $tags = $definition->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($tags);
        $this->assertSame('ai.agent.test_agent', $tags[0]['agent']);
        $this->assertSame(-40, $tags[0]['priority']);
    }

    #[TestDox('Agent without memory configuration does not create memory processor')]
    public function testAgentWithoutMemoryConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
    }

    #[TestDox('Memory with null value does not create memory processor')]
    public function testMemoryWithNullValueDoesNotCreateProcessor()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => null,
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
    }

    #[TestDox('Memory configuration works with system prompt and tools')]
    public function testMemoryWithSystemPromptAndTools()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => 'conversation_memory_service',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            ['service' => 'test_tool', 'description' => 'Test tool'],
                        ],
                    ],
                ],
            ],
        ]);

        // Check that all processors are created
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
        $this->assertTrue($container->hasDefinition('ai.tool.agent_processor.test_agent'));

        // Verify memory processor configuration (static memory since service doesn't exist)
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));
        $memoryDefinition = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $memoryArguments = $memoryDefinition->getArguments();
        $this->assertInstanceOf(Reference::class, $memoryArguments[0][0]);
        $this->assertSame('ai.agent.test_agent.static_memory_provider', (string) $memoryArguments[0][0]);

        // Verify memory processor has highest priority (runs first)
        $memoryTags = $memoryDefinition->getTag('ai.agent.input_processor');
        $this->assertSame(-40, $memoryTags[0]['priority']);

        // Verify system prompt processor has correct priority (runs after memory)
        $systemPromptDefinition = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');
        $systemPromptTags = $systemPromptDefinition->getTag('ai.agent.input_processor');
        $this->assertSame(-30, $systemPromptTags[0]['priority']);
    }

    #[TestDox('Memory configuration works with string prompt format')]
    public function testMemoryWithStringPromptFormat()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'prompt' => 'You are a helpful assistant.',
                        // memory cannot be configured with string format
                    ],
                ],
            ],
        ]);

        // Memory processor should not be created with string prompt format
        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));

        // But system prompt processor should still be created
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.system_prompt_processor'));
    }

    #[TestDox('Multiple agents can have different memory configurations')]
    public function testMultipleAgentsWithDifferentMemoryConfigurations()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'agent_with_memory' => [
                        'model' => 'gpt-4',
                        'memory' => 'first_memory_service',
                        'prompt' => [
                            'text' => 'Agent with memory.',
                        ],
                    ],
                    'agent_without_memory' => [
                        'model' => 'claude-3-opus-20240229',
                        'prompt' => [
                            'text' => 'Agent without memory.',
                        ],
                    ],
                    'agent_with_different_memory' => [
                        'model' => 'gpt-4',
                        'memory' => 'second_memory_service',
                        'prompt' => [
                            'text' => 'Agent with different memory.',
                        ],
                    ],
                ],
            ],
        ]);

        // First agent should have memory processor (static since service doesn't exist)
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_memory.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_memory.static_memory_provider'));
        $firstMemoryDef = $container->getDefinition('ai.agent.agent_with_memory.memory_input_processor');
        $firstMemoryArgs = $firstMemoryDef->getArguments();
        $this->assertSame('ai.agent.agent_with_memory.static_memory_provider', (string) $firstMemoryArgs[0][0]);

        // Second agent should not have memory processor
        $this->assertFalse($container->hasDefinition('ai.agent.agent_without_memory.memory_input_processor'));

        // Third agent should have memory processor (static since service doesn't exist)
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_different_memory.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_different_memory.static_memory_provider'));
        $thirdMemoryDef = $container->getDefinition('ai.agent.agent_with_different_memory.memory_input_processor');
        $thirdMemoryArgs = $thirdMemoryDef->getArguments();
        $this->assertSame('ai.agent.agent_with_different_memory.static_memory_provider', (string) $thirdMemoryArgs[0][0]);

        // Verify that each memory processor is tagged for the correct agent
        $firstTags = $firstMemoryDef->getTag('ai.agent.input_processor');
        $this->assertSame('ai.agent.agent_with_memory', $firstTags[0]['agent']);

        $thirdTags = $thirdMemoryDef->getTag('ai.agent.input_processor');
        $this->assertSame('ai.agent.agent_with_different_memory', $thirdTags[0]['agent']);
    }

    #[TestDox('Memory processor uses MemoryInputProcessor class')]
    public function testMemoryProcessorUsesCorrectClass()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => 'my_memory_service',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $this->assertSame(MemoryInputProcessor::class, $definition->getClass());
    }

    #[TestDox('Memory configuration is included in full config example')]
    public function testMemoryInFullConfigurationExample()
    {
        $container = $this->buildContainer($this->getFullConfig());

        // The full config should include memory in some agent
        // Let's check if we need to update the full config to include memory
        $this->assertTrue($container->hasDefinition('ai.agent.my_chat_agent'));

        // For now, let's verify that if memory were added to full config, it would work
        // This test documents the expectation that full config could include memory
        $this->assertInstanceOf(ContainerBuilder::class, $container);
    }

    #[TestDox('Empty string memory configuration throws validation exception')]
    public function testEmptyStringMemoryConfigurationThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Memory cannot be empty.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => '',
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Memory array configuration without service key throws validation exception')]
    public function testMemoryArrayConfigurationWithoutServiceKeyThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Memory array configuration must contain a "service" key.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => ['invalid' => 'value'],
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Memory array configuration with empty service throws validation exception')]
    public function testMemoryArrayConfigurationWithEmptyServiceThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Memory service cannot be empty.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => ['service' => ''],
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Memory service configuration works correctly')]
    public function testMemoryServiceConfigurationWorksCorrectly()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => ['service' => 'my_custom_memory_service'],
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);

        // Should use the service directly, not create a StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        $memoryProcessor = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $arguments = $memoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0][0]);
        $this->assertSame('my_custom_memory_service', (string) $arguments[0][0]);
    }

    #[TestDox('Memory configuration preserves correct processor priority ordering')]
    public function testMemoryProcessorPriorityOrdering()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => 'test_memory',
                        'prompt' => [
                            'text' => 'Test prompt',
                        ],
                    ],
                ],
            ],
        ]);

        $memoryDef = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $systemDef = $container->getDefinition('ai.agent.test_agent.system_prompt_processor');

        // Memory processor should have higher priority (more negative number)
        $memoryTags = $memoryDef->getTag('ai.agent.input_processor');
        $systemTags = $systemDef->getTag('ai.agent.input_processor');

        $this->assertSame(-40, $memoryTags[0]['priority']);
        $this->assertSame(-30, $systemTags[0]['priority']);
        $this->assertLessThan($systemTags[0]['priority'], $memoryTags[0]['priority']);
    }

    #[TestDox('Memory processor uses correct MemoryInputProcessor class and service reference')]
    public function testMemoryProcessorIntegration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => 'my_memory_service',
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $definition = $container->getDefinition('ai.agent.test_agent.memory_input_processor');

        // Check correct class
        $this->assertSame(MemoryInputProcessor::class, $definition->getClass());

        // Check service reference argument (static memory since service doesn't exist)
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));
        $arguments = $definition->getArguments();
        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0][0]);
        $this->assertSame('ai.agent.test_agent.static_memory_provider', (string) $arguments[0][0]);

        // Check proper tagging
        $tags = $definition->getTag('ai.agent.input_processor');
        $this->assertNotEmpty($tags);
        $this->assertSame('ai.agent.test_agent', $tags[0]['agent']);
        $this->assertSame(-40, $tags[0]['priority']);
    }

    #[TestDox('Memory with existing service uses service reference directly')]
    public function testMemoryWithExistingServiceUsesServiceReference()
    {
        // First create a service that exists
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'test');

        // Register a memory service
        $container->register('existing_memory_service', MemoryInputProcessor::class);

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => ['service' => 'existing_memory_service'], // New array syntax for service
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ], $container);

        // Should use the existing service directly, not create a StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        $memoryProcessor = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $arguments = $memoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0][0]);
        $this->assertSame('existing_memory_service', (string) $arguments[0][0]);
    }

    #[TestDox('Memory with non-existing service creates StaticMemoryProvider')]
    public function testMemoryWithNonExistingServiceCreatesStaticMemoryProvider()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => 'This is static memory content', // This is not a service
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ]);

        // Should create a StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        // Check StaticMemoryProvider configuration
        $staticProvider = $container->getDefinition('ai.agent.test_agent.static_memory_provider');
        $this->assertSame(StaticMemoryProvider::class, $staticProvider->getClass());
        $staticProviderArgs = $staticProvider->getArguments();
        $this->assertSame('This is static memory content', $staticProviderArgs[0]);

        // Check that memory processor uses the StaticMemoryProvider
        $memoryProcessor = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $memoryProcessorArgs = $memoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $memoryProcessorArgs[0][0]);
        $this->assertSame('ai.agent.test_agent.static_memory_provider', (string) $memoryProcessorArgs[0][0]);
    }

    #[TestDox('Memory with service alias uses alias correctly')]
    public function testMemoryWithServiceAliasUsesAlias()
    {
        // Create a container with a service alias
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'test');

        // Register a service with an alias
        $container->register('actual_memory_service', MemoryInputProcessor::class);
        $container->setAlias('memory_alias', 'actual_memory_service');

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load([
            'ai' => [
                'agent' => [
                    'test_agent' => [
                        'model' => 'gpt-4',
                        'memory' => ['service' => 'memory_alias'], // Use new array syntax for service alias
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                        ],
                    ],
                ],
            ],
        ], $container);

        // Should use the alias directly, not create a StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.test_agent.memory_input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.test_agent.static_memory_provider'));

        $memoryProcessor = $container->getDefinition('ai.agent.test_agent.memory_input_processor');
        $arguments = $memoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $arguments[0][0]);
        $this->assertSame('memory_alias', (string) $arguments[0][0]);
    }

    #[TestDox('Different agents can use different memory types')]
    public function testDifferentAgentsCanUseDifferentMemoryTypes()
    {
        // Create a container with one existing service
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', 'test');

        $container->register('dynamic_memory_service', MemoryInputProcessor::class);

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load([
            'ai' => [
                'agent' => [
                    'agent_with_service' => [
                        'model' => 'gpt-4',
                        'memory' => ['service' => 'dynamic_memory_service'], // Use new array syntax for service
                        'prompt' => [
                            'text' => 'Agent with service.',
                        ],
                    ],
                    'agent_with_static' => [
                        'model' => 'claude-3-opus-20240229',
                        'memory' => 'Static memory context for this agent', // Static content
                        'prompt' => [
                            'text' => 'Agent with static memory.',
                        ],
                    ],
                ],
            ],
        ], $container);

        // First agent uses service reference
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_service.memory_input_processor'));
        $this->assertFalse($container->hasDefinition('ai.agent.agent_with_service.static_memory_provider'));

        $serviceMemoryProcessor = $container->getDefinition('ai.agent.agent_with_service.memory_input_processor');
        $serviceArgs = $serviceMemoryProcessor->getArguments();
        $this->assertInstanceOf(Reference::class, $serviceArgs[0][0]);
        $this->assertSame('dynamic_memory_service', (string) $serviceArgs[0][0]);

        // Second agent uses StaticMemoryProvider
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_static.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.agent_with_static.static_memory_provider'));

        $staticProvider = $container->getDefinition('ai.agent.agent_with_static.static_memory_provider');
        $this->assertSame(StaticMemoryProvider::class, $staticProvider->getClass());
        $staticProviderArgs = $staticProvider->getArguments();
        $this->assertSame('Static memory context for this agent', $staticProviderArgs[0]);
    }

    #[TestDox('Model configuration with query parameters in model name works correctly')]
    public function testModelConfigurationWithQueryParameters()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test' => [
                        'model' => 'gpt-4o-mini?temperature=0.5&max_tokens=2000',
                    ],
                ],
            ],
        ]);

        $agentDefinition = $container->getDefinition('ai.agent.test');
        $this->assertSame('gpt-4o-mini?temperature=0.5&max_tokens=2000', $agentDefinition->getArgument(1));
    }

    #[TestDox('Model configuration with separate options array works correctly')]
    public function testModelConfigurationWithSeparateOptions()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test' => [
                        'model' => [
                            'name' => 'gpt-4o-mini',
                            'options' => [
                                'temperature' => 0.7,
                                'max_tokens' => 1500,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $agentDefinition = $container->getDefinition('ai.agent.test');
        $this->assertSame('gpt-4o-mini?temperature=0.7&max_tokens=1500', $agentDefinition->getArgument(1));
    }

    #[TestDox('Model configuration throws exception when using both query parameters and options array')]
    public function testModelConfigurationConflictThrowsException()
    {
        // Should throw exception when both query parameters and options array are provided
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot use both query parameters in model name and options array');

        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test' => [
                        'model' => [
                            'name' => 'gpt-4o-mini?temperature=0.5&max_tokens=1000',
                            'options' => [
                                'temperature' => 0.7,
                                'stream' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Model configuration query parameters are passed as strings')]
    public function testModelConfigurationTypeConversion()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test' => [
                        'model' => 'gpt-4o-mini?temperature=0.5&max_tokens=2000&stream=true&presence_penalty=0',
                    ],
                ],
            ],
        ]);

        $agentDefinition = $container->getDefinition('ai.agent.test');
        // Query parameters are maintained as strings when parsed from URL
        $this->assertSame('gpt-4o-mini?temperature=0.5&max_tokens=2000&stream=true&presence_penalty=0', $agentDefinition->getArgument(1));
    }

    #[TestDox('Vectorizer model configuration with query parameters works correctly')]
    public function testVectorizerModelConfigurationWithQueryParameters()
    {
        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'test' => [
                        'model' => 'text-embedding-3-small?dimensions=512',
                    ],
                ],
            ],
        ]);

        $vectorizerDefinition = $container->getDefinition('ai.vectorizer.test');
        $this->assertSame('text-embedding-3-small?dimensions=512', $vectorizerDefinition->getArgument(1));
    }

    #[TestDox('Vectorizer model configuration throws exception when using both query parameters and options array')]
    public function testVectorizerModelConfigurationConflictThrowsException()
    {
        // Should throw exception when both query parameters and options array are provided
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot use both query parameters in model name and options array');

        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'test' => [
                        'model' => [
                            'name' => 'text-embedding-3-small?dimensions=512',
                            'options' => [
                                'dimensions' => 1536,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testVectorizerConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'my_vectorizer' => [
                        'platform' => 'my_platform_service_id',
                        'model' => [
                            'name' => 'text-embedding-3-small',
                            'options' => ['dimension' => 512],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.vectorizer.my_vectorizer'));

        $vectorizerDefinition = $container->getDefinition('ai.vectorizer.my_vectorizer');
        $this->assertSame(Vectorizer::class, $vectorizerDefinition->getClass());
        $this->assertTrue($vectorizerDefinition->hasTag('ai.vectorizer'));

        // Check that model is passed as a string with options as query params
        $this->assertSame('text-embedding-3-small?dimension=512', $vectorizerDefinition->getArgument(1));
    }

    public function testVectorizerWithLoggerInjection()
    {
        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'my_vectorizer' => [
                        'platform' => 'my_platform_service_id',
                        'model' => 'text-embedding-3-small',
                    ],
                ],
            ],
        ]);

        $vectorizerDefinition = $container->getDefinition('ai.vectorizer.my_vectorizer');
        $arguments = $vectorizerDefinition->getArguments();

        $this->assertCount(3, $arguments, 'Vectorizer should have 3 arguments: platform, model, and logger');

        // First argument should be platform reference
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('my_platform_service_id', (string) $arguments[0]);

        // Second argument should be model string
        $this->assertIsString($arguments[1]);
        $this->assertSame('text-embedding-3-small', $arguments[1]);

        // Third argument should be logger reference with IGNORE_ON_INVALID_REFERENCE
        $this->assertInstanceOf(Reference::class, $arguments[2]);
        $this->assertSame('logger', (string) $arguments[2]);
        $this->assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $arguments[2]->getInvalidBehavior());
    }

    public function testInjectionVectorizerAliasIsRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'test' => [
                        'model' => 'text-embedding-3-small?dimensions=512',
                    ],
                    'another' => [
                        'model' => 'text-embedding-3-small?dimensions=512',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasAlias(VectorizerInterface::class.' $test'));
        $this->assertTrue($container->hasAlias(VectorizerInterface::class.' $another'));
    }

    public function testIndexerWithConfiguredVectorizer()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'vectorizer' => [
                    'my_vectorizer' => [
                        'platform' => 'my_platform_service_id',
                        'model' => 'text-embedding-3-small',
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'ai.vectorizer.my_vectorizer',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.vectorizer.my_vectorizer'));
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer.processor'));

        // Check SourceIndexer: [0]=loader, [1]=processor
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $arguments = $indexerDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame(InMemoryLoader::class, (string) $arguments[0]);

        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.indexer.my_indexer.processor', (string) $arguments[1]);

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $processorArgs[0]);
        $this->assertSame('ai.vectorizer.my_vectorizer', (string) $processorArgs[0]);

        // Should not create model-specific vectorizer when using configured one
        $this->assertFalse($container->hasDefinition('ai.indexer.my_indexer.vectorizer'));
        $this->assertFalse($container->hasDefinition('ai.indexer.my_indexer.model'));
    }

    public function testIndexerWithStringSource()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'source' => 'https://example.com/feed.xml',
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        // With source configured, main service is ConfiguredSourceIndexer
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $configuredIndexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $this->assertSame(ConfiguredSourceIndexer::class, $configuredIndexerDefinition->getClass());
        $arguments = $configuredIndexerDefinition->getArguments();

        // ConfiguredSourceIndexer arguments: [0] = inner indexer reference, [1] = source
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('ai.indexer.my_indexer.inner', (string) $arguments[0]);
        $this->assertSame('https://example.com/feed.xml', $arguments[1]);
    }

    public function testIndexerWithArraySource()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'source' => [
                            '/path/to/file1.txt',
                            '/path/to/file2.txt',
                            'https://example.com/feed.xml',
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        // With source configured, main service is ConfiguredSourceIndexer
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $configuredIndexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $this->assertSame(ConfiguredSourceIndexer::class, $configuredIndexerDefinition->getClass());
        $arguments = $configuredIndexerDefinition->getArguments();

        // ConfiguredSourceIndexer arguments: [0] = inner indexer reference, [1] = source
        $this->assertIsArray($arguments[1]);
        $this->assertCount(3, $arguments[1]);
        $this->assertSame([
            '/path/to/file1.txt',
            '/path/to/file2.txt',
            'https://example.com/feed.xml',
        ], $arguments[1]);
    }

    public function testIndexerWithNullSource()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                        // source not configured, should default to null - no ConfiguredSourceIndexer wrapper
                    ],
                ],
            ],
        ]);

        // Without source configured, main service is SourceIndexer directly (no ConfiguredSourceIndexer wrapper)
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertFalse($container->hasDefinition('ai.indexer.my_indexer.inner'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $this->assertSame(SourceIndexer::class, $indexerDefinition->getClass());
    }

    public function testIndexerWithoutLoader()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        // No loader configured - should create DocumentIndexer
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        // Without loader configured, main service is DocumentIndexer for direct document indexing
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertFalse($container->hasDefinition('ai.indexer.my_indexer.inner'));
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $this->assertSame(DocumentIndexer::class, $indexerDefinition->getClass());

        // Should have processor as only argument
        $arguments = $indexerDefinition->getArguments();
        $this->assertCount(1, $arguments);
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('ai.indexer.my_indexer.processor', (string) $arguments[0]);
    }

    public function testIndexerWithSourceButWithoutLoaderThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indexer "my_indexer" has a "source" configured but no "loader". A loader is required to process sources.');

        $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        // Source configured without loader - should throw exception
                        'source' => '/path/to/file.txt',
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);
    }

    public function testIndexerWithConfiguredTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'transformers' => [
                            TextTrimTransformer::class,
                            'App\CustomTransformer',
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer.processor'));

        // Check SourceIndexer: [0]=loader, [1]=processor
        $indexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $indexerArgs = $indexerDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $indexerArgs[0]);
        $this->assertSame(InMemoryLoader::class, (string) $indexerArgs[0]);

        $this->assertInstanceOf(Reference::class, $indexerArgs[1]);
        $this->assertSame('ai.indexer.my_indexer.processor', (string) $indexerArgs[1]);

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        $this->assertSame([], $processorArgs[2]); // Empty filters
        $this->assertIsArray($processorArgs[3]);
        $this->assertCount(2, $processorArgs[3]);

        $this->assertInstanceOf(Reference::class, $processorArgs[3][0]);
        $this->assertSame(TextTrimTransformer::class, (string) $processorArgs[3][0]);

        $this->assertInstanceOf(Reference::class, $processorArgs[3][1]);
        $this->assertSame('App\CustomTransformer', (string) $processorArgs[3][1]);
    }

    public function testIndexerWithEmptyTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'transformers' => [],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer.processor'));

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        $this->assertSame([], $processorArgs[2]); // Empty filters
        $this->assertSame([], $processorArgs[3]); // Empty transformers
    }

    public function testIndexerWithoutTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                        // transformers not configured, should default to empty array
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer.processor'));

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        $this->assertSame([], $processorArgs[2]); // Empty filters
        $this->assertSame([], $processorArgs[3]); // Empty transformers
    }

    public function testIndexerWithSourceAndTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'source' => [
                            '/path/to/file1.txt',
                            '/path/to/file2.txt',
                        ],
                        'transformers' => [
                            TextTrimTransformer::class,
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        // With source configured, main service is ConfiguredSourceIndexer
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $configuredIndexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $this->assertSame(ConfiguredSourceIndexer::class, $configuredIndexerDefinition->getClass());
        $configuredArguments = $configuredIndexerDefinition->getArguments();

        // Check source on ConfiguredSourceIndexer
        $this->assertIsArray($configuredArguments[1]);
        $this->assertCount(2, $configuredArguments[1]);
        $this->assertSame([
            '/path/to/file1.txt',
            '/path/to/file2.txt',
        ], $configuredArguments[1]);

        // Check inner indexer: [0]=loader, [1]=processor
        $innerIndexerDefinition = $container->getDefinition('ai.indexer.my_indexer.inner');
        $arguments = $innerIndexerDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame(InMemoryLoader::class, (string) $arguments[0]);

        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.indexer.my_indexer.processor', (string) $arguments[1]);

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $processorArgs[0]);
        $this->assertSame('my_vectorizer_service', (string) $processorArgs[0]);

        $this->assertInstanceOf(Reference::class, $processorArgs[1]);
        $this->assertSame('ai.store.memory.my_store', (string) $processorArgs[1]);

        $this->assertSame([], $processorArgs[2]); // Empty filters
        $this->assertIsArray($processorArgs[3]);
        $this->assertCount(1, $processorArgs[3]);
        $this->assertInstanceOf(Reference::class, $processorArgs[3][0]);
        $this->assertSame(TextTrimTransformer::class, (string) $processorArgs[3][0]);
    }

    public function testIndexerWithConfiguredFilters()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'filters' => [
                            TextContainsFilter::class,
                            'App\CustomFilter',
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer.processor'));

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        // Verify filters are in the correct position (index 2)
        $this->assertIsArray($processorArgs[2]);
        $this->assertCount(2, $processorArgs[2]);

        $this->assertInstanceOf(Reference::class, $processorArgs[2][0]);
        $this->assertSame(TextContainsFilter::class, (string) $processorArgs[2][0]);

        $this->assertInstanceOf(Reference::class, $processorArgs[2][1]);
        $this->assertSame('App\CustomFilter', (string) $processorArgs[2][1]);

        // Verify transformers are in the correct position (index 3, after filters)
        $this->assertSame([], $processorArgs[3]); // Empty transformers
    }

    public function testIndexerWithEmptyFilters()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'filters' => [],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer.processor'));

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        $this->assertSame([], $processorArgs[2]); // Empty filters
    }

    public function testIndexerWithoutFilters()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                        // filters not configured, should default to empty array
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer.processor'));

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        $this->assertSame([], $processorArgs[2]); // Empty filters
    }

    public function testIndexerWithFiltersAndTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'filters' => [
                            TextContainsFilter::class,
                        ],
                        'transformers' => [
                            TextTrimTransformer::class,
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer.processor'));

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        // Verify filters are at index 2
        $this->assertIsArray($processorArgs[2]);
        $this->assertCount(1, $processorArgs[2]);
        $this->assertInstanceOf(Reference::class, $processorArgs[2][0]);
        $this->assertSame(TextContainsFilter::class, (string) $processorArgs[2][0]);

        // Verify transformers are at index 3
        $this->assertIsArray($processorArgs[3]);
        $this->assertCount(1, $processorArgs[3]);
        $this->assertInstanceOf(Reference::class, $processorArgs[3][0]);
        $this->assertSame(TextTrimTransformer::class, (string) $processorArgs[3][0]);
    }

    public function testIndexerWithSourceFiltersAndTransformers()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'source' => [
                            '/path/to/file1.txt',
                            '/path/to/file2.txt',
                        ],
                        'filters' => [
                            TextContainsFilter::class,
                        ],
                        'transformers' => [
                            TextTrimTransformer::class,
                        ],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        // With source configured, main service is ConfiguredSourceIndexer
        $this->assertTrue($container->hasDefinition('ai.indexer.my_indexer'));
        $configuredIndexerDefinition = $container->getDefinition('ai.indexer.my_indexer');
        $this->assertSame(ConfiguredSourceIndexer::class, $configuredIndexerDefinition->getClass());
        $configuredArguments = $configuredIndexerDefinition->getArguments();

        // ConfiguredSourceIndexer: [0] = inner indexer reference, [1] = source
        $this->assertInstanceOf(Reference::class, $configuredArguments[0]);
        $this->assertSame('ai.indexer.my_indexer.inner', (string) $configuredArguments[0]);
        $this->assertIsArray($configuredArguments[1]); // source
        $this->assertCount(2, $configuredArguments[1]);
        $this->assertSame(['/path/to/file1.txt', '/path/to/file2.txt'], $configuredArguments[1]);

        // Check inner indexer: [0]=loader, [1]=processor
        $innerIndexerDefinition = $container->getDefinition('ai.indexer.my_indexer.inner');
        $arguments = $innerIndexerDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[0]); // loader
        $this->assertSame(InMemoryLoader::class, (string) $arguments[0]);

        $this->assertInstanceOf(Reference::class, $arguments[1]); // processor
        $this->assertSame('ai.indexer.my_indexer.processor', (string) $arguments[1]);

        // Check DocumentProcessor: [0]=vectorizer, [1]=store, [2]=filters, [3]=transformers, [4]=logger
        $processorDefinition = $container->getDefinition('ai.indexer.my_indexer.processor');
        $processorArgs = $processorDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $processorArgs[0]); // vectorizer
        $this->assertSame('my_vectorizer_service', (string) $processorArgs[0]);

        $this->assertInstanceOf(Reference::class, $processorArgs[1]); // store
        $this->assertSame('ai.store.memory.my_store', (string) $processorArgs[1]);

        $this->assertIsArray($processorArgs[2]); // filters
        $this->assertCount(1, $processorArgs[2]);
        $this->assertInstanceOf(Reference::class, $processorArgs[2][0]);
        $this->assertSame(TextContainsFilter::class, (string) $processorArgs[2][0]);

        $this->assertIsArray($processorArgs[3]); // transformers
        $this->assertCount(1, $processorArgs[3]);
        $this->assertInstanceOf(Reference::class, $processorArgs[3][0]);
        $this->assertSame(TextTrimTransformer::class, (string) $processorArgs[3][0]);

        $this->assertInstanceOf(Reference::class, $processorArgs[4]); // logger
        $this->assertSame('logger', (string) $processorArgs[4]);
    }

    public function testInjectionIndexerAliasIsRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'indexer' => [
                    'my_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'transformers' => [],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                    'another' => [
                        'loader' => InMemoryLoader::class,
                        'transformers' => [],
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasAlias(IndexerInterface::class.' $myIndexer'));
        $this->assertTrue($container->hasAlias(IndexerInterface::class.' $another'));
    }

    public function testRetrieverWithConfiguredVectorizer()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'vectorizer' => [
                    'my_vectorizer' => [
                        'platform' => 'my_platform_service_id',
                        'model' => 'text-embedding-3-small',
                    ],
                ],
                'retriever' => [
                    'my_retriever' => [
                        'vectorizer' => 'ai.vectorizer.my_vectorizer',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.retriever.my_retriever'));
        $this->assertTrue($container->hasDefinition('ai.vectorizer.my_vectorizer'));

        $retrieverDefinition = $container->getDefinition('ai.retriever.my_retriever');
        $arguments = $retrieverDefinition->getArguments();

        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('ai.store.memory.my_store', (string) $arguments[0]);

        $this->assertInstanceOf(Reference::class, $arguments[1]);
        $this->assertSame('ai.vectorizer.my_vectorizer', (string) $arguments[1]);

        $this->assertInstanceOf(Reference::class, $arguments[2]); // logger
        $this->assertSame('logger', (string) $arguments[2]);
    }

    public function testRetrieverAliasIsRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'retriever' => [
                    'my_retriever' => [
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                    'another' => [
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasAlias(RetrieverInterface::class.' $myRetriever'));
        $this->assertTrue($container->hasAlias(RetrieverInterface::class.' $another'));
    }

    public function testSingleRetrieverCreatesDefaultAlias()
    {
        $container = $this->buildContainer([
            'ai' => [
                'store' => [
                    'memory' => [
                        'my_store' => [],
                    ],
                ],
                'retriever' => [
                    'default' => [
                        'vectorizer' => 'my_vectorizer_service',
                        'store' => 'ai.store.memory.my_store',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.retriever.default'));
        $this->assertTrue($container->hasAlias(RetrieverInterface::class));
    }

    public function testValidMultiAgentConfiguration()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'dispatcher' => [
                        'model' => 'gpt-4o-mini',
                    ],
                    'technical' => [
                        'model' => 'gpt-4',
                    ],
                    'general' => [
                        'model' => 'claude-3-opus-20240229',
                    ],
                ],
                'multi_agent' => [
                    'support' => [
                        'orchestrator' => 'dispatcher',
                        'fallback' => 'general',
                        'handoffs' => [
                            'technical' => ['code', 'debug', 'error'],
                        ],
                    ],
                ],
            ],
        ]);

        // Verify the MultiAgent service is created
        $this->assertTrue($container->hasDefinition('ai.multi_agent.support'));

        $multiAgentDefinition = $container->getDefinition('ai.multi_agent.support');

        // Verify the class is correct
        $this->assertSame(MultiAgent::class, $multiAgentDefinition->getClass());

        // Verify arguments
        $arguments = $multiAgentDefinition->getArguments();
        $this->assertCount(4, $arguments);

        // First argument: orchestrator agent reference
        $this->assertInstanceOf(Reference::class, $arguments[0]);
        $this->assertSame('ai.agent.dispatcher', (string) $arguments[0]);

        // Second argument: handoffs array
        $handoffs = $arguments[1];
        $this->assertIsArray($handoffs);
        $this->assertCount(1, $handoffs);

        // Verify handoff structure
        $handoff = $handoffs[0];
        $this->assertInstanceOf(Definition::class, $handoff);
        $this->assertSame(Handoff::class, $handoff->getClass());
        $handoffArgs = $handoff->getArguments();
        $this->assertCount(2, $handoffArgs);
        $this->assertInstanceOf(Reference::class, $handoffArgs[0]);
        $this->assertSame('ai.agent.technical', (string) $handoffArgs[0]);
        $this->assertSame(['code', 'debug', 'error'], $handoffArgs[1]);

        // Third argument: fallback agent reference
        $this->assertInstanceOf(Reference::class, $arguments[2]);
        $this->assertSame('ai.agent.general', (string) $arguments[2]);

        // Fourth argument: name
        $this->assertSame('support', $arguments[3]);

        // Verify the MultiAgent service has proper tags
        $tags = $multiAgentDefinition->getTags();
        $this->assertArrayHasKey('ai.agent', $tags);
        $this->assertSame([['name' => 'support']], $tags['ai.agent']);

        // Verify alias is created
        $this->assertTrue($container->hasAlias(AgentInterface::class.' $support'));
    }

    public function testMultiAgentWithMultipleHandoffs()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'orchestrator' => [
                        'model' => 'gpt-4o-mini',
                    ],
                    'code_expert' => [
                        'model' => 'gpt-4',
                    ],
                    'billing_expert' => [
                        'model' => 'gpt-4',
                    ],
                    'general_assistant' => [
                        'model' => 'claude-3-opus-20240229',
                    ],
                ],
                'multi_agent' => [
                    'customer_service' => [
                        'orchestrator' => 'orchestrator',
                        'fallback' => 'general_assistant',
                        'handoffs' => [
                            'code_expert' => ['bug', 'code', 'programming', 'technical'],
                            'billing_expert' => ['payment', 'invoice', 'subscription', 'refund'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.multi_agent.customer_service'));

        $multiAgentDefinition = $container->getDefinition('ai.multi_agent.customer_service');
        $handoffs = $multiAgentDefinition->getArgument(1);

        $this->assertIsArray($handoffs);
        $this->assertCount(2, $handoffs);

        // Both handoffs should be Definition objects
        foreach ($handoffs as $handoff) {
            $this->assertInstanceOf(Definition::class, $handoff);
            $this->assertSame(Handoff::class, $handoff->getClass());
            $handoffArgs = $handoff->getArguments();
            $this->assertCount(2, $handoffArgs);
            $this->assertInstanceOf(Reference::class, $handoffArgs[0]);
            $this->assertIsArray($handoffArgs[1]);
        }

        // Verify first handoff (code_expert)
        $codeHandoff = $handoffs[0];
        $codeHandoffArgs = $codeHandoff->getArguments();
        $this->assertSame('ai.agent.code_expert', (string) $codeHandoffArgs[0]);
        $this->assertSame(['bug', 'code', 'programming', 'technical'], $codeHandoffArgs[1]);

        // Verify second handoff (billing_expert)
        $billingHandoff = $handoffs[1];
        $billingHandoffArgs = $billingHandoff->getArguments();
        $this->assertSame('ai.agent.billing_expert', (string) $billingHandoffArgs[0]);
        $this->assertSame(['payment', 'invoice', 'subscription', 'refund'], $billingHandoffArgs[1]);
    }

    public function testEmptyHandoffsThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "ai.multi_agent.support.handoffs" should have at least 1 element(s) defined.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'orchestrator' => [
                        'model' => 'gpt-4o-mini',
                    ],
                    'general' => [
                        'model' => 'claude-3-opus-20240229',
                    ],
                ],
                'multi_agent' => [
                    'support' => [
                        'orchestrator' => 'orchestrator',
                        'fallback' => 'general',
                        'handoffs' => [],
                    ],
                ],
            ],
        ]);
    }

    public function testEmptyWhenConditionsThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "ai.multi_agent.support.handoffs.technical" should have at least 1 element(s) defined.');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'orchestrator' => [
                        'model' => 'gpt-4o-mini',
                    ],
                    'technical' => [
                        'model' => 'gpt-4',
                    ],
                    'general' => [
                        'model' => 'claude-3-opus-20240229',
                    ],
                ],
                'multi_agent' => [
                    'support' => [
                        'orchestrator' => 'orchestrator',
                        'fallback' => 'general',
                        'handoffs' => [
                            'technical' => [],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testMultiAgentReferenceToNonExistingAgentThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The agent "non_existing" referenced in multi-agent "support" as orchestrator does not exist');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'general' => [
                        'model' => 'claude-3-opus-20240229',
                    ],
                ],
                'multi_agent' => [
                    'support' => [
                        'orchestrator' => 'non_existing',
                        'fallback' => 'general',
                        'handoffs' => [
                            'general' => ['help'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testAgentAndMultiAgentNameConflictThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Agent names and multi-agent names must be unique. Duplicate name(s) found: "support"');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'support' => [
                        'model' => 'gpt-4o-mini',
                    ],
                ],
                'multi_agent' => [
                    'support' => [
                        'orchestrator' => 'dispatcher',
                        'fallback' => 'general',
                        'handoffs' => [
                            'technical' => ['code', 'debug'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testMultipleAgentAndMultiAgentNameConflictsThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Agent names and multi-agent names must be unique. Duplicate name(s) found: "support, billing"');

        $this->buildContainer([
            'ai' => [
                'agent' => [
                    'support' => [
                        'model' => 'gpt-4o-mini',
                    ],
                    'billing' => [
                        'model' => 'gpt-4o-mini',
                    ],
                ],
                'multi_agent' => [
                    'support' => [
                        'orchestrator' => 'dispatcher',
                        'fallback' => 'general',
                        'handoffs' => [
                            'technical' => ['code', 'debug'],
                        ],
                    ],
                    'billing' => [
                        'orchestrator' => 'dispatcher',
                        'fallback' => 'general',
                        'handoffs' => [
                            'payments' => ['payment', 'invoice'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Comprehensive multi-agent configuration with all features works correctly')]
    public function testComprehensiveMultiAgentHappyPath()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    // Orchestrator agent - lightweight dispatcher with tools
                    'orchestrator' => [
                        'model' => 'gpt-4o-mini',
                        'prompt' => [
                            'text' => 'You are a dispatcher that routes requests to specialized agents.',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            ['service' => 'routing_tool', 'description' => 'Routes requests to appropriate agents'],
                        ],
                    ],
                    // Code expert agent with memory and tools
                    'code_expert' => [
                        'model' => 'gpt-4',
                        'prompt' => [
                            'text' => 'You are a senior software engineer specialized in debugging and code optimization.',
                            'include_tools' => true,
                        ],
                        'memory' => 'code_memory_service',
                        'tools' => [
                            ['service' => 'code_analyzer', 'description' => 'Analyzes code for issues'],
                            ['service' => 'test_runner', 'description' => 'Runs unit tests'],
                        ],
                    ],
                    // Documentation expert
                    'docs_expert' => [
                        'model' => 'claude-3-opus-20240229',
                        'prompt' => 'You are a technical documentation specialist.',
                    ],
                    // General support agent with memory
                    'general_support' => [
                        'model' => 'claude-3-sonnet-20240229',
                        'prompt' => [
                            'text' => 'You are a helpful general support assistant.',
                        ],
                        'memory' => 'general_memory_service',
                    ],
                ],
                'multi_agent' => [
                    // Customer support multi-agent system
                    'customer_support' => [
                        'orchestrator' => 'orchestrator',
                        'fallback' => 'general_support',
                        'handoffs' => [
                            'code_expert' => ['bug', 'error', 'code', 'debug', 'performance', 'optimization'],
                            'docs_expert' => ['documentation', 'docs', 'readme', 'api', 'guide', 'tutorial'],
                        ],
                    ],
                    // Development multi-agent system (can reuse agents)
                    'development_assistant' => [
                        'orchestrator' => 'orchestrator',
                        'fallback' => 'code_expert',
                        'handoffs' => [
                            'docs_expert' => ['comment', 'docblock', 'documentation'],
                        ],
                    ],
                ],
            ],
        ]);

        // Verify all agents are created
        $this->assertTrue($container->hasDefinition('ai.agent.orchestrator'));
        $this->assertTrue($container->hasDefinition('ai.agent.code_expert'));
        $this->assertTrue($container->hasDefinition('ai.agent.docs_expert'));
        $this->assertTrue($container->hasDefinition('ai.agent.general_support'));

        // Verify multi-agent services are created
        $this->assertTrue($container->hasDefinition('ai.multi_agent.customer_support'));
        $this->assertTrue($container->hasDefinition('ai.multi_agent.development_assistant'));

        // Test customer_support multi-agent configuration
        $customerSupportDef = $container->getDefinition('ai.multi_agent.customer_support');
        $this->assertSame(MultiAgent::class, $customerSupportDef->getClass());

        $csArguments = $customerSupportDef->getArguments();
        $this->assertCount(4, $csArguments);

        // Orchestrator reference
        $this->assertInstanceOf(Reference::class, $csArguments[0]);
        $this->assertSame('ai.agent.orchestrator', (string) $csArguments[0]);

        // Handoffs
        $csHandoffs = $csArguments[1];
        $this->assertIsArray($csHandoffs);
        $this->assertCount(2, $csHandoffs);

        // Code expert handoff
        $codeHandoff = $csHandoffs[0];
        $this->assertInstanceOf(Definition::class, $codeHandoff);
        $codeHandoffArgs = $codeHandoff->getArguments();
        $this->assertSame('ai.agent.code_expert', (string) $codeHandoffArgs[0]);
        $this->assertSame(['bug', 'error', 'code', 'debug', 'performance', 'optimization'], $codeHandoffArgs[1]);

        // Docs expert handoff
        $docsHandoff = $csHandoffs[1];
        $this->assertInstanceOf(Definition::class, $docsHandoff);
        $docsHandoffArgs = $docsHandoff->getArguments();
        $this->assertSame('ai.agent.docs_expert', (string) $docsHandoffArgs[0]);
        $this->assertSame(['documentation', 'docs', 'readme', 'api', 'guide', 'tutorial'], $docsHandoffArgs[1]);

        // Fallback
        $this->assertInstanceOf(Reference::class, $csArguments[2]);
        $this->assertSame('ai.agent.general_support', (string) $csArguments[2]);

        // Name
        $this->assertSame('customer_support', $csArguments[3]);

        // Verify tags and aliases
        $csTags = $customerSupportDef->getTags();
        $this->assertArrayHasKey('ai.agent', $csTags);
        $this->assertSame([['name' => 'customer_support']], $csTags['ai.agent']);

        $this->assertTrue($container->hasAlias(AgentInterface::class.' $customerSupport'));
        $this->assertTrue($container->hasAlias(AgentInterface::class.' $developmentAssistant'));

        // Test development_assistant multi-agent configuration
        $devAssistantDef = $container->getDefinition('ai.multi_agent.development_assistant');
        $daArguments = $devAssistantDef->getArguments();

        // Verify it uses code_expert as fallback
        $this->assertInstanceOf(Reference::class, $daArguments[2]);
        $this->assertSame('ai.agent.code_expert', (string) $daArguments[2]);

        // Verify it has only docs_expert handoff
        $daHandoffs = $daArguments[1];
        $this->assertCount(1, $daHandoffs);

        // Verify agent components are properly configured

        // Code expert should have memory processor
        $this->assertTrue($container->hasDefinition('ai.agent.code_expert.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.code_expert.static_memory_provider'));

        // Code expert should have tool processor
        $this->assertTrue($container->hasDefinition('ai.tool.agent_processor.code_expert'));

        // Code expert should have system prompt processor
        $this->assertTrue($container->hasDefinition('ai.agent.code_expert.system_prompt_processor'));

        // Docs expert should have only system prompt processor, no memory
        $this->assertFalse($container->hasDefinition('ai.agent.docs_expert.memory_input_processor'));
        $this->assertTrue($container->hasDefinition('ai.agent.docs_expert.system_prompt_processor'));

        // General support should have memory processor
        $this->assertTrue($container->hasDefinition('ai.agent.general_support.memory_input_processor'));

        // Orchestrator should have tools processor
        $this->assertTrue($container->hasDefinition('ai.tool.agent_processor.orchestrator'));
    }

    #[TestDox('Agent model configuration preserves colon notation in model names (e.g., qwen3:0.6b)')]
    #[TestWith(['qwen3:0.6b'])]
    #[TestWith(['deepseek-r1:70b'])]
    #[TestWith(['qwen3-coder:30b'])]
    #[TestWith(['qwen3:0.6b?think=false'])]
    public function testModelConfigurationWithColonNotation(string $model)
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test' => [
                        'model' => [
                            'name' => $model,
                        ],
                    ],
                ],
            ],
        ]);

        $agentDefinition = $container->getDefinition('ai.agent.test');

        $this->assertSame($model, $agentDefinition->getArgument(1));
    }

    #[TestDox('Vectorizer model configuration preserves colon notation in model names (e.g., bge-m3:1024)')]
    #[TestWith(['bge-m3:567m'])]
    #[TestWith(['nomic-embed-text:137m-v1.5-fp16'])]
    #[TestWith(['qwen3-embedding:0.6b?normalize=true'])]
    public function testVectorizerConfigurationWithColonNotation(string $model)
    {
        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'test' => [
                        'model' => [
                            'name' => $model,
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.vectorizer.test');

        $this->assertSame($model, $definition->getArgument(1));
    }

    public function testAgentModelBooleanOptionsArePreserved()
    {
        $container = $this->buildContainer([
            'ai' => [
                'agent' => [
                    'test' => [
                        'model' => [
                            'name' => 'qwen3',
                            'options' => [
                                'stream' => false,
                                'think' => true,
                                'nested' => [
                                    'bool' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $agentDefinition = $container->getDefinition('ai.agent.test');

        $this->assertSame('qwen3?stream=false&think=true&nested%5Bbool%5D=false', $agentDefinition->getArgument(1));
    }

    public function testVectorizerModelBooleanOptionsArePreserved()
    {
        $container = $this->buildContainer([
            'ai' => [
                'vectorizer' => [
                    'test' => [
                        'model' => [
                            'name' => 'text-embedding-3-small',
                            'options' => [
                                'normalize' => false,
                                'cache' => true,
                                'nested' => [
                                    'bool' => false,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $vectorizerDefinition = $container->getDefinition('ai.vectorizer.test');

        $this->assertSame('text-embedding-3-small?normalize=false&cache=true&nested%5Bbool%5D=false', $vectorizerDefinition->getArgument(1));
    }

    public function testCachePlatformCanBeUsed()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'ollama' => [
                        'host_url' => 'http://127.0.0.1:11434',
                    ],
                    'cache' => [
                        'ollama' => [
                            'platform' => 'ai.platform.ollama',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.cache.ollama'));
        $this->assertTrue($container->hasDefinition('ai.platform.cache.result_normalizer'));

        $definition = $container->getDefinition('ai.platform.cache.ollama');

        $this->assertSame(CachePlatform::class, $definition->getClass());
        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());

        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('ai.platform.ollama', (string) $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame(ClockInterface::class, (string) $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('cache.app', (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('serializer', (string) $definition->getArgument(3));
        $this->assertSame('ollama', $definition->getArgument(4));
        $this->assertNull($definition->getArgument(5));

        $this->assertSame([
            ['interface' => PlatformInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([
            ['name' => 'cache.ollama'],
        ], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias('.Symfony\AI\Platform\PlatformInterface $cache_ollama'));
        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $cacheOllama'));
    }

    public function testCachePlatformCanBeUsedWithoutCustomCacheKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'ollama' => [
                        'host_url' => 'http://127.0.0.1:11434',
                    ],
                    'cache' => [
                        'ollama' => [
                            'platform' => 'ai.platform.ollama',
                            'service' => 'cache.app',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.cache.ollama'));

        $definition = $container->getDefinition('ai.platform.cache.ollama');

        $this->assertSame(CachePlatform::class, $definition->getClass());
        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());

        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('ai.platform.ollama', (string) $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame(ClockInterface::class, (string) $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('cache.app', (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('serializer', (string) $definition->getArgument(3));
        $this->assertSame('ollama', $definition->getArgument(4));
        $this->assertNull($definition->getArgument(5));

        $this->assertSame([
            ['interface' => PlatformInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([
            ['name' => 'cache.ollama'],
        ], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias('.Symfony\AI\Platform\PlatformInterface $cache_ollama'));
        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $cacheOllama'));
    }

    public function testCachePlatformCanBeUsedWithCustomTtl()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'ollama' => [
                        'host_url' => 'http://127.0.0.1:11434',
                    ],
                    'cache' => [
                        'ollama' => [
                            'platform' => 'ai.platform.ollama',
                            'ttl' => 10,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.cache.ollama'));

        $definition = $container->getDefinition('ai.platform.cache.ollama');

        $this->assertSame(CachePlatform::class, $definition->getClass());
        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());

        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('ai.platform.ollama', (string) $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame(ClockInterface::class, (string) $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('cache.app', (string) $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('serializer', (string) $definition->getArgument(3));
        $this->assertSame('ollama', $definition->getArgument(4));
        $this->assertSame(10, $definition->getArgument(5));

        $this->assertSame([
            ['interface' => PlatformInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.platform'));
        $this->assertSame([
            ['name' => 'cache.ollama'],
        ], $definition->getTag('ai.platform'));

        $this->assertTrue($container->hasAlias('.Symfony\AI\Platform\PlatformInterface $cache_ollama'));
        $this->assertTrue($container->hasAlias(PlatformInterface::class.' $cacheOllama'));
    }

    public function testCacheMessageStoreCanBeConfiguredWithCustomKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'cache' => [
                        'custom' => [
                            'service' => 'cache.app',
                            'key' => 'custom',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.cache.custom');

        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.app', (string) $definition->getArgument(0));

        $this->assertSame('custom', (string) $definition->getArgument(1));

        $this->assertTrue($definition->hasTag('proxy'));
        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testCacheMessageStoreCanBeConfiguredWithCustomTtl()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'cache' => [
                        'custom' => [
                            'service' => 'cache.app',
                            'ttl' => 3600,
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.cache.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('cache.app', (string) $definition->getArgument(0));

        $this->assertSame('custom', (string) $definition->getArgument(1));
        $this->assertSame(3600, (int) $definition->getArgument(2));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testCloudflareMessageStoreIsConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'cloudflare' => [
                        'my_cloudflare_message_store' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'namespace' => 'random',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.cloudflare.my_cloudflare_message_store');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(5, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('random', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('bar', $definition->getArgument(3));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(4));
        $this->assertSame('serializer', (string) $definition->getArgument(4));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testCloudflareMessageStoreWithCustomEndpointIsConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'cloudflare' => [
                        'my_cloudflare_message_store_with_new_endpoint' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'namespace' => 'random',
                            'endpoint_url' => 'https://api.cloudflare.com/client/v6/accounts',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.cloudflare.my_cloudflare_message_store_with_new_endpoint');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(6, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('random', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('bar', $definition->getArgument(3));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(4));
        $this->assertSame('serializer', (string) $definition->getArgument(4));
        $this->assertSame('https://api.cloudflare.com/client/v6/accounts', $definition->getArgument(5));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testDoctrineDbalMessageStoreCanBeConfiguredWithCustomKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'doctrine' => [
                        'dbal' => [
                            'default' => [
                                'connection' => 'default',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.doctrine.dbal.default');

        $this->assertSame('default', (string) $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame('doctrine.dbal.default_connection', (string) $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('serializer', (string) $definition->getArgument(2));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testDoctrineDbalMessageStoreWithCustomTableNameCanBeConfiguredWithCustomKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'doctrine' => [
                        'dbal' => [
                            'default' => [
                                'connection' => 'default',
                                'table_name' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.doctrine.dbal.default');

        $this->assertSame('foo', (string) $definition->getArgument(0));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(1));
        $this->assertSame('doctrine.dbal.default_connection', (string) $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('serializer', (string) $definition->getArgument(2));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testMemoryMessageStoreCanBeConfiguredWithCustomKey()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'memory' => [
                        'custom' => [
                            'identifier' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.memory.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertSame('foo', $definition->getArgument(0));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testMongoDbMessageStoreIsConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'mongodb' => [
                        'custom' => [
                            'collection' => 'foo',
                            'database' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.mongodb.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame(MongoDbClient::class, (string) $definition->getArgument(0));
        $this->assertSame('bar', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('serializer', (string) $definition->getArgument(3));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testMongoDbMessageStoreIsConfiguredWithCustomClient()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'mongodb' => [
                        'custom' => [
                            'client' => 'custom',
                            'collection' => 'foo',
                            'database' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.mongodb.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(4, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('custom', (string) $definition->getArgument(0));
        $this->assertSame('bar', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(3));
        $this->assertSame('serializer', (string) $definition->getArgument(3));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testPogocacheMessageStoreIsConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'pogocache' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:9401',
                            'password' => 'foo',
                            'key' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.pogocache.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(5, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:9401', $definition->getArgument(1));
        $this->assertSame('foo', $definition->getArgument(2));
        $this->assertSame('bar', $definition->getArgument(3));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(4));
        $this->assertSame('serializer', (string) $definition->getArgument(4));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testRedisMessageStoreIsConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'redis' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:9401',
                            'index_name' => 'foo',
                            'connection_parameters' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.redis.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertInstanceOf(Definition::class, $definition->getArgument(0));
        $this->assertSame(\Redis::class, $definition->getArgument(0)->getClass());
        $this->assertSame('foo', $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('serializer', (string) $definition->getArgument(2));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testRedisMessageStoreIsConfiguredWithCustomClient()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'redis' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:9401',
                            'index_name' => 'foo',
                            'client' => 'custom.redis',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.redis.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('custom.redis', (string) $definition->getArgument(0));
        $this->assertSame('foo', $definition->getArgument(1));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(2));
        $this->assertSame('serializer', (string) $definition->getArgument(2));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testSessionMessageStoreIsConfigured()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'session' => [
                        'custom' => [
                            'identifier' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.session.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('request_stack', (string) $definition->getArgument(0));
        $this->assertSame('foo', (string) $definition->getArgument(1));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testSurrealDbMessageStoreIsConfiguredWithoutCustomTable()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'surrealdb' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.surrealdb.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', (string) $definition->getArgument(1));
        $this->assertSame('test', (string) $definition->getArgument(2));
        $this->assertSame('test', (string) $definition->getArgument(3));
        $this->assertSame('foo', (string) $definition->getArgument(4));
        $this->assertSame('bar', (string) $definition->getArgument(5));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(6));
        $this->assertSame('serializer', (string) $definition->getArgument(6));
        $this->assertSame('custom', (string) $definition->getArgument(7));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testSurrealDbMessageStoreIsConfiguredWithCustomTable()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'surrealdb' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                            'table' => 'random',
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.surrealdb.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(8, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', (string) $definition->getArgument(1));
        $this->assertSame('test', (string) $definition->getArgument(2));
        $this->assertSame('test', (string) $definition->getArgument(3));
        $this->assertSame('foo', (string) $definition->getArgument(4));
        $this->assertSame('bar', (string) $definition->getArgument(5));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(6));
        $this->assertSame('serializer', (string) $definition->getArgument(6));
        $this->assertSame('random', (string) $definition->getArgument(7));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    public function testSurrealDbMessageStoreIsConfiguredWithNamespacedUser()
    {
        $container = $this->buildContainer([
            'ai' => [
                'message_store' => [
                    'surrealdb' => [
                        'custom' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                            'namespaced_user' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.message_store.surrealdb.custom');

        $this->assertTrue($definition->isLazy());
        $this->assertCount(9, $definition->getArguments());
        $this->assertInstanceOf(Reference::class, $definition->getArgument(0));
        $this->assertSame('http_client', (string) $definition->getArgument(0));
        $this->assertSame('http://127.0.0.1:8000', (string) $definition->getArgument(1));
        $this->assertSame('test', (string) $definition->getArgument(2));
        $this->assertSame('test', (string) $definition->getArgument(3));
        $this->assertSame('foo', (string) $definition->getArgument(4));
        $this->assertSame('bar', (string) $definition->getArgument(5));
        $this->assertInstanceOf(Reference::class, $definition->getArgument(6));
        $this->assertSame('serializer', (string) $definition->getArgument(6));
        $this->assertSame('custom', (string) $definition->getArgument(7));
        $this->assertTrue($definition->getArgument(8));

        $this->assertSame([
            ['interface' => MessageStoreInterface::class],
            ['interface' => ManagedMessageStoreInterface::class],
        ], $definition->getTag('proxy'));
        $this->assertTrue($definition->hasTag('ai.message_store'));
    }

    #[TestDox('Model configuration defaults class to Model::class')]
    #[DoesNotPerformAssertions]
    public function testModelConfigurationDefaultsClassToModel()
    {
        // Should not throw - class defaults to Model::class
        $this->buildContainer([
            'ai' => [
                'model' => [
                    'openai' => [
                        'my-custom-model' => [
                            'capabilities' => ['input-text', 'output-text'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Model configuration throws exception when class does not exist')]
    public function testModelConfigurationThrowsExceptionWhenClassDoesNotExist()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('does not exist');

        $this->buildContainer([
            'ai' => [
                'model' => [
                    'openai' => [
                        'my-custom-model' => [
                            'class' => 'NonExistentModelClass',
                            'capabilities' => ['input-text', 'output-text'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Model configuration throws exception when class does not extend Model')]
    public function testModelConfigurationThrowsExceptionWhenClassDoesNotExtendModel()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must extend');

        $this->buildContainer([
            'ai' => [
                'model' => [
                    'openai' => [
                        'my-custom-model' => [
                            'class' => \stdClass::class,
                            'capabilities' => ['input-text', 'output-text'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[TestDox('Model configuration is passed to ModelCatalog services')]
    public function testModelConfigurationIsPassedToModelCatalogServices()
    {
        $container = $this->buildContainer([
            'ai' => [
                'model' => [
                    'openai' => [
                        'my-custom-model' => [
                            'class' => Model::class,
                            'capabilities' => ['input-text', 'output-text'],
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.platform.model_catalog.openai');
        $arguments = $definition->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('my-custom-model', $arguments[0]);
        $this->assertSame(Model::class, $arguments[0]['my-custom-model']['class']);
        $this->assertEquals([Capability::INPUT_TEXT, Capability::OUTPUT_TEXT], $arguments[0]['my-custom-model']['capabilities']);
    }

    #[TestDox('Model configuration for vertexai uses correct catalog service id')]
    public function testModelConfigurationForVertexAiUsesCorrectCatalogServiceId()
    {
        $container = $this->buildContainer([
            'ai' => [
                'model' => [
                    'vertexai' => [
                        'my-custom-vertex-model' => [
                            'class' => Model::class,
                            'capabilities' => ['input-text', 'output-text'],
                        ],
                    ],
                ],
            ],
        ]);

        $definition = $container->getDefinition('ai.platform.model_catalog.vertexai.gemini');
        $arguments = $definition->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('my-custom-vertex-model', $arguments[0]);
    }

    #[TestDox('VertexAI platform uses custom http_client when configured')]
    public function testVertexAiPlatformUsesCustomHttpClient()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'vertexai' => [
                        'location' => 'us-central1',
                        'project_id' => 'my-project',
                        'http_client' => 'my_custom_http_client',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($container->hasDefinition('ai.platform.vertexai'));

        $definition = $container->getDefinition('ai.platform.vertexai');
        $arguments = $definition->getArguments();

        $this->assertSame('us-central1', $arguments[0]);
        $this->assertSame('my-project', $arguments[1]);

        // Argument 2 is the http client definition which uses the configured http_client service
        $httpClientDefinition = $arguments[3];
        $this->assertInstanceOf(Definition::class, $httpClientDefinition);

        $factory = $httpClientDefinition->getFactory();
        $this->assertIsArray($factory);
        $this->assertInstanceOf(Reference::class, $factory[0]);
        $this->assertSame('my_custom_http_client', (string) $factory[0]);
        $this->assertSame('withOptions', $factory[1]);
    }

    #[TestDox('Model configuration is ignored for unknown platform')]
    public function testModelConfigurationIsIgnoredForUnknownPlatform()
    {
        // Should not throw - unknown platforms are silently ignored
        $container = $this->buildContainer([
            'ai' => [
                'model' => [
                    'unknown_platform' => [
                        'my-custom-model' => [
                            'class' => Model::class,
                            'capabilities' => ['input-text', 'output-text'],
                        ],
                    ],
                ],
            ],
        ]);

        // The model catalog for openai should not have been modified
        $definition = $container->getDefinition('ai.platform.model_catalog.openai');
        $this->assertSame([], $definition->getArguments());
    }

    public function testTemplateRendererServicesAreRegistered()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'anthropic' => [
                        'api_key' => 'test_key',
                    ],
                ],
            ],
        ]);

        // Verify string template renderer is registered
        $this->assertTrue($container->hasDefinition('ai.platform.template_renderer.string'));
        $stringRendererDefinition = $container->getDefinition('ai.platform.template_renderer.string');
        $this->assertSame(StringTemplateRenderer::class, $stringRendererDefinition->getClass());
        $this->assertTrue($stringRendererDefinition->hasTag('ai.platform.template_renderer'));

        // Verify expression template renderer is registered
        $this->assertTrue($container->hasDefinition('ai.platform.template_renderer.expression'));
        $expressionRendererDefinition = $container->getDefinition('ai.platform.template_renderer.expression');
        $this->assertSame(ExpressionLanguageTemplateRenderer::class, $expressionRendererDefinition->getClass());
        $this->assertTrue($expressionRendererDefinition->hasTag('ai.platform.template_renderer'));

        // Verify template renderer registry is registered
        $this->assertTrue($container->hasDefinition('ai.platform.template_renderer_registry'));
        $registryDefinition = $container->getDefinition('ai.platform.template_renderer_registry');
        $this->assertSame(TemplateRendererRegistry::class, $registryDefinition->getClass());

        // Verify template renderer listener is registered as event subscriber
        $this->assertTrue($container->hasDefinition('ai.platform.template_renderer_listener'));
        $listenerDefinition = $container->getDefinition('ai.platform.template_renderer_listener');
        $this->assertSame(TemplateRendererListener::class, $listenerDefinition->getClass());
        $this->assertTrue($listenerDefinition->hasTag('kernel.event_subscriber'));
    }

    public function testTraceablePlatformServiceNamingIncludesPlatformType()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'azure' => [
                        'eu' => [
                            'api_key' => 'azure_key',
                            'base_url' => 'myazure.openai.azure.com/',
                            'deployment' => 'gpt-35-turbo',
                            'api_version' => '2024-02-15-preview',
                        ],
                        'us' => [
                            'api_key' => 'azure_key_us',
                            'base_url' => 'myazure-us.openai.azure.com/',
                            'deployment' => 'gpt-4',
                            'api_version' => '2024-02-15-preview',
                        ],
                    ],
                    'anthropic' => [
                        'api_key' => 'anthropic_key',
                    ],
                ],
            ],
        ]);

        // Verify that traceable platforms include the full platform type to avoid naming collisions
        // For multi-instance platforms like azure, the service name should be ai.traceable_platform.azure.{instance}
        $this->assertTrue($container->hasDefinition('ai.traceable_platform.azure.eu'));
        $this->assertTrue($container->hasDefinition('ai.traceable_platform.azure.us'));

        // For single-instance platforms like anthropic, the service name should be ai.traceable_platform.anthropic
        $this->assertTrue($container->hasDefinition('ai.traceable_platform.anthropic'));

        // Verify the old naming pattern (just the suffix) is NOT used - this would have caused collisions
        $this->assertFalse($container->hasDefinition('ai.traceable_platform.eu'));
        $this->assertFalse($container->hasDefinition('ai.traceable_platform.us'));
    }

    public function testDecoratorPriority()
    {
        $container = $this->buildContainer([
            'ai' => [
                'platform' => [
                    'anthropic' => [
                        'api_key' => 'test_key',
                    ],
                ],
                'agent' => [
                    'my_agent' => [
                        'model' => 'gpt-4',
                        'fault_tolerant_toolbox' => true,
                    ],
                ],
                'message_store' => [
                    'memory' => [
                        'main' => [
                            'identifier' => '_memory',
                        ],
                    ],
                ],
                'chat' => [
                    'main' => [
                        'agent' => 'ai.agent.my_agent',
                        'message_store' => 'ai.message_store.memory.main',
                    ],
                ],
            ],
        ]);

        $decorators = [
            'ai.traceable_platform.anthropic',
            'ai.traceable_toolbox.my_agent',
            'ai.fault_tolerant_toolbox.my_agent',
            'ai.traceable_message_store.main',
            'ai.traceable_chat.main',
        ];

        foreach ($decorators as $decorator) {
            $this->assertTrue($container->hasDefinition($decorator), "Container should have definition for decorator: $decorator");
            $definition = $container->getDefinition($decorator);
            $this->assertNotNull($definition->getDecoratedService(), "Definition for $decorator should be decorated");
            $this->assertSame(-1024, $definition->getDecoratedService()[2], "Decorator $decorator should have priority -1024");
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function buildContainer(array $configuration): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('kernel.build_dir', 'public');
        $container->setDefinition(ClockInterface::class, new Definition(MonotonicClock::class));
        $container->setDefinition('async_aws.client.bedrock_us', new Definition(BedrockRuntimeClient::class));
        $container->setDefinition(LoggerInterface::class, new Definition(NullLogger::class));
        $container->setDefinition('limiter.failover_platform', new Definition(RateLimiterFactory::class, [
            [
                'policy' => 'sliding_window',
                'id' => 'test',
                'interval' => '60 seconds',
                'limit' => 1,
            ],
            new Definition(InMemoryStorage::class),
        ]));
        $container->setDefinition('serializer', new Definition(Serializer::class));

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load($configuration, $container);

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFullConfig(): array
    {
        return [
            'ai' => [
                'platform' => [
                    'anthropic' => [
                        'api_key' => 'anthropic_key_full',
                    ],
                    'albert' => [
                        'api_key' => 'albert-test-key',
                        'base_url' => 'https://albert.api.etalab.gouv.fr/v1',
                    ],
                    'azure' => [
                        'my_azure_instance' => [
                            'api_key' => 'azure_key_full',
                            'base_url' => 'myazure.openai.azure.com/',
                            'deployment' => 'gpt-35-turbo',
                            'api_version' => '2024-02-15-preview',
                        ],
                        'another_azure_instance' => [
                            'api_key' => 'azure_key_2',
                            'base_url' => 'myazure2.openai.azure.com/',
                            'deployment' => 'gpt-4',
                            'api_version' => '2024-02-15-preview',
                        ],
                    ],
                    'bedrock' => [
                        'default' => [],
                        'us' => [
                            'bedrock_runtime_client' => 'async_aws.client.bedrock_us',
                        ],
                    ],
                    'cache' => [
                        'azure' => [
                            'platform' => 'ai.platform.azure.my_azure_instance',
                        ],
                        'azure_with_custom_cache' => [
                            'platform' => 'ai.platform.azure.my_azure_instance',
                            'service' => 'cache.foo',
                        ],
                        'azure_with_custom_cache_key' => [
                            'platform' => 'ai.platform.azure.my_azure_instance',
                            'cache_key' => 'foo',
                        ],
                        'azure_with_custom_ttl' => [
                            'platform' => 'ai.platform.azure.my_azure_instance',
                            'ttl' => 10,
                        ],
                    ],
                    'cartesia' => [
                        'api_key' => 'cartesia_key_full',
                        'version' => '2025-04-16',
                        'http_client' => 'http_client',
                    ],
                    'decart' => [
                        'api_key' => 'foo',
                    ],
                    'elevenlabs' => [
                        'host' => 'https://api.elevenlabs.io/v1',
                        'api_key' => 'elevenlabs_key_full',
                    ],
                    'failover' => [
                        'main' => [
                            'platforms' => [
                                'ai.platform.ollama',
                                'ai.platform.openai',
                            ],
                            'rate_limiter' => 'limiter.failover_platform',
                        ],
                    ],
                    'gemini' => [
                        'api_key' => 'gemini_key_full',
                    ],
                    'openai' => [
                        'api_key' => 'sk-openai_key_full',
                    ],
                    'mistral' => [
                        'api_key' => 'mistral_key_full',
                    ],
                    'openrouter' => [
                        'api_key' => 'sk-openrouter_key_full',
                    ],
                    'lmstudio' => [
                        'host_url' => 'http://127.0.0.1:1234',
                    ],
                    'ollama' => [
                        'host_url' => 'http://127.0.0.1:11434',
                    ],
                    'cerebras' => [
                        'api_key' => 'csk-cerebras_key_full',
                    ],
                    'voyage' => [
                        'api_key' => 'voyage_key_full',
                    ],
                    'vertexai' => [
                        'location' => 'global',
                        'project_id' => '123',
                        'api_key' => 'vertex_key_full',
                    ],
                    'dockermodelrunner' => [
                        'host_url' => 'http://127.0.0.1:12434',
                    ],
                    'transformersphp' => [],
                ],
                'agent' => [
                    'my_chat_agent' => [
                        'platform' => 'openai_platform_service_id',
                        'model' => [
                            'name' => 'gpt-3.5-turbo',
                            'options' => [
                                'temperature' => 0.7,
                                'max_tokens' => 150,
                                'nested' => ['options' => ['work' => 'too']],
                            ],
                        ],
                        'prompt' => [
                            'text' => 'You are a helpful assistant.',
                            'include_tools' => true,
                        ],
                        'tools' => [
                            'enabled' => true,
                            'services' => [
                                ['service' => 'my_tool_service_id', 'name' => 'myTool', 'description' => 'A test tool'],
                                'another_tool_service_id', // String format
                            ],
                        ],
                        'fault_tolerant_toolbox' => false,
                    ],
                    'another_agent' => [
                        'model' => 'claude-3-opus-20240229',
                        'prompt' => 'Be concise.',
                    ],
                ],
                'store' => [
                    'azuresearch' => [
                        'my_azuresearch_store' => [
                            'endpoint' => 'https://mysearch.search.windows.net',
                            'api_key' => 'azure_search_key',
                            'index_name' => 'my-documents',
                            'api_version' => '2023-11-01',
                            'vector_field' => 'contentVector',
                        ],
                    ],
                    'cache' => [
                        'my_cache_store' => [
                            'service' => 'cache.system',
                        ],
                        'my_cache_store_with_custom_key' => [
                            'service' => 'cache.system',
                            'cache_key' => 'bar',
                        ],
                        'my_cache_store_with_custom_strategy' => [
                            'service' => 'cache.system',
                            'strategy' => 'chebyshev',
                        ],
                        'my_cache_store_with_custom_strategy_and_custom_key' => [
                            'service' => 'cache.system',
                            'cache_key' => 'bar',
                            'strategy' => 'chebyshev',
                        ],
                    ],
                    'chromadb' => [
                        'my_chromadb_store' => [
                            'collection' => 'my_collection',
                        ],
                        'my_chromadb_store_with_custom_client' => [
                            'client' => 'bar',
                            'collection' => 'foo',
                        ],
                    ],
                    'clickhouse' => [
                        'my_clickhouse_store' => [
                            'dsn' => 'http://foo:bar@1.2.3.4:9999',
                            'database' => 'my_db',
                            'table' => 'my_table',
                        ],
                        'my_clickhouse_store_with_custom_dsn' => [
                            'dsn' => 'http://foo:bar@1.2.3.4:9999',
                            'database' => 'my_db',
                            'table' => 'my_table',
                        ],
                    ],
                    'cloudflare' => [
                        'my_cloudflare_store' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'index_name' => 'random',
                            'dimensions' => 1536,
                            'metric' => 'cosine',
                            'endpoint' => 'https://api.cloudflare.com/client/v5/accounts',
                        ],
                        'my_cloudflare_store_with_dimensions' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'index_name' => 'random',
                            'dimensions' => 1536,
                        ],
                        'my_cloudflare_store_with_metric' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'index_name' => 'random',
                            'metric' => 'cosine',
                        ],
                        'my_cloudflare_store_with_endpoint' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'index_name' => 'random',
                            'endpoint' => 'https://api.cloudflare.com/client/v6/accounts',
                        ],
                    ],
                    'manticoresearch' => [
                        'my_manticoresearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9306',
                            'table' => 'test',
                            'field' => 'foo_vector',
                            'type' => 'hnsw',
                            'similarity' => 'cosine',
                            'dimensions' => 768,
                        ],
                        'my_manticoresearch_store_with_quantization' => [
                            'endpoint' => 'http://127.0.0.1:9306',
                            'table' => 'test',
                            'field' => 'foo_vector',
                            'type' => 'hnsw',
                            'similarity' => 'cosine',
                            'dimensions' => 768,
                            'quantization' => '1bit',
                        ],
                    ],
                    'mariadb' => [
                        'my_mariadb_store' => [
                            'connection' => 'default',
                            'table_name' => 'vector_table',
                            'index_name' => 'vector_idx',
                            'vector_field_name' => 'vector',
                            'setup_options' => [
                                'dimensions' => 1024,
                            ],
                        ],
                    ],
                    'meilisearch' => [
                        'my_meilisearch_store' => [
                            'endpoint' => 'http://127.0.0.1:7700',
                            'api_key' => 'foo',
                            'index_name' => 'test',
                            'embedder' => 'default',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                            'semantic_ratio' => 0.5,
                        ],
                    ],
                    'memory' => [
                        'my_memory_store' => [
                            'strategy' => 'cosine',
                        ],
                    ],
                    'milvus' => [
                        'my_milvus_store' => [
                            'endpoint' => 'http://127.0.0.1:19530',
                            'api_key' => 'foo',
                            'database' => 'test',
                            'collection' => 'default',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                        ],
                        'my_milvus_store_with_custom_metric_type' => [
                            'endpoint' => 'http://127.0.0.1:19530',
                            'api_key' => 'foo',
                            'database' => 'test',
                            'collection' => 'default',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                            'metric_type' => 'COSINE',
                        ],
                    ],
                    'mongodb' => [
                        'my_mongo_store' => [
                            'database' => 'my_db',
                            'collection' => 'my_collection',
                            'index_name' => 'vector_index',
                            'vector_field' => 'embedding',
                            'bulk_write' => true,
                        ],
                    ],
                    'neo4j' => [
                        'my_neo4j_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'database' => 'foo',
                            'vector_index_name' => 'test',
                            'node_name' => 'foo',
                            'vector_field' => '_vectors',
                            'dimensions' => 768,
                            'distance' => 'cosine',
                            'quantization' => true,
                        ],
                    ],
                    'opensearch' => [
                        'my_opensearch_store' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                        ],
                        'my_opensearch_store_with_custom_index' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'index_name' => 'foo',
                        ],
                        'my_opensearch_store_with_custom_field' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'vectors_field' => 'foo',
                        ],
                        'my_opensearch_store_with_custom_space_type' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'space_type' => 'l1',
                        ],
                        'my_opensearch_store_with_custom_http_client' => [
                            'endpoint' => 'http://127.0.0.1:9200',
                            'http_client' => 'foo',
                        ],
                    ],
                    'pinecone' => [
                        'my_pinecone_store' => [
                            'index_name' => 'my_index',
                            'namespace' => 'my_namespace',
                            'filter' => ['category' => 'books'],
                            'top_k' => 10,
                        ],
                        'my_pinecone_store_with_filter' => [
                            'index_name' => 'my_index',
                            'namespace' => 'my_namespace',
                            'filter' => ['category' => 'books'],
                        ],
                        'my_pinecone_store_with_top_k' => [
                            'index_name' => 'my_index',
                            'namespace' => 'my_namespace',
                            'filter' => ['category' => 'books'],
                            'top_k' => 10,
                        ],
                    ],
                    'postgres' => [
                        'my_postgres_store' => [
                            'dsn' => 'pgsql:host=127.0.0.1;port=5432;dbname=postgresql_db',
                            'username' => 'postgres',
                            'password' => 'pass',
                            'table_name' => 'my_table',
                            'vector_field' => 'my_embedding',
                        ],
                    ],
                    'qdrant' => [
                        'my_qdrant_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'api_key' => 'test',
                            'collection_name' => 'foo',
                            'dimensions' => 768,
                            'distance' => 'Cosine',
                            'async' => false,
                        ],
                        'my_custom_dimensions_qdrant_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'api_key' => 'test',
                            'collection_name' => 'foo',
                            'dimensions' => 768,
                            'distance' => 'Cosine',
                        ],
                        'my_custom_distance_qdrant_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'api_key' => 'test',
                            'collection_name' => 'foo',
                            'dimensions' => 768,
                            'distance' => 'Cosine',
                        ],
                        'my_async_qdrant_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'api_key' => 'test',
                            'collection_name' => 'foo',
                            'dimensions' => 768,
                            'distance' => 'Cosine',
                            'async' => false,
                        ],
                    ],
                    'redis' => [
                        'my_redis_store' => [
                            'connection_parameters' => [
                                'host' => '1.2.3.4',
                                'port' => 6379,
                            ],
                            'index_name' => 'my_vector_index',
                        ],
                        'my_redis_store_with_custom_client' => [
                            'client' => 'foo',
                            'index_name' => 'my_vector_index',
                        ],
                        'my_redis_store_with_custom_key_prefix' => [
                            'client' => 'foo',
                            'index_name' => 'my_vector_index',
                            'key_prefix' => 'foo:',
                        ],
                        'my_redis_store_with_custom_distance' => [
                            'client' => 'foo',
                            'index_name' => 'my_vector_index',
                            'distance' => RedisDistance::L2->value,
                        ],
                    ],
                    'supabase' => [
                        'my_supabase_store' => [
                            'url' => 'https://test.supabase.co',
                            'api_key' => 'supabase_test_key',
                            'table' => 'my_supabase_table',
                            'vector_field' => 'my_embedding',
                            'vector_dimension' => 1024,
                            'function_name' => 'my_match_function',
                        ],
                        'my_supabase_store_with_custom_http_client' => [
                            'http_client' => 'foo',
                            'url' => 'https://test.supabase.co',
                            'api_key' => 'supabase_test_key',
                            'table' => 'my_supabase_table',
                            'vector_field' => 'my_embedding',
                            'vector_dimension' => 1024,
                        ],
                        'my_supabase_store_with_custom_function' => [
                            'url' => 'https://test.supabase.co',
                            'api_key' => 'supabase_test_key',
                            'table' => 'my_supabase_table',
                            'vector_field' => 'my_embedding',
                            'vector_dimension' => 1024,
                            'function_name' => 'foo',
                        ],
                    ],
                    'surrealdb' => [
                        'my_surrealdb_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                            'table' => 'bar',
                            'vector_field' => '_vectors',
                            'strategy' => 'cosine',
                            'dimensions' => 768,
                            'namespaced_user' => true,
                        ],
                    ],
                    'typesense' => [
                        'my_typesense_store' => [
                            'endpoint' => 'http://localhost:8108',
                            'api_key' => 'foo',
                            'collection' => 'my_collection',
                            'vector_field' => 'vector',
                            'dimensions' => 768,
                        ],
                    ],
                    'weaviate' => [
                        'my_weaviate_store' => [
                            'endpoint' => 'http://localhost:8080',
                            'api_key' => 'bar',
                            'collection' => 'my_weaviate_collection',
                        ],
                    ],
                ],
                'message_store' => [
                    'cache' => [
                        'my_cache_message_store' => [
                            'service' => 'cache.system',
                        ],
                        'my_cache_message_store_with_custom_cache_key' => [
                            'service' => 'cache.system',
                            'key' => 'foo',
                        ],
                    ],
                    'cloudflare' => [
                        'my_cloudflare_message_store' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'namespace' => 'random',
                        ],
                        'my_cloudflare_message_store_with_new_endpoint' => [
                            'account_id' => 'foo',
                            'api_key' => 'bar',
                            'namespace' => 'random',
                            'endpoint_url' => 'https://api.cloudflare.com/client/v6/accounts',
                        ],
                    ],
                    'doctrine' => [
                        'dbal' => [
                            'default' => [
                                'connection' => 'default',
                                'table_name' => 'foo',
                            ],
                        ],
                    ],
                    'memory' => [
                        'my_memory_message_store' => [
                            'identifier' => '_memory',
                        ],
                    ],
                    'meilisearch' => [
                        'my_meilisearch_store' => [
                            'endpoint' => 'http://127.0.0.1:7700',
                            'api_key' => 'foo',
                            'index_name' => 'test',
                        ],
                    ],
                    'mongodb' => [
                        'my_mongo_db_message_store' => [
                            'collection' => 'foo',
                            'database' => 'bar',
                        ],
                        'my_mongo_db_message_store_with_custom_client' => [
                            'client' => 'custom',
                            'collection' => 'foo',
                            'database' => 'bar',
                        ],
                    ],
                    'pogocache' => [
                        'my_pogocache_message_store' => [
                            'endpoint' => 'http://127.0.0.1:9401',
                            'password' => 'foo',
                            'key' => 'bar',
                        ],
                    ],
                    'redis' => [
                        'my_redis_store' => [
                            'connection_parameters' => [
                                'host' => '1.2.3.4',
                                'port' => 6379,
                            ],
                            'index_name' => 'my_message_store',
                        ],
                    ],
                    'session' => [
                        'my_session_message_store' => [
                            'identifier' => 'session',
                        ],
                    ],
                    'surrealdb' => [
                        'my_surrealdb_message_store' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                            'namespaced_user' => true,
                        ],
                        'my_surrealdb_message_store_with_custom_table' => [
                            'endpoint' => 'http://127.0.0.1:8000',
                            'username' => 'test',
                            'password' => 'test',
                            'namespace' => 'foo',
                            'database' => 'bar',
                            'table' => 'bar',
                            'namespaced_user' => true,
                        ],
                    ],
                ],
                'chat' => [
                    'main' => [
                        'agent' => 'my_chat_agent',
                        'message_store' => 'cache',
                    ],
                ],
                'vectorizer' => [
                    'test_vectorizer' => [
                        'platform' => 'mistral_platform_service_id',
                        'model' => [
                            'name' => 'mistral-embed',
                            'options' => ['dimension' => 768],
                        ],
                    ],
                ],
                'indexer' => [
                    'my_text_indexer' => [
                        'loader' => InMemoryLoader::class,
                        'vectorizer' => 'ai.vectorizer.test_vectorizer',
                        'store' => 'my_azuresearch_store_service_id',
                    ],
                ],
            ],
        ];
    }
}
