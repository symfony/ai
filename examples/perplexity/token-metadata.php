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
use Symfony\AI\Platform\Bridge\Perplexity\TokenOutputProcessor;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('PERPLEXITY_API_KEY'), http_client());
$model = new Perplexity();
$agent = new Agent($platform, $model, outputProcessors: [new TokenOutputProcessor()], logger: logger());

$messages = new MessageBag(
    Message::forSystem('You are a pirate and you write funny.'),
    Message::ofUser('What is the Symfony framework?'),
);
$result = $agent->call($messages, [
    'model' => Perplexity::SONAR_DEEP_RESEARCH,
    'max_tokens' => 500, // specific options just for this call
]);

print_token_usage($result->getMetadata());
