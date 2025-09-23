<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Store\Document\Loader\TextFileLoader;
use Symfony\AI\Store\Document\Transformer\TextReplaceTransformer;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OLLAMA_HOST_URL'), http_client());
$store = new InMemoryStore();
$vectorizer = new Vectorizer($platform, $embeddings = new Ollama(env('OLLAMA_EMBEDDINGS')), logger());
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
        new TextReplaceTransformer(search: '## Plot', replace: '## Synopsis'),
        new TextSplitTransformer(chunkSize: 500, overlap: 100),
    ],
);

$indexer->index();

$vector = $vectorizer->vectorize('Roman gladiator revenge');
$results = $store->query($vector);
foreach ($results as $i => $document) {
    echo sprintf("%d. %s\n", $i + 1, substr($document->id, 0, 40).'...');
}
