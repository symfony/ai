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
use Symfony\AI\Store\Document\Loader\TextFileLoader;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Retriever;

require_once dirname(__DIR__).'/bootstrap.php';

$store = new InMemoryStore();

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');

$indexer = new Indexer(
    loader: new TextFileLoader(),
    vectorizer: $vectorizer,
    store: $store,
    source: [
        dirname(__DIR__, 2).'/fixtures/movies/gladiator.md',
        dirname(__DIR__, 2).'/fixtures/movies/inception.md',
        dirname(__DIR__, 2).'/fixtures/movies/jurassic-park.md',
    ],
    transformers: [
        new TextSplitTransformer(chunkSize: 500, overlap: 100),
    ],
);
$indexer->index();

$retriever = new Retriever(
    vectorizer: $vectorizer,
    store: $store,
);

echo "Searching for: 'Roman gladiator revenge'\n\n";
$results = $retriever->retrieve('Roman gladiator revenge', ['maxItems' => 1]);

foreach ($results as $i => $document) {
    echo sprintf("%d. Document ID: %s\n", $i + 1, $document->id);
    echo sprintf("   Score: %s\n", $document->score ?? 'n/a');
    echo sprintf("   Source: %s\n\n", $document->metadata->getSource() ?? 'unknown');
}
