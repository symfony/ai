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
use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory;
use Symfony\AI\Store\CombinedStore;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
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

echo "=== Hybrid Retrieval with RRF + HuggingFace Reranking ===\n\n";
echo "This example demonstrates combining vector (semantic) and text (keyword)\n";
echo "retrieval using CombinedStore with Reciprocal Rank Fusion (RRF), with\n";
echo "optional reranking via a PostQueryEvent listener.\n\n";

$store = new InMemoryStore();

$documents = [];
foreach (Movies::all() as $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

$platform = PlatformFactory::create(env('HUGGINGFACE_KEY'), httpClient: http_client());
$vectorizer = new Vectorizer($platform, 'BAAI/bge-small-en-v1.5?task=feature-extraction', logger());
$indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store, logger: logger()));
$indexer->index($documents);

$vectorRetriever = new Retriever($store, $vectorizer, logger: logger());

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

// 3. Hybrid retrieval with CombinedStore + RRF (no reranker)
echo "--- Hybrid RRF Results (CombinedStore, no reranker) ---\n";
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

// 4. Hybrid retrieval with CombinedStore + RRF + Platform reranking via event listener
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

// 5. Full pipeline: PreQueryEvent (query expansion) + PostQueryEvent (reranking)
echo "--- Full Pipeline: Query Enhancement + Reranking ---\n";
$vagueQuery = 'gangster';
echo "Original query: \"$vagueQuery\"\n";

$fullDispatcher = new EventDispatcher();

// Pre-retrieval: expand the query with related terms
$fullDispatcher->addListener(PreQueryEvent::class, static function (PreQueryEvent $event) {
    $expanded = $event->getQuery().' crime family mafia organized crime';
    echo "Enhanced query: \"$expanded\"\n";
    $event->setQuery($expanded);
});

// Post-retrieval: rerank with cross-encoder
$fullDispatcher->addListener(PostQueryEvent::class, new RerankerListener($reranker, topK: 5));

$fullPipelineRetriever = new Retriever($combinedStore, $vectorizer, $fullDispatcher, logger: logger());
$results = iterator_to_array($fullPipelineRetriever->retrieve($vagueQuery));

foreach ($results as $i => $document) {
    echo sprintf(
        "  %d. %s (Relevance: %.4f)\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
        $document->getScore() ?? 0.0,
    );
}
echo "\n";

// 6. Caching via PreQueryEvent short-circuiting
echo "--- Caching: Short-Circuit Store Query via PreQueryEvent ---\n";

/** @var array<string, list<VectorDocument>> $cache */
$cache = [];
$cacheDispatcher = new EventDispatcher();

// Pre-retrieval: return cached results if available, skipping store query + vectorization
$cacheDispatcher->addListener(PreQueryEvent::class, static function (PreQueryEvent $event) use (&$cache) {
    $cacheKey = $event->getQuery();
    if (isset($cache[$cacheKey])) {
        echo "  Cache HIT for \"$cacheKey\" — skipping store query\n";
        $event->setDocuments($cache[$cacheKey]);

        return;
    }
    echo "  Cache MISS for \"$cacheKey\"\n";
});

// Post-retrieval: store results in cache
$cacheDispatcher->addListener(PostQueryEvent::class, static function (PostQueryEvent $event) use (&$cache) {
    $cacheKey = $event->getQuery();
    $cache[$cacheKey] = iterator_to_array($event->getDocuments());
    $event->setDocuments($cache[$cacheKey]);
    echo '  Cached '.count($cache[$cacheKey])." results for \"$cacheKey\"\n";
});

$cachedRetriever = new Retriever($combinedStore, $vectorizer, $cacheDispatcher, logger: logger());

echo "First call (populates cache):\n";
$results = iterator_to_array($cachedRetriever->retrieve($queryText));
foreach (array_slice($results, 0, 3) as $i => $document) {
    echo sprintf(
        "  %d. %s\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
    );
}

echo "Second call (served from cache, no store query or vectorization):\n";
$results = iterator_to_array($cachedRetriever->retrieve($queryText));
foreach (array_slice($results, 0, 3) as $i => $document) {
    echo sprintf(
        "  %d. %s\n",
        $i + 1,
        $document->getMetadata()['title'] ?? 'Unknown',
    );
}
echo "\n";

echo "=== Summary ===\n";
echo "- Vector retrieval finds semantically similar documents via embeddings\n";
echo "- Text retrieval matches documents by keyword overlap\n";
echo "- CombinedStore merges both result lists using rank-based RRF scoring\n";
echo "- Adding a RerankerListener via EventDispatcher further refines results using a cross-encoder\n";
echo "- PreQueryEvent allows query enhancement (expansion, spelling correction) before vectorization\n";
echo "- PreQueryEvent::setDocuments() short-circuits the store query for caching or pre-computed results\n";
