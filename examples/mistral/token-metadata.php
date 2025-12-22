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
use Symfony\AI\Platform\Bridge\Mistral\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('MISTRAL_API_KEY'), http_client());

$agent = new Agent($platform, 'mistral-large-latest');

$messages = new MessageBag(
    Message::forSystem('You are a pirate, you write funny and only with one sentence.'),
    Message::ofUser('What is the best French cuisine?'),
);

$result = $agent->call($messages, [
    'temperature' => 0.3,
    'max_tokens' => 500,
]);

print_token_usage($result->getMetadata()->get('token_usage'));
