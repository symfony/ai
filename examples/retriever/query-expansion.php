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
use Symfony\AI\Store\Event\PreQueryEvent;
use Symfony\AI\Store\EventListener\RerankerListener;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Reranker\Reranker;
use Symfony\AI\Store\Retriever;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo "=== Query Expansion via PreQueryEvent + Reranking ===\n\n";
echo "This example demonstrates using PreQueryEvent to expand a vague query\n";
echo "with related terms, and PostQueryEvent to rerank with a cross-encoder.\n\n";

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

$combinedStore = new CombinedStore($store, $store, rrfK: 60);
$reranker = new Reranker($platform, 'BAAI/bge-reranker-base?task=text-ranking', logger());

$vagueQuery = 'gangster';
echo "Original query: \"$vagueQuery\"\n\n";

// 1. Without query expansion
echo "--- Without Query Expansion ---\n";
$retriever = new Retriever($combinedStore, $vectorizer, logger: logger());
$results = iterator_to_array($retriever->retrieve($vagueQuery));

foreach (array_slice($results, 0, 5) as $i => $document) {
    echo sprintf(
        "  %d. %s (Score: %.6f)\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
        $document->getScore() ?? 0.0,
    );
}
echo "\n";

// 2. With query expansion via PreQueryEvent + reranking via PostQueryEvent
echo "--- With Query Expansion + Reranking ---\n";
$dispatcher = new EventDispatcher();

// Pre-retrieval: expand the query with related terms
$dispatcher->addListener(PreQueryEvent::class, static function (PreQueryEvent $event) {
    $expanded = $event->getQuery().' crime family mafia organized crime';
    echo "Enhanced query: \"$expanded\"\n";
    $event->setQuery($expanded);
});

// Post-retrieval: rerank with cross-encoder
$dispatcher->addListener(PostQueryEvent::class, new RerankerListener($reranker, topK: 5));

$expandedRetriever = new Retriever($combinedStore, $vectorizer, $dispatcher, logger: logger());
$results = iterator_to_array($expandedRetriever->retrieve($vagueQuery));

foreach ($results as $i => $document) {
    echo sprintf(
        "  %d. %s (Relevance: %.4f)\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
        $document->getScore() ?? 0.0,
    );
}
echo "\n";

echo "=== Summary ===\n";
echo "- PreQueryEvent allows modifying the query before vectorization and store lookup\n";
echo "- Use cases: query expansion, spelling correction, synonym injection\n";
echo "- Combined with PostQueryEvent reranking for a full retrieval pipeline\n";
