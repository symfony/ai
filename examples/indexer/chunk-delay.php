<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\ChunkDelayTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$store = new InMemoryStore();
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');

// Create a batch of documents to demonstrate the chunk delay behavior
$documents = [
    new TextDocument(
        Uuid::v4(),
        'Artificial Intelligence is transforming the way we work and live. Machine learning algorithms can now process vast amounts of data and make predictions with remarkable accuracy.',
        new Metadata(['title' => 'AI Revolution'])
    ),
    new TextDocument(
        Uuid::v4(),
        'Climate change is one of the most pressing challenges of our time. Renewable energy sources like solar and wind power are becoming increasingly important for a sustainable future.',
        new Metadata(['title' => 'Climate Action'])
    ),
    new TextDocument(
        Uuid::v4(),
        'Quantum computing promises to revolutionize computing by leveraging quantum mechanical phenomena. It could solve problems that are currently intractable for classical computers.',
        new Metadata(['title' => 'Quantum Computing'])
    ),
    new TextDocument(
        Uuid::v4(),
        'Space exploration continues to advance with missions to Mars and beyond. Private companies are now playing a significant role alongside government space agencies.',
        new Metadata(['title' => 'Space Exploration'])
    ),
    new TextDocument(
        Uuid::v4(),
        'Biotechnology is making breakthroughs in medicine and agriculture. Gene editing technologies like CRISPR are opening new possibilities for treating genetic diseases.',
        new Metadata(['title' => 'Biotechnology'])
    ),
];

$indexer = new DocumentIndexer(
    new DocumentProcessor(
        vectorizer: $vectorizer,
        store: $store,
        transformers: [
            new ChunkDelayTransformer(new Clock(), 1, 10, logger()),
        ],
        logger: logger(),
    ),
);

echo "Indexing documents with chunk delay...\n";
$startTime = microtime(true);

$indexer->index($documents);

$elapsedTime = microtime(true) - $startTime;
echo sprintf("Indexing completed in %.2f seconds.\n\n", $elapsedTime);

$vector = $vectorizer->vectorize('machine learning artificial intelligence');
$results = $store->query(new VectorQuery($vector));

echo "Search results for 'machine learning artificial intelligence':\n";
foreach ($results as $i => $document) {
    echo sprintf("%d. %s\n", $i + 1, substr($document->getId(), 0, 40).'...');
}
