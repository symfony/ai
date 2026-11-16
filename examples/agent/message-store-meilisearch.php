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
use Symfony\AI\Agent\Bridge\Meilisearch\MessageStore;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$store = new MessageStore(http_client(), 'http://127.0.0.1:7700', env('MEILISEARCH_API_KEY'), new MonotonicClock());
$store->setup();

$agent = new Agent(
    $platform,
    'gpt-5-mini',
    [new SystemPromptInputProcessor('You are a helpful assistant. You only answer with short sentences.')],
    messageStore: $store,
);

// each request only sends the new message, the agent continues the stored conversation by its ID
$conversationId = Uuid::v7();

foreach (['My name is Christopher.', 'What is my name?'] as $question) {
    echo $agent->call((new MessageBag(Message::ofUser($question)))->withId($conversationId))->asText().\PHP_EOL;
}
