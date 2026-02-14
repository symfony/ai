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

use MongoDB\Client as MongoDbClient;
use Symfony\AI\Store\Bridge\MongoDb\Store as MongoDbStore;
use Symfony\AI\Store\Bridge\SurrealDb\Store as SurrealDbStore;
use Symfony\AI\Store\Bridge\Typesense\Store as TypesenseStore;
use Symfony\AI\Store\Command\DropStoreCommand;
use Symfony\AI\Store\Command\SetupStoreCommand;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ServiceLocator;

$factories = [
    'mongodb' => static fn (): MongoDbStore => new MongoDbStore(
        client: new MongoDbClient(env('MONGODB_URI')),
        databaseName: 'my-database',
        collectionName: 'my-collection',
        indexName: 'my-index',
        vectorFieldName: 'vector',
    ),
    // 'pinecone' => static fn (): PineconeStore => new PineconeStore(
    //     new PineconeClient(env('PINECONE_API_KEY'), env('PINECONE_HOST')),
    //     'symfony',
    // ),
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
