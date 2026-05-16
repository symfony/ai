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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\AiBundle\AiBundle;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Serializer\Serializer;

/**
 * Tests that each bridge configuration compiles into a valid Symfony DI container.
 *
 * Unlike AiBundleTest which calls $extension->load() without compiling,
 * these tests call $container->compile() to exercise autowiring resolution,
 * compiler passes, alias validation, and lazy proxy generation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class BridgeConfigCompilationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('providePlatformConfigs')]
    #[TestDox('Platform bridge "$type" config compiles')]
    public function testPlatformConfigCompiles(string $type, array $config, string $expectedServiceId)
    {
        $container = $this->loadAndCompileContainer([
            'ai' => [
                'platform' => [$type => $config],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $this->assertTrue($container->has($expectedServiceId), \sprintf('Service "%s" should exist.', $expectedServiceId));
    }

    #[TestDox('Cache platform config compiles with referenced platform')]
    public function testCachePlatformConfigCompiles()
    {
        $container = $this->loadAndCompileContainer([
            'ai' => [
                'platform' => [
                    'openai' => ['api_key' => 'k'],
                    'cache' => [
                        'main' => [
                            'platform' => 'ai.platform.openai',
                        ],
                    ],
                ],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $this->assertTrue($container->has('ai.platform.cache.main'));
    }

    /**
     * Regression: keyed-by-name OpenAI-compatible bridges used to wire `null`
     * for the optional model_catalog, which TypeError'd on instantiation
     * because the factory parameter is non-nullable with an object default.
     *
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideKeyedOpenAiCompatibleConfigs')]
    #[TestDox('Platform bridge "$type" instantiates without explicit model_catalog')]
    public function testKeyedOpenAiCompatiblePlatformInstantiatesWithDefaultModelCatalog(string $type, array $config, string $expectedServiceId)
    {
        $container = $this->loadContainer([
            'ai' => [
                'platform' => [$type => $config],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $container->getDefinition($expectedServiceId)->setPublic(true)->setLazy(false);
        $container->getCompiler()->getPassConfig()->setRemovingPasses([]);
        $container->getCompiler()->getPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        $this->assertInstanceOf(\Symfony\AI\Platform\Platform::class, $container->get($expectedServiceId));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideKeyedOpenAiCompatibleConfigs(): iterable
    {
        yield 'generic' => ['generic', ['inst' => ['base_url' => 'http://localhost:8080']], 'ai.platform.generic.inst'];
        yield 'openresponses' => ['openresponses', ['inst' => ['base_url' => 'http://localhost:8080']], 'ai.platform.openresponses.inst'];
    }

    #[TestDox('Failover platform config compiles with referenced platforms')]
    public function testFailoverPlatformConfigCompiles()
    {
        $container = $this->loadAndCompileContainer([
            'ai' => [
                'platform' => [
                    'openai' => ['api_key' => 'k'],
                    'ollama' => [],
                    'failover' => [
                        'main' => [
                            'platforms' => ['ai.platform.openai', 'ai.platform.ollama'],
                            'rate_limiter' => 'limiter.failover_platform',
                        ],
                    ],
                ],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $this->assertTrue($container->has('ai.platform.failover.main'));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideStoreConfigs')]
    #[TestDox('Store bridge "$type" config compiles')]
    public function testStoreConfigCompiles(string $type, array $config, string $expectedServiceId)
    {
        $container = $this->loadAndCompileContainer([
            'ai' => [
                'store' => [$type => $config],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $this->assertTrue($container->has($expectedServiceId), \sprintf('Service "%s" should exist.', $expectedServiceId));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideMessageStoreConfigs')]
    #[TestDox('Message store bridge "$type" config compiles')]
    public function testMessageStoreConfigCompiles(string $type, array $config, string $expectedServiceId)
    {
        $container = $this->loadAndCompileContainer([
            'ai' => [
                'message_store' => [$type => $config],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $this->assertTrue($container->has($expectedServiceId), \sprintf('Service "%s" should exist.', $expectedServiceId));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('providePlatformConfigs')]
    #[TestDox('Platform bridge "$type" has valid service definition')]
    public function testPlatformServiceDefinition(string $type, array $config, string $expectedServiceId)
    {
        $container = $this->loadContainer([
            'ai' => [
                'platform' => [$type => $config],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $this->assertTrue($container->hasDefinition($expectedServiceId), \sprintf('Service "%s" should be defined.', $expectedServiceId));

        $definition = $container->getDefinition($expectedServiceId);

        // Verify tag exists with name attribute
        $this->assertTrue($definition->hasTag('ai.platform'), \sprintf('Service "%s" should have the "ai.platform" tag.', $expectedServiceId));
        $tags = $definition->getTag('ai.platform');
        $this->assertArrayHasKey('name', $tags[0], \sprintf('Tag "ai.platform" on service "%s" should have a "name" attribute.', $expectedServiceId));

        // Verify factory method exists and argument count matches
        $this->assertFactoryArgumentCount($definition, $expectedServiceId);

        // Verify all service references can be resolved
        $this->assertReferencesResolvable($definition, $container, $expectedServiceId);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideStoreConfigs')]
    #[TestDox('Store bridge "$type" has valid service definition')]
    public function testStoreServiceDefinition(string $type, array $config, string $expectedServiceId)
    {
        $container = $this->loadContainer([
            'ai' => [
                'store' => [$type => $config],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $this->assertTrue($container->hasDefinition($expectedServiceId), \sprintf('Service "%s" should be defined.', $expectedServiceId));

        $definition = $container->getDefinition($expectedServiceId);

        $this->assertTrue($definition->hasTag('ai.store'), \sprintf('Service "%s" should have the "ai.store" tag.', $expectedServiceId));
        $this->assertFactoryArgumentCount($definition, $expectedServiceId);
        $this->assertReferencesResolvable($definition, $container, $expectedServiceId);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideMessageStoreConfigs')]
    #[TestDox('Message store bridge "$type" has valid service definition')]
    public function testMessageStoreServiceDefinition(string $type, array $config, string $expectedServiceId)
    {
        $container = $this->loadContainer([
            'ai' => [
                'message_store' => [$type => $config],
                'agent' => ['test' => ['model' => 'test']],
            ],
        ]);

        $this->assertTrue($container->hasDefinition($expectedServiceId), \sprintf('Service "%s" should be defined.', $expectedServiceId));

        $definition = $container->getDefinition($expectedServiceId);

        $this->assertTrue($definition->hasTag('ai.message_store'), \sprintf('Service "%s" should have the "ai.message_store" tag.', $expectedServiceId));
        $this->assertReferencesResolvable($definition, $container, $expectedServiceId);
    }

    #[TestDox('All platform config files have corresponding test coverage')]
    public function testAllPlatformConfigFilesAreCovered()
    {
        $configFiles = $this->getConfigFileNames('platform');
        $providerKeys = array_keys(iterator_to_array(self::providePlatformConfigs()));
        $compoundPlatforms = ['cache', 'failover'];

        foreach ($configFiles as $file) {
            if (\in_array($file, $compoundPlatforms, true)) {
                continue;
            }

            $this->assertContains($file, $providerKeys, \sprintf(
                'Platform config file "%s.php" has no corresponding entry in providePlatformConfigs(). Add test coverage for this bridge.',
                $file,
            ));
        }
    }

    #[TestDox('All store config files have corresponding test coverage')]
    public function testAllStoreConfigFilesAreCovered()
    {
        $configFiles = $this->getConfigFileNames('store');
        $providerKeys = array_keys(iterator_to_array(self::provideStoreConfigs()));

        foreach ($configFiles as $file) {
            $this->assertContains($file, $providerKeys, \sprintf(
                'Store config file "%s.php" has no corresponding entry in provideStoreConfigs(). Add test coverage for this bridge.',
                $file,
            ));
        }
    }

    #[TestDox('All message store config files have corresponding test coverage')]
    public function testAllMessageStoreConfigFilesAreCovered()
    {
        $configFiles = $this->getConfigFileNames('message_store');
        $providerKeys = array_keys(iterator_to_array(self::provideMessageStoreConfigs()));

        foreach ($configFiles as $file) {
            $this->assertContains($file, $providerKeys, \sprintf(
                'Message store config file "%s.php" has no corresponding entry in provideMessageStoreConfigs(). Add test coverage for this bridge.',
                $file,
            ));
        }
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function providePlatformConfigs(): iterable
    {
        yield 'albert' => ['albert', ['api_key' => 'k', 'base_url' => 'https://albert.example.com'], 'ai.platform.albert'];
        yield 'amazeeai' => ['amazeeai', ['api_key' => 'k', 'base_url' => 'https://amazeeai.example.com'], 'ai.platform.amazeeai'];
        yield 'anthropic' => ['anthropic', ['api_key' => 'k'], 'ai.platform.anthropic'];
        yield 'azure' => ['azure', ['inst' => ['api_key' => 'k', 'base_url' => 'https://a.openai.azure.com/', 'deployment' => 'd', 'api_version' => 'v']], 'ai.platform.azure.inst'];
        yield 'bedrock' => ['bedrock', ['default' => []], 'ai.platform.bedrock.default'];
        yield 'cartesia' => ['cartesia', ['api_key' => 'k', 'version' => '2025-04-16'], 'ai.platform.cartesia'];
        yield 'cerebras' => ['cerebras', ['api_key' => 'k'], 'ai.platform.cerebras'];
        yield 'cohere' => ['cohere', ['api_key' => 'k'], 'ai.platform.cohere'];
        yield 'decart' => ['decart', ['api_key' => 'k'], 'ai.platform.decart'];
        yield 'deepseek' => ['deepseek', ['api_key' => 'k'], 'ai.platform.deepseek'];
        yield 'dockermodelrunner' => ['dockermodelrunner', ['host_url' => 'http://localhost:12434'], 'ai.platform.dockermodelrunner'];
        yield 'elevenlabs' => ['elevenlabs', ['api_key' => 'k'], 'ai.platform.elevenlabs'];
        yield 'gemini' => ['gemini', ['api_key' => 'k'], 'ai.platform.gemini'];
        yield 'generic' => ['generic', ['inst' => ['base_url' => 'http://localhost:8080']], 'ai.platform.generic.inst'];
        yield 'huggingface' => ['huggingface', ['api_key' => 'k'], 'ai.platform.huggingface'];
        yield 'inworld' => ['inworld', ['api_key' => 'k'], 'ai.platform.inworld'];
        yield 'lmstudio' => ['lmstudio', ['host_url' => 'http://localhost:1234'], 'ai.platform.lmstudio'];
        yield 'mistral' => ['mistral', ['api_key' => 'k'], 'ai.platform.mistral'];
        yield 'ollama' => ['ollama', [], 'ai.platform.ollama'];
        yield 'openai' => ['openai', ['api_key' => 'k'], 'ai.platform.openai'];
        yield 'openresponses' => ['openresponses', ['inst' => ['base_url' => 'http://localhost:8080']], 'ai.platform.openresponses.inst'];
        yield 'openrouter' => ['openrouter', ['api_key' => 'k'], 'ai.platform.openrouter'];
        yield 'ovh' => ['ovh', ['api_key' => 'k'], 'ai.platform.ovh'];
        yield 'perplexity' => ['perplexity', ['api_key' => 'k'], 'ai.platform.perplexity'];
        yield 'scaleway' => ['scaleway', ['api_key' => 'k'], 'ai.platform.scaleway'];
        yield 'transformersphp' => ['transformersphp', [], 'ai.platform.transformersphp'];
        yield 'vertexai' => ['vertexai', ['location' => 'global', 'project_id' => '123'], 'ai.platform.vertexai'];
        yield 'voyage' => ['voyage', ['api_key' => 'k'], 'ai.platform.voyage'];
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideStoreConfigs(): iterable
    {
        yield 'azuresearch' => ['azuresearch', ['default' => ['endpoint' => 'https://s.search.windows.net', 'api_key' => 'k', 'index_name' => 'idx', 'vector_field' => 'vec']], 'ai.store.azuresearch.default'];
        yield 'cache' => ['cache', ['default' => ['service' => 'cache.system']], 'ai.store.cache.default'];
        yield 'chromadb' => ['chromadb', ['default' => ['collection' => 'col']], 'ai.store.chromadb.default'];
        yield 'clickhouse' => ['clickhouse', ['default' => ['dsn' => 'http://localhost:9000', 'database' => 'db', 'table' => 'tbl']], 'ai.store.clickhouse.default'];
        yield 'cloudflare' => ['cloudflare', ['default' => ['account_id' => 'a', 'api_key' => 'k', 'index_name' => 'idx']], 'ai.store.cloudflare.default'];
        yield 'elasticsearch' => ['elasticsearch', ['default' => ['endpoint' => 'http://localhost:9200']], 'ai.store.elasticsearch.default'];
        yield 'manticoresearch' => ['manticoresearch', ['default' => ['endpoint' => 'http://localhost:9306', 'table' => 'tbl', 'field' => 'vec', 'type' => 'hnsw', 'similarity' => 'cosine', 'dimensions' => 768]], 'ai.store.manticoresearch.default'];
        yield 'mariadb' => ['mariadb', ['default' => ['connection' => 'default', 'table_name' => 'tbl', 'index_name' => 'idx', 'vector_field_name' => 'vec']], 'ai.store.mariadb.default'];
        yield 'meilisearch' => ['meilisearch', ['default' => ['endpoint' => 'http://localhost:7700', 'api_key' => 'k', 'index_name' => 'idx']], 'ai.store.meilisearch.default'];
        yield 'memory' => ['memory', ['default' => []], 'ai.store.memory.default'];
        yield 'milvus' => ['milvus', ['default' => ['endpoint' => 'http://localhost:19530', 'api_key' => 'k', 'database' => 'db', 'collection' => 'col']], 'ai.store.milvus.default'];
        yield 'mongodb' => ['mongodb', ['default' => ['database' => 'db', 'collection' => 'col', 'index_name' => 'idx', 'vector_field' => 'emb']], 'ai.store.mongodb.default'];
        yield 'neo4j' => ['neo4j', ['default' => ['endpoint' => 'http://localhost:7474', 'username' => 'u', 'password' => 'p', 'database' => 'db', 'vector_index_name' => 'idx', 'node_name' => 'n', 'dimensions' => 768]], 'ai.store.neo4j.default'];
        yield 'opensearch' => ['opensearch', ['default' => ['endpoint' => 'http://localhost:9200']], 'ai.store.opensearch.default'];
        yield 'pinecone' => ['pinecone', ['default' => ['index_name' => 'idx', 'namespace' => 'ns']], 'ai.store.pinecone.default'];
        yield 'postgres' => ['postgres', ['default' => ['dsn' => 'pgsql:host=localhost', 'username' => 'u', 'password' => 'p']], 'ai.store.postgres.default'];
        yield 'qdrant' => ['qdrant', ['default' => ['endpoint' => 'http://localhost:6333', 'api_key' => 'k', 'collection_name' => 'col', 'dimensions' => 768]], 'ai.store.qdrant.default'];
        yield 'redis' => ['redis', ['default' => ['connection_parameters' => ['host' => 'localhost', 'port' => 6379], 'index_name' => 'idx']], 'ai.store.redis.default'];
        yield 's3vectors' => ['s3vectors', ['default' => ['vector_bucket_name' => 'bucket']], 'ai.store.s3vectors.default'];
        yield 'sqlite' => ['sqlite', ['default' => ['dsn' => 'sqlite::memory:']], 'ai.store.sqlite.default'];
        yield 'supabase' => ['supabase', ['default' => ['url' => 'https://t.supabase.co', 'api_key' => 'k', 'table' => 'tbl', 'vector_field' => 'emb', 'vector_dimension' => 1024]], 'ai.store.supabase.default'];
        yield 'surrealdb' => ['surrealdb', ['default' => ['endpoint' => 'http://localhost:8000', 'username' => 'u', 'password' => 'p', 'namespace' => 'ns', 'database' => 'db', 'table' => 'tbl', 'dimensions' => 768]], 'ai.store.surrealdb.default'];
        yield 'typesense' => ['typesense', ['default' => ['endpoint' => 'http://localhost:8108', 'api_key' => 'k', 'collection' => 'col', 'dimensions' => 768]], 'ai.store.typesense.default'];
        yield 'vektor' => ['vektor', ['default' => []], 'ai.store.vektor.default'];
        yield 'weaviate' => ['weaviate', ['default' => ['endpoint' => 'http://localhost:8080', 'api_key' => 'k', 'collection' => 'col']], 'ai.store.weaviate.default'];
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideMessageStoreConfigs(): iterable
    {
        yield 'cache' => ['cache', ['default' => ['service' => 'cache.system']], 'ai.message_store.cache.default'];
        yield 'cloudflare' => ['cloudflare', ['default' => ['account_id' => 'a', 'api_key' => 'k', 'namespace' => 'ns']], 'ai.message_store.cloudflare.default'];
        yield 'doctrine' => ['doctrine', ['dbal' => ['default' => ['connection' => 'default']]], 'ai.message_store.doctrine.dbal.default'];
        yield 'meilisearch' => ['meilisearch', ['default' => ['endpoint' => 'http://localhost:7700', 'api_key' => 'k', 'index_name' => 'idx']], 'ai.message_store.meilisearch.default'];
        yield 'memory' => ['memory', ['default' => ['identifier' => 'mem']], 'ai.message_store.memory.default'];
        yield 'mongodb' => ['mongodb', ['default' => ['database' => 'db', 'collection' => 'col']], 'ai.message_store.mongodb.default'];
        yield 'pogocache' => ['pogocache', ['default' => ['endpoint' => 'http://localhost:9401', 'password' => 'p', 'key' => 'k']], 'ai.message_store.pogocache.default'];
        yield 'redis' => ['redis', ['default' => ['connection_parameters' => ['host' => 'localhost', 'port' => 6379], 'index_name' => 'idx']], 'ai.message_store.redis.default'];
        yield 'session' => ['session', ['default' => ['identifier' => 'sess']], 'ai.message_store.session.default'];
        yield 'surrealdb' => ['surrealdb', ['default' => ['endpoint' => 'http://localhost:8000', 'username' => 'u', 'password' => 'p', 'namespace' => 'ns', 'database' => 'db']], 'ai.message_store.surrealdb.default'];
    }

    private function assertFactoryArgumentCount(Definition $definition, string $serviceId): void
    {
        $factory = $definition->getFactory();

        if (!\is_array($factory) && !\is_string($factory)) {
            return;
        }

        if (\is_string($factory) && str_contains($factory, '::')) {
            [$class, $method] = explode('::', $factory, 2);
        } elseif (\is_array($factory)) {
            [$class, $method] = $factory;
        } else {
            return;
        }

        if (!\is_string($class) || !class_exists($class) || !method_exists($class, $method)) {
            $this->fail(\sprintf('Factory "%s::%s" for service "%s" does not exist.', $class, $method, $serviceId));
        }

        $reflectionMethod = new \ReflectionMethod($class, $method);
        $argumentCount = \count($definition->getArguments());

        $this->assertGreaterThanOrEqual(
            $reflectionMethod->getNumberOfRequiredParameters(),
            $argumentCount,
            \sprintf('Service "%s" has %d arguments but factory "%s::%s" requires at least %d.', $serviceId, $argumentCount, $class, $method, $reflectionMethod->getNumberOfRequiredParameters()),
        );

        $this->assertLessThanOrEqual(
            $reflectionMethod->getNumberOfParameters(),
            $argumentCount,
            \sprintf('Service "%s" has %d arguments but factory "%s::%s" accepts at most %d.', $serviceId, $argumentCount, $class, $method, $reflectionMethod->getNumberOfParameters()),
        );
    }

    private function assertReferencesResolvable(Definition $definition, ContainerBuilder $container, string $serviceId): void
    {
        foreach ($definition->getArguments() as $index => $argument) {
            if (!$argument instanceof Reference) {
                continue;
            }

            $refId = (string) $argument;
            $invalidBehavior = $argument->getInvalidBehavior();

            // Skip optional references (NULL_ON_INVALID_REFERENCE, IGNORE_ON_INVALID_REFERENCE)
            if (ContainerBuilder::EXCEPTION_ON_INVALID_REFERENCE !== $invalidBehavior) {
                continue;
            }

            $this->assertTrue(
                $container->has($refId),
                \sprintf('Service "%s" (argument %s) references "%s" which does not exist in the container.', $serviceId, $index, $refId),
            );
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function loadContainer(array $configuration): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        // Common mock services
        $container->setDefinition(ClockInterface::class, new Definition(MonotonicClock::class));
        $container->setDefinition(LoggerInterface::class, new Definition(NullLogger::class));
        $container->setDefinition('http_client', (new Definition(NativeHttpClient::class))->setPublic(true));
        $container->setDefinition('event_dispatcher', (new Definition(EventDispatcher::class))->setPublic(true));
        $container->setDefinition('serializer', new Definition(Serializer::class));
        $container->setDefinition('cache.system', new Definition(ArrayAdapter::class));
        $container->setDefinition('cache.app', new Definition(ArrayAdapter::class));

        // Bedrock client (needed when bedrock platform is configured)
        $container->setDefinition('async_aws.client.bedrock_runtime', new Definition(BedrockRuntimeClient::class));
        $container->setDefinition('async_aws.client.bedrock_us', new Definition(BedrockRuntimeClient::class));

        // Rate limiter for failover platform
        $container->setDefinition('limiter.failover_platform', new Definition(RateLimiterFactory::class, [
            ['policy' => 'sliding_window', 'id' => 'test', 'interval' => '60 seconds', 'limit' => 1],
            new Definition(InMemoryStorage::class),
        ]));

        // Doctrine connection (for stores/message stores that reference it)
        $container->register('doctrine.dbal.default_connection')->setSynthetic(true);

        // External services provided by FrameworkBundle or third-party packages
        $container->register('request_stack')->setSynthetic(true);
        $container->register('filesystem')->setSynthetic(true);
        $container->register('MongoDB\Client')->setSynthetic(true);
        $container->register('Codewithkyrian\ChromaDB\Client')->setSynthetic(true);
        $container->register('Probots\Pinecone\Client')->setSynthetic(true);

        $extension = (new AiBundle())->getContainerExtension();
        $extension->load($configuration, $container);

        return $container;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function loadAndCompileContainer(array $configuration): ContainerBuilder
    {
        $container = $this->loadContainer($configuration);

        // Register bundle compiler passes
        (new AiBundle())->build($container);

        // Remove the removing passes to avoid "service not found" errors for synthetic/external services
        // while still running optimization and merge passes that validate the config structure
        $container->getCompiler()->getPassConfig()->setRemovingPasses([]);
        $container->getCompiler()->getPassConfig()->setAfterRemovingPasses([]);

        $container->compile();

        return $container;
    }

    /**
     * @return list<string>
     */
    private function getConfigFileNames(string $directory): array
    {
        $configDir = \dirname(__DIR__, 2).'/config/'.$directory;
        $files = glob($configDir.'/*.php');

        return array_map(
            static fn (string $path): string => basename($path, '.php'),
            $files,
        );
    }
}
