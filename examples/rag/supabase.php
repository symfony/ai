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
use Symfony\AI\Platform\Bridge\Ollama\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Store\Bridge\Supabase\Store;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$store = new Store(
    httpClient: http_client(),
    url: env('SUPABASE_URL'),
    apiKey: env('SUPABASE_API_KEY'),
    table: env('SUPABASE_TABLE'),
    vectorFieldName: env('SUPABASE_VECTOR_FIELD'),
    vectorDimension: (int) env('SUPABASE_VECTOR_DIMENSION'),
    functionName: env('SUPABASE_MATCH_FUNCTION'),
);

$documents = [];

foreach (Movies::all() as $movie) {
    $documents[] = new TextDocument(
        id: Uuid::v4(),
        content: 'Title: '.$movie['title'].\PHP_EOL.'Director: '.$movie['director'].\PHP_EOL.'Description: '.$movie['description'],
        metadata: new Metadata($movie),
    );
}

$platform = PlatformFactory::create(env('OLLAMA_HOST_URL'), http_client());

$vectorizer = new Vectorizer($platform, env('OLLAMA_EMBEDDINGS'));
$loader = new InMemoryLoader($documents);
$indexer = new Indexer($loader, $vectorizer, $store, logger: logger());
$indexer->index();

$similaritySearch = new SimilaritySearch($vectorizer, $store);
$toolbox = new Toolbox([$similaritySearch], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, env('OLLAMA_LLM'), [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
    Message::ofUser('Which movie fits the theme of technology?')
);

$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
