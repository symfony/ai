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
use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\Gemini\PlatformFactory;
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
    connection: DriverManager::getConnection((new DsnParser())->parse(env('MARIADB_URI'))),
    tableName: 'my_table_gemini',
    indexName: 'my_index',
);

// create embeddings and documents
$documents = [];
foreach (Movies::all() as $i => $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

// initialize the table
$store->setup(['dimensions' => 768]);

// create embeddings for documents
$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client());
$model = 'gemini-embedding-exp-03-07?dimensions=768&task_type=SEMANTIC_SIMILARITY';
$vectorizer = new Vectorizer($platform, $model, logger());
$indexer = new Indexer(new InMemoryLoader($documents), $vectorizer, $store, logger: logger());
$indexer->index($documents);

$similaritySearch = new SimilaritySearch($vectorizer, $store);
$toolbox = new Toolbox([$similaritySearch], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gemini-2.5-flash-lite', [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
    Message::ofUser('Which movie fits the theme of the mafia?')
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
