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

$agent = new Agent($platform, $llm, logger: logger());

$store = new InMemoryStore();

$firstChat = new Chat($agent, $store);
$secondChat = new Chat($agent, $store);

$firstChat->initiate(new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
), '_first_chat');
$secondChat->initiate(new MessageBag(
    Message::forSystem('You are a helpful assistant. You only answer with short sentences.'),
), '_second_chat');

$firstChat->submit(Message::ofUser('My name is Christopher.'));
$firstChatMessage = $firstChat->submit(Message::ofUser('What is my name?'));

$secondChat->submit(Message::ofUser('My name is William.'));
$secondChatMessage = $secondChat->submit(Message::ofUser('What is my name?'));

$firstChatMessageContent = $firstChatMessage->content;
$secondChatMessageContent = $secondChatMessage->content;

echo $firstChatMessageContent.\PHP_EOL;
echo $secondChatMessageContent.\PHP_EOL;

assert(str_contains($firstChatMessageContent, 'Christopher'));
assert(!str_contains($secondChatMessageContent, 'Christopher'));
