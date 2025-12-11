<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Retriever;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$store = new InMemoryStore();

$documents = [];
foreach (Movies::all() as $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());

$indexer = new Indexer(new InMemoryLoader($documents), $vectorizer, $store, logger: logger());
$indexer->index();

$retriever = new Retriever($vectorizer, $store, logger());

echo "Searching for movies about 'crime family mafia'\n";
echo "================================================\n\n";

$results = $retriever->retrieve('crime family mafia');

foreach ($results as $i => $document) {
    $title = $document->metadata['title'];
    $director = $document->metadata['director'];
    $score = $document->score;

    echo sprintf("%d. %s (Director: %s)\n", $i + 1, $title, $director);
    echo sprintf("   Score: %.4f\n\n", $score ?? 0);
}
