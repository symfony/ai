<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Bridge\SimilaritySearch\SimilaritySearch;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Store\Bridge\Sqlite\Distance;
use Symfony\AI\Store\Bridge\Sqlite\VecStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Retriever;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

// Install the sqlite-vec extension first — from this directory, run:
//   curl -L https://github.com/asg017/sqlite-vec/releases/download/v0.1.9/install.sh | sh
// That drops a vec0.* loadable file next to this script. Override via SQLITE_VEC_PATH if needed.
$extensionPath = $_SERVER['SQLITE_VEC_PATH'] ?? __DIR__.'/vec0.'.(\PHP_OS_FAMILY === 'Darwin' ? 'dylib' : (\PHP_OS_FAMILY === 'Windows' ? 'dll' : 'so'));
if (!file_exists($extensionPath)) {
    echo 'The sqlite-vec extension was not found at "'.$extensionPath.'".'.\PHP_EOL;
    echo 'Install it via: curl -L https://github.com/asg017/sqlite-vec/releases/download/v0.1.9/install.sh | sh'.\PHP_EOL;
    exit(1);
}

if (!is_dir(__DIR__.'/.sqlite')) {
    mkdir(__DIR__.'/.sqlite', 0777, true);
}
$pdo = new Pdo\Sqlite('sqlite:'.__DIR__.'/.sqlite/vec-vectors.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->loadExtension($extensionPath);

$store = new VecStore($pdo, 'movies', Distance::Cosine, 1536);
$store->setup();

$documents = [];
foreach (Movies::all() as $i => $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());
$indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store, logger: logger()));
$indexer->index($documents);

$retriever = new Retriever($store, $vectorizer);
$similaritySearch = new SimilaritySearch($retriever);
$toolbox = new Toolbox([$similaritySearch], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gpt-5-mini', [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
    Message::ofUser('Which movie fits the theme of the mafia?')
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
