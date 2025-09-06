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
use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
use Symfony\AI\Platform\Bridge\Perplexity\PlatformFactory;
use Symfony\AI\Platform\Bridge\Perplexity\SearchResultProcessor;
use Symfony\AI\Platform\Bridge\Perplexity\TokenOutputProcessor;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('PERPLEXITY_API_KEY'), http_client());
$model = new Perplexity();
$agent = new Agent($platform, $model, outputProcessors: [new TokenOutputProcessor(), new SearchResultProcessor()], logger: logger());

$messages = new MessageBag(Message::ofUser('What is the best French cheese of the first quarter-century of 21st century?'));
$response = $agent->call($messages, [
    'search_mode' => 'academic',
    'search_after_date_filter' => '01/01/2000',
    'search_before_date_filter' => '01/01/2025',
]);

echo $response->getContent().\PHP_EOL;
echo \PHP_EOL;

$metadata = $response->getMetadata();
if ($metadata->has('search_results')) {
    echo 'Search results:'.\PHP_EOL;
    if (0 === count($metadata->get('search_results'))) {
        echo 'No search results.'.\PHP_EOL;

        return;
    }
    foreach ($metadata->get('search_results') as $i => $searchResult) {
        echo 'Result #'.($i + 1).':'.\PHP_EOL;
        echo $searchResult['title'].\PHP_EOL;
        echo $searchResult['url'].\PHP_EOL;
        echo $searchResult['date'].\PHP_EOL;
        echo $searchResult['last_updated'] ? $searchResult['last_updated'].\PHP_EOL : '';
        echo $searchResult['snippet'] ? $searchResult['snippet'].\PHP_EOL : '';
        echo \PHP_EOL;
    }
}

if ($metadata->has('citations')) {
    echo 'Citations:'.\PHP_EOL;
    if (0 === count($metadata->get('citations'))) {
        echo 'No citations.'.\PHP_EOL;

        return;
    }
    foreach ($metadata->get('citations') as $i => $citation) {
        echo 'Citation #'.($i + 1).':'.\PHP_EOL;
        echo $citation.\PHP_EOL;
        echo \PHP_EOL;
    }
}
