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
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextTrimTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
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

$indexer = new Indexer(
    loader: new InMemoryLoader($documents),
    vectorizer: $vectorizer,
    store: $store,
    source: null,
    filters: $filters,
    transformers: [
        new TextTrimTransformer(),
    ],
);

$indexer->index();

$vector = $vectorizer->vectorize('technology artificial intelligence');
$results = $store->query($vector);

$filteredDocuments = 0;
foreach ($results as $i => $document) {
    $title = $document->metadata['title'] ?? 'Unknown';
    $category = $document->metadata['category'] ?? 'Unknown';
    echo sprintf("%d. %s [%s]\n", $i + 1, $title, $category);
    echo sprintf("   Content: %s\n", substr($document->metadata->getText() ?? 'No content', 0, 80).'...');
    echo sprintf("   ID: %s\n\n", substr($document->id, 0, 8).'...');
    ++$filteredDocuments;
}

echo "=== Results Summary ===\n";
echo sprintf("Original documents: %d\n", count($documents));
echo sprintf("Documents after filtering: %d\n", ++$filteredDocuments);
echo sprintf("Filtered out: %d documents\n", count($documents) - $filteredDocuments);
echo "\nThe 'Week of Symfony' newsletter and SPAM advertisement were successfully filtered out!\n";
