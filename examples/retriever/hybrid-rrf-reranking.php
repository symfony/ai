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
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Store\CombinedStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Event\PostQueryEvent;
use Symfony\AI\Store\EventListener\RerankerListener;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Reranker\Reranker;
use Symfony\AI\Store\Retriever;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo "=== Hybrid RRF + HuggingFace Reranking via PostQueryEvent ===\n\n";
echo "This example demonstrates CombinedStore with RRF, followed by\n";
echo "cross-encoder reranking via a PostQueryEvent listener.\n\n";

$store = new InMemoryStore();

$documents = [];
foreach (Movies::all() as $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

$platform = Factory::createPlatform(env('HUGGINGFACE_KEY'), httpClient: http_client());
$vectorizer = new Vectorizer($platform, 'BAAI/bge-small-en-v1.5?task=feature-extraction', logger());
$indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store, logger: logger()));
$indexer->index($documents);

$queryText = 'crime family mafia';
echo "Query: \"$queryText\"\n\n";

// 1. Hybrid RRF without reranking
echo "--- Hybrid RRF Results (no reranking) ---\n";
$combinedStore = new CombinedStore($store, $store, rrfK: 60);
$hybridRetriever = new Retriever($combinedStore, $vectorizer, logger: logger());

$results = iterator_to_array($hybridRetriever->retrieve($queryText));

foreach ($results as $i => $document) {
    echo sprintf(
        "  %d. %s (RRF Score: %.6f)\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
        $document->getScore() ?? 0.0,
    );
}
echo "\n";

// 2. Hybrid RRF + reranking via PostQueryEvent
echo "--- Hybrid RRF + HuggingFace Reranking Results ---\n";
$reranker = new Reranker($platform, 'BAAI/bge-reranker-base?task=text-ranking', logger());

$dispatcher = new EventDispatcher();
$dispatcher->addListener(PostQueryEvent::class, new RerankerListener($reranker, topK: 5));

$hybridWithReranker = new Retriever($combinedStore, $vectorizer, $dispatcher, logger: logger());

$rerankedResults = iterator_to_array($hybridWithReranker->retrieve($queryText));

foreach ($rerankedResults as $i => $document) {
    echo sprintf(
        "  %d. %s (Relevance: %.4f)\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
        $document->getScore() ?? 0.0,
    );
}
echo "\n";

echo "=== Summary ===\n";
echo "- CombinedStore merges vector and text results using RRF scoring\n";
echo "- RerankerListener on PostQueryEvent refines results using a cross-encoder model\n";
