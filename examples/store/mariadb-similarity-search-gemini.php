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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\Google\Embeddings;
use Symfony\AI\Platform\Bridge\Google\Embeddings\TaskType;
use Symfony\AI\Platform\Bridge\Google\Gemini;
use Symfony\AI\Platform\Bridge\Google\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Store\Bridge\MariaDB\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

// initialize the store
$store = Store::fromDbal(
    connection: DriverManager::getConnection((new DsnParser())->parse(env('MARIADB_URI'))),
    tableName: 'my_table',
    indexName: 'my_index',
);

// create embeddings and documents
foreach (Movies::all() as $i => $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

// initialize the table
$store->initialize(['dimensions' => 768]);

// create embeddings for documents
$platform = PlatformFactory::create(env('GEMINI_API_KEY'), http_client());
$embeddings = new Embeddings(options: ['dimensions' => 768, 'task_type' => TaskType::SemanticSimilarity]);
$vectorizer = new Vectorizer($platform, $embeddings);
$indexer = new Indexer($vectorizer, $store, logger());
$indexer->index($documents);

$model = new Gemini(Gemini::GEMINI_2_FLASH_LITE);

$similaritySearch = new SimilaritySearch($platform, $embeddings, $store);
$toolbox = new Toolbox([$similaritySearch], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor], logger());

$messages = new MessageBag(
    Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
    Message::ofUser('Which movie fits the theme of the mafia?')
);
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
