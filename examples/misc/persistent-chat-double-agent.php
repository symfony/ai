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
use Symfony\AI\Agent\Chat;
use Symfony\AI\Agent\Chat\MessageStore\InMemoryStore;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$llm = new Gpt(Gpt::GPT_4O_MINI);

$firstAgent = new Agent($platform, $llm, logger: logger());
$secondAgent = new Agent($platform, $llm, logger: logger());

$store = new InMemoryStore();

$firstChat = new Chat($firstAgent, $store);
$secondChat = new Chat($secondAgent, $store);

$firstChat->initiate(new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
));
$secondChat->initiate(new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
));

$firstChat->submit(Message::ofUser('My name is Christopher.'));
$firstChatMessage = $firstChat->submit(Message::ofUser('What is my name?'));
$secondChatMessage = $secondChat->submit(Message::ofUser('What is my name?'));

echo $firstChatMessage->content.\PHP_EOL;
echo $secondChatMessage->content.\PHP_EOL;
