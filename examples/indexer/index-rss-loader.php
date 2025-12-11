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
use Symfony\AI\Store\Document\Loader\RssFeedLoader;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\Component\HttpClient\HttpClient;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$store = new InMemoryStore();
$vectorizer = new Vectorizer($platform, 'text-embedding-3-small');
$indexer = new Indexer(
    loader: new RssFeedLoader(HttpClient::create()),
    vectorizer: $vectorizer,
    store: $store,
    source: [
        'https://feeds.feedburner.com/symfony/blog',
        'https://www.tagesschau.de/index~rss2.xml',
    ],
    transformers: [
        new TextSplitTransformer(chunkSize: 500, overlap: 100),
    ],
);

$indexer->index();

$vector = $vectorizer->vectorize('Week of Symfony');
$results = $store->query($vector);
foreach ($results as $i => $document) {
    echo sprintf("%d. %s\n", $i + 1, substr($document->id, 0, 40).'...');
}
