<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once dirname(__DIR__).'/bootstrap.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use MongoDB\Client as MongoDbClient;
use Symfony\AI\Store\Bridge\ClickHouse\Store as ClickHouseStore;
use Symfony\AI\Store\Bridge\Local\CacheStore;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Store\Bridge\Manticore\Store as ManticoreStore;
use Symfony\AI\Store\Bridge\MariaDb\Store as MariaDbStore;
use Symfony\AI\Store\Bridge\Meilisearch\Store as MeilisearchStore;
use Symfony\AI\Store\Bridge\Milvus\Store as MilvusStore;
use Symfony\AI\Store\Bridge\MongoDb\Store as MongoDbStore;
use Symfony\AI\Store\Bridge\Neo4j\Store as Neo4jStore;
use Symfony\AI\Store\Bridge\OpenSearch\Store as OpenSearchStore;
use Symfony\AI\Store\Bridge\Postgres\Store as PostgresStore;
use Symfony\AI\Store\Bridge\Qdrant\Store as QdrantStore;
use Symfony\AI\Store\Bridge\Redis\Store as RedisStore;
use Symfony\AI\Store\Bridge\SurrealDb\Store as SurrealDbStore;
use Symfony\AI\Store\Bridge\Typesense\Store as TypesenseStore;
use Symfony\AI\Store\Bridge\Weaviate\Store as WeaviateStore;
use Symfony\AI\Store\Command\DropStoreCommand;
use Symfony\AI\Store\Command\SetupStoreCommand;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpClient\HttpClient;

$factories = [
    'cache' => static fn (): CacheStore => new CacheStore(new ArrayAdapter(), cacheKey: 'symfony'),
    'clickhouse' => static fn (): ClickHouseStore => new ClickHouseStore(
        HttpClient::createForBaseUri(env('CLICKHOUSE_HOST')),
        env('CLICKHOUSE_DATABASE'),
        env('CLICKHOUSE_TABLE'),
    ),
    'manticore' => static fn (): ManticoreStore => new ManticoreStore(
        http_client(),
        env('MANTICORE_HOST'),
        'symfony',
        '_vectors',
    ),
    'mariadb' => static fn (): MariaDbStore => MariaDbStore::fromDbal(
        DriverManager::getConnection((new DsnParser())->parse(env('MARIADB_URI'))),
        'my_table_for_commands',
        'my_commands_index',
    ),
    'memory' => static fn (): InMemoryStore => new InMemoryStore(),
    'meilisearch' => static fn (): MeilisearchStore => new MeilisearchStore(
        http_client(),
        env('MEILISEARCH_HOST'),
        env('MEILISEARCH_API_KEY'),
        'symfony',
    ),
    'milvus' => static fn (): MilvusStore => new MilvusStore(
        http_client(),
        env('MILVUS_HOST'),
        env('MILVUS_API_KEY'),
        env('MILVUS_DATABASE'),
        'symfony',
    ),
    'mongodb' => static fn (): MongoDbStore => new MongoDbStore(
        client: new MongoDbClient(env('MONGODB_URI')),
        databaseName: 'my-database',
        collectionName: 'my-collection',
        indexName: 'my-index',
        vectorFieldName: 'vector',
    ),
    'neo4j' => static fn (): Neo4jStore => new Neo4jStore(
        httpClient: http_client(),
        endpointUrl: env('NEO4J_HOST'),
        username: env('NEO4J_USERNAME'),
        password: env('NEO4J_PASSWORD'),
        databaseName: env('NEO4J_DATABASE'),
        vectorIndexName: 'Commands',
        nodeName: 'symfony',
    ),
    'opensearch' => static fn (): OpenSearchStore => new OpenSearchStore(
        http_client(),
        env('OPENSEARCH_ENDPOINT'),
        'symfony',
    ),
    'postgres' => static fn (): PostgresStore => PostgresStore::fromDbal(
        DriverManager::getConnection((new DsnParser())->parse(env('POSTGRES_URI'))),
        'my_table',
    ),
    'qdrant' => static fn (): QdrantStore => new QdrantStore(
        http_client(),
        env('QDRANT_HOST'),
        env('QDRANT_SERVICE_API_KEY'),
        'symfony',
    ),
    'redis' => static fn (): RedisStore => new RedisStore(new Redis([
        'host' => env('REDIS_HOST'),
        'port' => 6379,
    ]), 'symfony'),
    'surrealdb' => static fn (): SurrealDbStore => new SurrealDbStore(
        httpClient: http_client(),
        endpointUrl: env('SURREALDB_HOST'),
        user: env('SURREALDB_USER'),
        password: env('SURREALDB_PASS'),
        namespace: 'default',
        database: 'symfony',
        table: 'symfony',
    ),
    'typesense' => static fn (): TypesenseStore => new TypesenseStore(
        http_client(),
        env('TYPESENSE_HOST'),
        env('TYPESENSE_API_KEY'),
        'symfony',
    ),
    'weaviate' => static fn (): WeaviateStore => new WeaviateStore(
        http_client(),
        env('WEAVIATE_HOST'),
        env('WEAVIATE_API_KEY'),
        'symfony',
    ),
];

$storesIds = array_keys($factories);

$application = new Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$application->addCommands([
    new SetupStoreCommand(new ServiceLocator($factories)),
    new DropStoreCommand(new ServiceLocator($factories)),
]);

$clock = new MonotonicClock();
$clock->sleep(10);

foreach ($storesIds as $store) {
    $setupOutputCode = $application->run(new ArrayInput([
        'command' => 'ai:store:setup',
        'store' => $store,
    ]), new ConsoleOutput());

    $dropOutputCode = $application->run(new ArrayInput([
        'command' => 'ai:store:drop',
        'store' => $store,
        '--force' => true,
    ]), new ConsoleOutput());
}
