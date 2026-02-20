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
use Symfony\AI\Store\Bridge\Qdrant\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo '=== Qdrant Hybrid Search Demo ==='.\PHP_EOL.\PHP_EOL;
echo 'This example demonstrates hybrid search combining dense vector similarity'.\PHP_EOL;
echo 'with BM25 sparse vectors, using Formula Queries to control the balance.'.\PHP_EOL.\PHP_EOL;

$store = new Store(
    httpClient: http_client(),
    endpointUrl: env('QDRANT_HOST'),
    apiKey: env('QDRANT_SERVICE_API_KEY'),
    collectionName: 'movies_hybrid',
    hybridEnabled: true,
);

$store->setup();

$documents = [];
foreach (Movies::all() as $movie) {
    $content = 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'];
    $metadata = new Metadata($movie);
    $metadata->setText($content);
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: $content,
        metadata: $metadata,
    );
}

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());
$indexer = new DocumentIndexer(new DocumentProcessor($vectorizer, $store, logger: logger()));
$indexer->index($documents);

$queryText = 'futuristic technology and artificial intelligence';
echo 'Query: "'.$queryText.'"'.\PHP_EOL.\PHP_EOL;
$queryEmbedding = $vectorizer->vectorize($queryText);

$ratios = [
    ['ratio' => 0.0, 'description' => '100% Full-text search (BM25 keyword matching)'],
    ['ratio' => 0.5, 'description' => 'Balanced hybrid (50% semantic + 50% BM25)'],
    ['ratio' => 1.0, 'description' => '100% Semantic search (dense vector similarity)'],
];

foreach ($ratios as $config) {
    echo '--- '.$config['description'].' ---'.\PHP_EOL;

    $results = $store->query(new HybridQuery($queryEmbedding, $queryText, $config['ratio']));

    echo 'Top 3 results:'.\PHP_EOL;
    foreach (array_slice(iterator_to_array($results), 0, 3) as $i => $result) {
        $metadata = $result->getMetadata()->getArrayCopy();
        echo sprintf(
            '  %d. %s (Score: %.4f)'.\PHP_EOL,
            $i + 1,
            $metadata['title'] ?? 'Unknown',
            $result->getScore() ?? 0.0
        );
    }
    echo \PHP_EOL;
}

echo '--- Pure semantic search with VectorQuery ---'.\PHP_EOL;
echo 'Query: Movies about space exploration'.\PHP_EOL;
$spaceEmbedding = $vectorizer->vectorize('space exploration and cosmic adventures');
$results = $store->query(new VectorQuery($spaceEmbedding));

echo 'Top 3 results:'.\PHP_EOL;
foreach (array_slice(iterator_to_array($results), 0, 3) as $i => $result) {
    $metadata = $result->getMetadata()->getArrayCopy();
    echo sprintf(
        '  %d. %s (Score: %.4f)'.\PHP_EOL,
        $i + 1,
        $metadata['title'] ?? 'Unknown',
        $result->getScore() ?? 0.0
    );
}
echo \PHP_EOL;

$store->drop();

echo '=== Summary ==='.\PHP_EOL;
echo '- semanticRatio = 0.0: Best for exact keyword matches (pure BM25)'.\PHP_EOL;
echo '- semanticRatio = 0.5: Balanced approach combining both methods'.\PHP_EOL;
echo '- semanticRatio = 1.0: Best for conceptual similarity searches'.\PHP_EOL;
echo \PHP_EOL.'Qdrant uses Formula Queries to weight the BM25 and dense prefetch scores.'.\PHP_EOL;
echo 'The collection requires hybrid_enabled: true and Qdrant >= 1.14.'.\PHP_EOL;
