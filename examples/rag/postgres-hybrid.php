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
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

echo '=== PostgreSQL Hybrid Search Demo ==='.\PHP_EOL.\PHP_EOL;

$connection = DriverManager::getConnection((new DsnParser())->parse(env('POSTGRES_URI')));
$pdo = $connection->getNativeConnection();

if (!$pdo instanceof PDO) {
    throw new RuntimeException('Unable to get native PDO connection from Doctrine DBAL.');
}

// Prepare documents
$documents = [];
foreach (Movies::all() as $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata(array_merge($movie, ['content' => 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description']])),
    );
}

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small', logger());

$queryText = 'futuristic technology and artificial intelligence';

// --- Native PostgreSQL FTS (works with any PostgreSQL + pgvector) ---

echo '=== Using Native PostgreSQL FTS ==='.\PHP_EOL.\PHP_EOL;

$store = new HybridStore(
    connection: $pdo,
    tableName: 'hybrid_movies',
    textSearchStrategy: new PostgresTextSearchStrategy(),
    rrf: new ReciprocalRankFusion(k: 60, normalizeScores: true),
    semanticRatio: 0.5,
);

$store->setup();

$indexer = new Indexer(new InMemoryLoader($documents), $vectorizer, $store, logger: logger());
$indexer->index();

echo sprintf('Query: "%s"', $queryText).\PHP_EOL.\PHP_EOL;
$queryEmbedding = $vectorizer->vectorize($queryText);

$ratios = [
    ['ratio' => 0.0, 'description' => '100% Full-text search (keyword matching)'],
    ['ratio' => 0.5, 'description' => 'Balanced hybrid (RRF: 50% semantic + 50% FTS)'],
    ['ratio' => 1.0, 'description' => '100% Semantic search (vector similarity)'],
];

foreach ($ratios as $config) {
    echo sprintf('--- %s ---', $config['description']).\PHP_EOL;

    $results = $store->query($queryEmbedding, [
        'semanticRatio' => $config['ratio'],
        'q' => 'technology',
        'limit' => 3,
    ]);

    echo 'Top 3 results:'.\PHP_EOL;
    foreach ($results as $i => $result) {
        $metadata = $result->metadata->getArrayCopy();
        echo sprintf(
            '  %d. %s (Score: %.4f)'.\PHP_EOL,
            $i + 1,
            $metadata['title'] ?? 'Unknown',
            $result->score ?? 0.0
        );
    }
    echo \PHP_EOL;
}

echo '--- Custom query with pure semantic search ---'.\PHP_EOL;
echo 'Query: Movies about space exploration'.\PHP_EOL;
$spaceEmbedding = $vectorizer->vectorize('space exploration and cosmic adventures');
$results = $store->query($spaceEmbedding, [
    'semanticRatio' => 1.0,
    'limit' => 3,
]);

echo 'Top 3 results:'.\PHP_EOL;
foreach ($results as $i => $result) {
    $metadata = $result->metadata->getArrayCopy();
    echo sprintf(
        '  %d. %s (Score: %.4f)'.\PHP_EOL,
        $i + 1,
        $metadata['title'] ?? 'Unknown',
        $result->score ?? 0.0
    );
}
echo \PHP_EOL;

$store->drop();

// --- BM25 strategy (requires plpgsql_bm25 extension) ---

$bm25Strategy = new Bm25TextSearchStrategy('en');

if ($bm25Strategy->isAvailable($pdo)) {
    echo '=== Using BM25 Text Search Strategy ==='.\PHP_EOL.\PHP_EOL;

    $storeBm25 = new HybridStore(
        connection: $pdo,
        tableName: 'hybrid_movies_bm25',
        textSearchStrategy: $bm25Strategy,
        rrf: new ReciprocalRankFusion(k: 60, normalizeScores: true),
        semanticRatio: 0.5,
    );

    $storeBm25->setup();
    $indexer = new Indexer(new InMemoryLoader($documents), $vectorizer, $storeBm25, logger: logger());
    $indexer->index();

    $resultsBm25 = $storeBm25->query($queryEmbedding, [
        'semanticRatio' => 0.5,
        'q' => 'technology',
        'limit' => 3,
    ]);

    echo 'Top 3 results (BM25):'.\PHP_EOL;
    foreach ($resultsBm25 as $i => $result) {
        $metadata = $result->metadata->getArrayCopy();
        echo sprintf(
            '  %d. %s (Score: %.4f)'.\PHP_EOL,
            $i + 1,
            $metadata['title'] ?? 'Unknown',
            $result->score ?? 0.0
        );
    }

    $storeBm25->drop();
} else {
    echo '=== BM25 Text Search Strategy ==='.\PHP_EOL;
    echo 'Skipped: plpgsql_bm25 extension is not installed.'.\PHP_EOL;
    echo 'See https://github.com/jankovicsandras/plpgsql_bm25'.\PHP_EOL;
}
