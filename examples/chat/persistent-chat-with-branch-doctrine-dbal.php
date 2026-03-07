<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\DBAL\DriverManager;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Chat\Bridge\Doctrine\DoctrineDbalMessageStore;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

$store = new DoctrineDbalMessageStore('symfony', $connection);
$store->setup();

$agent = new Agent($platform, 'gpt-4o-mini');
$chat = new Chat($agent, $store);

$chat->initiate(new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
));
$chat->submit(Message::ofUser('My name is Christopher.'));

$branchedChat = $chat->branch('_branched_for_oskar');
$branchedChat->submit(Message::ofUser('Made a mistake about my name, my name is Oskar'));

$firstMessage = $chat->submit(Message::ofUser('What is my name?'));
$branchedMessage = $branchedChat->submit(Message::ofUser('What is my name?'));

echo sprintf('First chat: "%s"', $firstMessage->getContent()).\PHP_EOL;
echo sprintf('Forked chat: "%s"', $branchedMessage->getContent()).\PHP_EOL;
