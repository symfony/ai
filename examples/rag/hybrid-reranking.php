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
use Symfony\AI\Store\Bridge\Cohere\Reranker;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\HybridRetriever;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Retriever;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo "=== Hybrid Retrieval with RRF + Cohere Reranking ===\n\n";
echo "This example demonstrates combining vector (semantic) and text (keyword)\n";
echo "retrieval using Reciprocal Rank Fusion (RRF), with optional Cohere reranking.\n\n";

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
$indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store, logger: logger()));
$indexer->index($documents);

$vectorRetriever = new Retriever($store, $vectorizer, logger());

$textRetriever = new Retriever($store, logger: logger());

$queryText = 'crime family mafia';
echo "Query: \"$queryText\"\n\n";

// 1. Vector-only results (semantic similarity)
echo "--- Vector-Only Results (semantic similarity) ---\n";
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

// 2. Text-only results (keyword matching)
echo "--- Text-Only Results (keyword matching) ---\n";
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

// 3. Hybrid retrieval with RRF only (no reranker)
echo "--- Hybrid RRF Results (merged, no reranker) ---\n";
$hybridRetriever = new HybridRetriever(
    vectorRetriever: $vectorRetriever,
    textRetriever: $textRetriever,
    rrfK: 60,
    candidateCount: 10,
    topK: 5,
);

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

// 4. Hybrid retrieval with RRF + Cohere reranking
echo "--- Hybrid RRF + Cohere Reranking Results ---\n";
$reranker = new Reranker(
    client: HttpClient::create(),
    apiKey: env('COHERE_API_KEY'),
);

$hybridWithReranker = new HybridRetriever(
    vectorRetriever: $vectorRetriever,
    textRetriever: $textRetriever,
    reranker: $reranker,
    rrfK: 60,
    candidateCount: 10,
    topK: 5,
);

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
echo "- Vector retrieval finds semantically similar documents via embeddings\n";
echo "- Text retrieval matches documents by keyword overlap\n";
echo "- RRF merges both result lists using rank-based scoring\n";
echo "- Adding a reranker (Cohere) further refines results using a cross-encoder\n";
