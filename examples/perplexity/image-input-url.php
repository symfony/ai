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
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('PERPLEXITY_API_KEY'), http_client());
$model = new Perplexity();
$agent = new Agent($platform, $model, outputProcessors: [new TokenOutputProcessor(), new SearchResultProcessor()], logger: logger());

$messages = new MessageBag(
    Message::forSystem('You are an image analyzer bot that helps identify the content of images.'),
    Message::ofUser(
        'Describe the image as a comedian would do it.',
        new ImageUrl('https://upload.wikimedia.org/wikipedia/commons/thumb/3/31/Webysther_20160423_-_Elephpant.svg/350px-Webysther_20160423_-_Elephpant.svg.png'),
    ),
);
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;

$metadata = $result->getMetadata();
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
