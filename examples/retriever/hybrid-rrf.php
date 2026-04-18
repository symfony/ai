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
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Retriever;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo "=== Hybrid Retrieval with CombinedStore + RRF ===\n\n";
echo "This example demonstrates combining vector (semantic) and text (keyword)\n";
echo "retrieval using CombinedStore with Reciprocal Rank Fusion (RRF).\n\n";

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

// 1. Vector-only results
echo "--- Vector-Only Results (semantic similarity) ---\n";
$vectorRetriever = new Retriever($store, $vectorizer, logger: logger());
$results = iterator_to_array($vectorRetriever->retrieve($queryText));

foreach ($results as $i => $document) {
    echo sprintf(
        "  %d. %s (Score: %.4f)\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
        $document->getScore() ?? 0.0,
    );
}
echo "\n";

// 2. Text-only results
echo "--- Text-Only Results (keyword matching) ---\n";
$textRetriever = new Retriever($store, logger: logger());
$results = iterator_to_array($textRetriever->retrieve($queryText));

foreach ($results as $i => $document) {
    echo sprintf(
        "  %d. %s (Score: %.4f)\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
        $document->getScore() ?? 0.0,
    );
}
echo "\n";

// 3. Hybrid retrieval with CombinedStore + RRF
echo "--- Hybrid RRF Results (CombinedStore) ---\n";
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

echo "=== Summary ===\n";
echo "- Vector retrieval finds semantically similar documents via embeddings\n";
echo "- Text retrieval matches documents by keyword overlap\n";
echo "- CombinedStore merges both result lists using rank-based RRF scoring\n";
