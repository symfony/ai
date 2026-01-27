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
use Symfony\AI\Chat\Bridge\Meilisearch\MessageStore;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$store = new MessageStore(http_client(), env('MEILISEARCH_HOST'), env('MEILISEARCH_API_KEY'));
$store->setup();

$agent = new Agent($platform, 'gpt-4o-mini');
$chat = new Chat($agent, $store);

$chat->initiate(new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
));
$chat->submit(Message::ofUser('My name is Christopher.'));

$forkedChat = $chat->branch('_forked_for_oskar');
$forkedChat->submit(Message::ofUser('Made a mistake about my name, my name is Oskar'));

$firstMessage = $chat->submit(Message::ofUser('What is my name?'));
$forkedMessage = $forkedChat->submit(Message::ofUser('What is my name?'));

echo sprintf('First chat: "%s"', $firstMessage->getContent()).\PHP_EOL;
echo sprintf('Forked chat: "%s"', $forkedMessage->getContent()).\PHP_EOL;
