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
use Symfony\AI\Platform\Bridge\Perplexity\PlatformFactory;
use Symfony\AI\Platform\Bridge\Perplexity\SearchResultProcessor;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('PERPLEXITY_API_KEY'), http_client());
$agent = new Agent($platform, 'sonar', outputProcessors: [new SearchResultProcessor()], logger: logger());

$messages = new MessageBag(Message::ofUser('What is the best French cheese of the first quarter-century of 21st century?'));
$response = $agent->call($messages, [
    'search_mode' => 'academic',
    'search_after_date_filter' => '01/01/2000',
    'search_before_date_filter' => '01/01/2025',
]);

echo $response->getContent().\PHP_EOL;
echo \PHP_EOL;

perplexity_print_search_results($response->getMetadata());
perplexity_print_citations($response->getMetadata());
