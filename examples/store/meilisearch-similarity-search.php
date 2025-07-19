<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Fixtures\Movies;
use Symfony\AI\Platform\Bridge\OpenAI\Embeddings;
use Symfony\AI\Platform\Bridge\OpenAI\GPT;
use Symfony\AI\Platform\Bridge\OpenAI\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Store\Bridge\Meilisearch\Store;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

// initialize the store
$store = new Store(
    httpClient: HttpClient::create(),
    endpointUrl: env('MEILISEARCH_HOST'),
    apiKey: env('MEILISEARCH_API_KEY'),
    indexName: 'movies',
);

// create embeddings and documents
$documents = [];
foreach (Movies::all() as $i => $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

// initialize the index
$store->initialize();

// create embeddings for documents
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$vectorizer = new Vectorizer($platform, $embeddings = new Embeddings());
$indexer = new Indexer($vectorizer, $store, logger());
$indexer->index($documents);

$model = new GPT(GPT::GPT_4O_MINI);

$similaritySearch = new SimilaritySearch($platform, $embeddings, $store);
$toolbox = new Toolbox([$similaritySearch], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, $model, [$processor], [$processor], logger());

$messages = new MessageBag(
    Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
    Message::ofUser('Which movie fits the theme of technology?')
);
$response = $agent->call($messages);

echo $response->getContent().\PHP_EOL;
