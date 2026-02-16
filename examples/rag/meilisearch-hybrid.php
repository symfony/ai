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
use Symfony\AI\Store\Bridge\Meilisearch\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo "=== Meilisearch Hybrid Search Demo ===\n\n";
echo "This example demonstrates how to configure the semantic ratio to balance\n";
echo "between semantic (vector) search and full-text search in Meilisearch.\n\n";

// Initialize the store with a balanced hybrid search (50/50)
$store = new Store(
    httpClient: http_client(),
    endpointUrl: env('MEILISEARCH_HOST'),
    apiKey: env('MEILISEARCH_API_KEY'),
    indexName: 'movies_hybrid',
    semanticRatio: 0.5, // Balanced hybrid search by default
);

// Create embeddings and documents
$documents = [];
foreach (Movies::all() as $i => $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

// Initialize the index
$store->setup();

// Create embeddings for documents
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());
$indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store, logger: logger()));
$indexer->index($documents);

// Create a query embedding
$queryText = 'futuristic technology and artificial intelligence';
echo "Query: \"$queryText\"\n\n";
$queryEmbedding = $vectorizer->vectorize($queryText);

// Test different semantic ratios to compare results
$ratios = [
    ['ratio' => 0.0, 'description' => '100% Full-text search (keyword matching)'],
    ['ratio' => 0.5, 'description' => 'Balanced hybrid (50% semantic + 50% full-text)'],
    ['ratio' => 1.0, 'description' => '100% Semantic search (vector similarity)'],
];

foreach ($ratios as $config) {
    echo "--- {$config['description']} ---\n";

    // Override the semantic ratio for this specific query
    $results = $store->query(new HybridQuery($queryEmbedding, 'technology', $config['ratio']));

    echo "Top 3 results:\n";
    foreach (array_slice($results, 0, 3) as $i => $result) {
        $metadata = $result->metadata->getArrayCopy();
        echo sprintf(
            "  %d. %s (Score: %.4f)\n",
            $i + 1,
            $metadata['title'] ?? 'Unknown',
            $result->score ?? 0.0
        );
    }
    echo "\n";
}

echo "--- Custom query with pure semantic search ---\n";
echo "Query: Movies about space exploration\n";
$spaceEmbedding = $vectorizer->vectorize('space exploration and cosmic adventures');
$results = $store->query(new VectorQuery($spaceEmbedding));

echo "Top 3 results:\n";
foreach (array_slice($results, 0, 3) as $i => $result) {
    $metadata = $result->metadata->getArrayCopy();
    echo sprintf(
        "  %d. %s (Score: %.4f)\n",
        $i + 1,
        $metadata['title'] ?? 'Unknown',
        $result->score ?? 0.0
    );
}
echo "\n";

// Cleanup
$store->drop();

echo "=== Summary ===\n";
echo "- semanticRatio = 0.0: Best for exact keyword matches\n";
echo "- semanticRatio = 0.5: Balanced approach combining both methods\n";
echo "- semanticRatio = 1.0: Best for conceptual similarity searches\n";
echo "\nYou can set the default ratio when instantiating the Store,\n";
echo "and override it per query using the 'semanticRatio' option.\n";
