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
use Symfony\AI\Chat\Bridge\Pogocache\MessageStore;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$store = new MessageStore(http_client(), env('POGOCACHE_HOST'), env('POGOCACHE_PASSWORD'));
$store->setup();

$agent = new Agent($platform, 'gpt-4o-mini');
$chat = new Chat($agent, $store);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
);

$chat->initiate($messages);
$chat->submit(Message::ofUser('My name is Christopher.'));
$message = $chat->submit(Message::ofUser('What is my name?'));

echo $message->getContent().\PHP_EOL;
