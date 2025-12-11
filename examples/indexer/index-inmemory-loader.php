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
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$store = new InMemoryStore();
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');

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
];

$indexer = new Indexer(
    loader: new InMemoryLoader($documents),
    vectorizer: $vectorizer,
    store: $store,
    source: null,
    transformers: [
        new TextSplitTransformer(chunkSize: 100, overlap: 20),
    ],
);

$indexer->index();

$vector = $vectorizer->vectorize('machine learning artificial intelligence');
$results = $store->query($vector);
foreach ($results as $i => $document) {
    echo sprintf("%d. %s\n", $i + 1, substr($document->id, 0, 40).'...');
}
