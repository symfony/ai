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
use Symfony\AI\Store\Document\Transformer\TextReplaceTransformer;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer\DocumentProcessor;
use Symfony\AI\Store\Indexer\SourceIndexer;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Query\VectorQuery;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$store = new InMemoryStore();
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
$indexer = new SourceIndexer(
    loader: new TextFileLoader(),
    processor: new DocumentProcessor(
        vectorizer: $vectorizer,
        store: $store,
        transformers: [
            new TextReplaceTransformer(search: '## Plot', replace: '## Synopsis'),
            new TextSplitTransformer(chunkSize: 500, overlap: 100),
        ],
        logger: logger(),
    ),
);

$indexer->index([
    dirname(__DIR__, 2).'/fixtures/movies/gladiator.md',
    dirname(__DIR__, 2).'/fixtures/movies/inception.md',
    dirname(__DIR__, 2).'/fixtures/movies/jurassic-park.md',
]);

$vector = $vectorizer->vectorize('Roman gladiator revenge');
$results = $store->query(new VectorQuery($vector));
foreach ($results as $i => $document) {
    echo sprintf("%d. %s\n", $i + 1, substr($document->getId(), 0, 40).'...');
}
