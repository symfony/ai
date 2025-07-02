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
use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAI\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Store\Bridge\MariaDB\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Indexer;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->loadEnv(dirname(__DIR__, 2).'/.env');

if (!$_ENV['OPENAI_API_KEY'] || !$_ENV['MARIADB_URI']) {
    echo 'Please set OPENAI_API_KEY and MARIADB_URI environment variables.'.\PHP_EOL;
    exit(1);
}

// initialize the store
$store = Store::fromDbal(
    connection: DriverManager::getConnection((new DsnParser())->parse($_ENV['MARIADB_URI'])),
    tableName: 'my_table',
    indexName: 'my_index',
    vectorFieldName: 'embedding',
);

// our data
$movies = [
    ['title' => 'Inception', 'description' => 'A skilled thief is given a chance at redemption if he can successfully perform inception, the act of planting an idea in someone\'s subconscious.', 'director' => 'Christopher Nolan'],
    ['title' => 'The Matrix', 'description' => 'A hacker discovers the world he lives in is a simulated reality and joins a rebellion to overthrow its controllers.', 'director' => 'The Wachowskis'],
    ['title' => 'The Godfather', 'description' => 'The aging patriarch of an organized crime dynasty transfers control of his empire to his reluctant son.', 'director' => 'Francis Ford Coppola'],
];

// create embeddings and documents
foreach ($movies as $i => $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

// initialize the table
$store->initialize();

// create embeddings for documents
$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
$indexer = new Indexer($platform, $embeddings = new Embeddings(), $store);
$indexer->index($documents);

$model = new GPT(GPT::GPT_4O_MINI);

$similaritySearch = new SimilaritySearch($platform, $embeddings, $store);
$toolbox = Toolbox::create($similaritySearch);
$processor = new ChainProcessor($toolbox);
$chain = new Chain($platform, $model, [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
    Message::ofUser('Which movie fits the theme of the mafia?')
);
$response = $chain->call($messages);

echo $response->getContent().\PHP_EOL;
