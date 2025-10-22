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
use Symfony\AI\Chat\Bridge\SurrealDb\MessageStore;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// SurrealDb does not require to call the `setup()` method as the table is created during insertion
$store = new MessageStore(
    http_client(),
    'http://127.0.0.1:8000',
    env('SURREALDB_USER'),
    env('SURREALDB_PASS'),
    'default',
    'chat',
    table: 'chat',
);

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
