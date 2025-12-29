<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Memory\EmbeddingProvider;
use Symfony\AI\Agent\Memory\MemoryInputProcessor;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Store\Bridge\MariaDb\Store;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

// initialize the store
$store = Store::fromDbal(
    connection: DriverManager::getConnection((new DsnParser())->parse($_ENV['MARIADB_URI'])),
    tableName: 'my_table_memory',
    indexName: 'my_index',
);

// our data
$pastConversationPieces = [
    ['role' => 'user', 'timestamp' => '2024-12-14 12:00:00', 'content' => 'My friends John and Emma are friends, too, are there hints why?'],
    ['role' => 'assistant', 'timestamp' => '2024-12-14 12:00:01', 'content' => 'Based on the found documents i would expect they are friends since childhood, this can give a deep bound!'],
    ['role' => 'user', 'timestamp' => '2024-12-14 12:02:02', 'content' => 'Yeah but how does this bound? I know John was once there with a wound dressing as Emma fell, could this be a hint?'],
    ['role' => 'assistant', 'timestamp' => '2024-12-14 12:02:03', 'content' => 'Yes, this could be a hint that they have been through difficult times together, which can strengthen their bond.'],
];

// create embeddings and documents
foreach ($pastConversationPieces as $i => $message) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Role: '.$message['role'].\PHP_EOL.'Timestamp: '.$message['timestamp'].\PHP_EOL.'Message: '.$message['content'],
        metadata: new Metadata($message),
    );
}

// initialize the table
$store->setup();

// create embeddings for documents as preparation of the chain memory
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, $embeddings = 'text-embedding-3-small');
$indexer = new Indexer(new InMemoryLoader($documents), $vectorizer, $store, logger: logger());
$indexer->index($documents);

// Execute a chat call that is utilizing the memory
$embeddingsModel = $platform->getModelCatalog()->getModel($embeddings);
$embeddingsMemory = new EmbeddingProvider($platform, $embeddingsModel, $store);
$memoryProcessor = new MemoryInputProcessor([$embeddingsMemory]);

$agent = new Agent($platform, 'gpt-4o-mini', [$memoryProcessor]);
$messages = new MessageBag(Message::ofUser('Have we discussed about my friend John in the past? If yes, what did we talk about?'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
