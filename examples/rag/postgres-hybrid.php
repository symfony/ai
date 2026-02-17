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
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Store\Bridge\Postgres\HybridStore;
use Symfony\AI\Store\Bridge\Postgres\ReciprocalRankFusion;
use Symfony\AI\Store\Bridge\Postgres\TextSearch\Bm25TextSearchStrategy;
use Symfony\AI\Store\Bridge\Postgres\TextSearch\PostgresTextSearchStrategy;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo '=== PostgreSQL Hybrid Search Demo ==='.\PHP_EOL.\PHP_EOL;
echo 'Demonstrates HybridStore with configurable search strategies:'.\PHP_EOL;
echo '- Native PostgreSQL FTS vs BM25'.\PHP_EOL;
echo '- Semantic ratio adjustment'.\PHP_EOL;
echo '- Custom RRF scoring'.\PHP_EOL.\PHP_EOL;

$connection = DriverManager::getConnection((new DsnParser())->parse(env('POSTGRES_URI')));
$pdo = $connection->getNativeConnection();

if (!$pdo instanceof PDO) {
    throw new RuntimeException('Unable to get native PDO connection from Doctrine DBAL.');
}

echo '=== Using BM25 Text Search Strategy ==='.\PHP_EOL.\PHP_EOL;

$store = new HybridStore(
    connection: $pdo,
    tableName: 'hybrid_movies',
    textSearchStrategy: new Bm25TextSearchStrategy('en'),
    rrf: new ReciprocalRankFusion(k: 60, normalizeScores: true),
    semanticRatio: 0.5,
);

// Create embeddings and documents
$documents = [];
foreach (Movies::all() as $i => $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata(array_merge($movie, ['content' => 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description']])),
    );
}

// Initialize the table
$store->setup();

// Create embeddings for documents
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());
$indexer = new Indexer(new InMemoryLoader($documents), $vectorizer, $store, logger: logger());
$indexer->index($documents);

// Create a query embedding
$queryText = 'futuristic technology and artificial intelligence';
echo sprintf('Query: "%s"', $queryText).\PHP_EOL.\PHP_EOL;
$queryEmbedding = $vectorizer->vectorize($queryText);

// Test different semantic ratios to compare results
$ratios = [
    ['ratio' => 0.0, 'description' => '100% Full-text search (keyword matching)'],
    ['ratio' => 0.5, 'description' => 'Balanced hybrid (RRF: 50% semantic + 50% FTS)'],
    ['ratio' => 1.0, 'description' => '100% Semantic search (vector similarity)'],
];

foreach ($ratios as $config) {
    echo sprintf('--- %s ---', $config['description']).\PHP_EOL;

    if (1.0 === $config['ratio']) {
        $query = new VectorQuery($queryEmbedding);
    } else {
        $query = new HybridQuery($queryEmbedding, 'technology', $config['ratio']);
    }

    $results = $store->query($query, ['limit' => 3]);

    echo 'Top 3 results:'.\PHP_EOL;
    foreach ($results as $i => $result) {
        $metadata = $result->getMetadata()->getArrayCopy();
        echo sprintf(
            '  %d. %s (Score: %.4f)'.\PHP_EOL,
            $i + 1,
            $metadata['title'] ?? 'Unknown',
            $result->getScore() ?? 0.0
        );
    }
    echo "\n";
}

echo '--- Custom query with pure semantic search ---'.\PHP_EOL;
echo 'Query: Movies about space exploration'.\PHP_EOL;
$spaceEmbedding = $vectorizer->vectorize('space exploration and cosmic adventures');
$results = $store->query(new VectorQuery($spaceEmbedding), ['limit' => 3]);

echo 'Top 3 results:'.\PHP_EOL;
foreach ($results as $i => $result) {
    $metadata = $result->getMetadata()->getArrayCopy();
    echo sprintf(
        '  %d. %s (Score: %.4f)'.\PHP_EOL,
        $i + 1,
        $metadata['title'] ?? 'Unknown',
        $result->getScore() ?? 0.0
    );
}
echo "\n";

// Cleanup
$store->drop();

echo '=== Comparing with Native PostgreSQL FTS ==='.\PHP_EOL.\PHP_EOL;

$storeFts = new HybridStore(
    connection: $pdo,
    tableName: 'hybrid_movies_fts',
    textSearchStrategy: new PostgresTextSearchStrategy(),
    semanticRatio: 0.5,
);

$storeFts->setup();
$indexer = new Indexer(new InMemoryLoader($documents), $vectorizer, $storeFts, logger: logger());
$indexer->index($documents);

$resultsFts = $storeFts->query(
    new HybridQuery($queryEmbedding, 'technology', 0.5),
    ['limit' => 3],
);

echo 'Top 3 results (Native FTS):'.\PHP_EOL;
foreach ($resultsFts as $i => $result) {
    $metadata = $result->getMetadata()->getArrayCopy();
    echo sprintf(
        '  %d. %s (Score: %.4f)'.\PHP_EOL,
        $i + 1,
        $metadata['title'] ?? 'Unknown',
        $result->getScore() ?? 0.0
    );
}

$storeFts->drop();

echo "\n=== Summary ===\n";
echo "- semanticRatio = 0.0: Pure keyword matching\n";
echo "- semanticRatio = 0.5: Balanced hybrid (RRF)\n";
echo "- semanticRatio = 1.0: Pure semantic search\n";
echo "\nText Search Strategies:\n";
echo "- PostgresTextSearchStrategy: Native FTS (ts_rank_cd)\n";
echo "- Bm25TextSearchStrategy: BM25 ranking (requires pg_bm25 extension)\n";
