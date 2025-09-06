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

$messages = new MessageBag(Message::ofUser('What is the best French cheese?'));
$response = $agent->call($messages, [
    'search_domain_filter' => [
        'https://en.wikipedia.org/wiki/Cheese',
    ],
    'search_mode' => 'web',
    'enable_search_classifier' => true,
    'search_recency_filter' => 'week',
]);

echo $response->getContent().\PHP_EOL;
echo \PHP_EOL;
