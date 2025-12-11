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
use MongoDB\Client as MongoDbClient;
use Symfony\AI\Chat\Bridge\Cache\Store as CacheStore;
use Symfony\AI\Chat\Bridge\Doctrine\DoctrineDbalMessageStore;
use Symfony\AI\Chat\Bridge\HttpFoundation\SessionStore;
use Symfony\AI\Chat\Bridge\Meilisearch\MessageStore as MeilisearchMessageStore;
use Symfony\AI\Chat\Bridge\MongoDb\MessageStore as MongoDbMessageStore;
use Symfony\AI\Chat\Bridge\Pogocache\MessageStore as PogocacheMessageStore;
use Symfony\AI\Chat\Bridge\Redis\MessageStore as RedisMessageStore;
use Symfony\AI\Chat\Bridge\SurrealDb\MessageStore as SurrealDbMessageStore;
use Symfony\AI\Chat\Command\DropStoreCommand;
use Symfony\AI\Chat\Command\SetupStoreCommand;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

$factories = [
    'cache' => static fn (): CacheStore => new CacheStore(new ArrayAdapter(), cacheKey: 'symfony'),
    'doctrine' => static fn (): DoctrineDbalMessageStore => new DoctrineDbalMessageStore(
        'symfony',
        DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
    ),
    'meilisearch' => static fn (): MeilisearchMessageStore => new MeilisearchMessageStore(
        http_client(),
        env('MEILISEARCH_HOST'),
        env('MEILISEARCH_API_KEY'),
        new MonotonicClock(),
        'symfony',
    ),
    'memory' => static fn (): InMemoryStore => new InMemoryStore('symfony'),
    'mongodb' => static fn (): MongoDbMessageStore => new MongoDbMessageStore(
        new MongoDbClient(env('MONGODB_URI')),
        'chat',
        'symfony',
    ),
    'pogocache' => static fn (): PogocacheMessageStore => new PogocacheMessageStore(
        http_client(),
        env('POGOCACHE_HOST'),
        env('POGOCACHE_PASSWORD'),
        'symfony',
    ),
    'redis' => static fn (): RedisMessageStore => new RedisMessageStore(new Redis([
        'host' => env('REDIS_HOST'),
        'port' => 6379,
    ]), 'symfony', new Serializer([
        new ArrayDenormalizer(),
        new MessageNormalizer(),
    ], [
        new JsonEncoder(),
    ])),
    'session' => static function (): SessionStore {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new SessionStore($requestStack, 'symfony');
    },
    'surrealdb' => static fn (): SurrealDbMessageStore => new SurrealDbMessageStore(
        httpClient: http_client(),
        endpointUrl: env('SURREALDB_HOST'),
        user: env('SURREALDB_USER'),
        password: env('SURREALDB_PASS'),
        namespace: 'default',
        database: 'chat',
        table: 'chat',
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
        'command' => 'ai:message-store:setup',
        'store' => $store,
    ]), new ConsoleOutput());

    $dropOutputCode = $application->run(new ArrayInput([
        'command' => 'ai:message-store:drop',
        'store' => $store,
        '--force' => true,
    ]), new ConsoleOutput());
}
