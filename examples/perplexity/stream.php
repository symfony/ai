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
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('PERPLEXITY_API_KEY'), http_client());
$model = new Perplexity('sonar');
$agent = new Agent($platform, $model, outputProcessors: [new SearchResultProcessor()], logger: logger());

$messages = new MessageBag(
    Message::forSystem('You are a thoughtful philosopher.'),
    Message::ofUser('What is the purpose of an ant?'),
);
$result = $agent->call($messages, [
    'stream' => true,
]);

foreach ($result->getContent() as $word) {
    echo $word;
}
echo \PHP_EOL;

perplexity_print_search_results($result->getMetadata());
perplexity_print_citations($result->getMetadata());
