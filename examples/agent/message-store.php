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
use Symfony\AI\Agent\Bridge\Clock\Clock;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());
$store = new InMemoryMessageStore();
$agent = new Agent(
    $platform,
    'gpt-5-mini',
    [new SystemPromptInputProcessor('You are a helpful assistant. Answer in one sentence.')],
    toolbox: new Toolbox([new Clock(clock())], logger: logger()),
    messageStore: $store,
);

// every request only sends its new message, the agent continues the stored conversation by its ID
$conversationId = Uuid::v7();

foreach (['My name is Wilhelm Tell.', 'What time is it?', 'What is my name, and what did you tell me about the time?'] as $question) {
    $answer = $agent->call(MessageBag::forId($conversationId, $question))->asText();

    output()->writeln(sprintf('<comment>User:</comment> %s', $question));
    output()->writeln(sprintf('<info>Assistant:</info> %s', $answer));
}

print_conversation_shape($store->load($conversationId));
