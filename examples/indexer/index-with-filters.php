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
use Symfony\AI\Store\Document\Filter\TextContainsFilter;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextTrimTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentIndexer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$store = new InMemoryStore();
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');

// Sample documents with some unwanted content
$documents = [
    new TextDocument(
        Uuid::v4(),
        'Artificial Intelligence is transforming the way we work and live. Machine learning algorithms can now process vast amounts of data and make predictions with remarkable accuracy.',
        new Metadata(['title' => 'AI Revolution', 'category' => 'technology'])
    ),
    new TextDocument(
        Uuid::v4(),
        'Week of Symfony - This week we released several new features including improved performance and better documentation.',
        new Metadata(['title' => 'Weekly Newsletter', 'category' => 'newsletter'])
    ),
    new TextDocument(
        Uuid::v4(),
        'SPAM: Buy cheap products now! Limited time offer on all electronics. Click here to save 90% on your purchase!',
        new Metadata(['title' => 'Advertisement', 'category' => 'spam'])
    ),
    new TextDocument(
        Uuid::v4(),
        'Climate change is one of the most pressing challenges of our time. Renewable energy sources like solar and wind power are becoming increasingly important for a sustainable future.',
        new Metadata(['title' => 'Climate Action', 'category' => 'environment'])
    ),
];

// Create filters to remove unwanted content
$filters = [
    new TextContainsFilter('Week of Symfony', caseSensitive: false),
    new TextContainsFilter('SPAM:', caseSensitive: true),
];

$indexer = new DocumentIndexer(
    processor: new DocumentProcessor(
        vectorizer: $vectorizer,
        store: $store,
        filters: $filters,
        transformers: [
            new TextTrimTransformer(),
        ],
        logger: logger(),
    ),
);

$indexer->index($documents);

$vector = $vectorizer->vectorize('technology artificial intelligence');
$results = $store->query(new VectorQuery($vector));

$filteredDocuments = 0;
foreach ($results as $i => $document) {
    $title = $document->getMetadata()['title'] ?? 'Unknown';
    $category = $document->getMetadata()['category'] ?? 'Unknown';
    echo sprintf("%d. %s [%s]\n", $i + 1, $title, $category);
    echo sprintf("   Content: %s\n", substr($document->getMetadata()->getText() ?? 'No content', 0, 80).'...');
    echo sprintf("   ID: %s\n\n", substr($document->getId(), 0, 8).'...');
    ++$filteredDocuments;
}

echo "=== Results Summary ===\n";
echo sprintf("Original documents: %d\n", count($documents));
echo sprintf("Documents after filtering: %d\n", ++$filteredDocuments);
echo sprintf("Filtered out: %d documents\n", count($documents) - $filteredDocuments);
echo "\nThe 'Week of Symfony' newsletter and SPAM advertisement were successfully filtered out!\n";
