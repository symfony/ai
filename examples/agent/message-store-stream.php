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
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$agent = new Agent(
    $platform,
    'gpt-4o-mini',
    [new SystemPromptInputProcessor('You are a helpful assistant. You only answer with short sentences.')],
    messageStore: new InMemoryMessageStore(),
);

$conversationId = Uuid::v7();

echo $agent->call(MessageBag::forId($conversationId, 'My name is Christopher.'))->asText().\PHP_EOL;

// the agent saves the streamed answer once the stream is consumed
foreach ($agent->call(MessageBag::forId($conversationId, 'Tell me a short story about the sun and use my name.'), ['stream' => true])->asStream() as $delta) {
    if ($delta instanceof TextDelta) {
        echo $delta->getText();
    }
}
echo \PHP_EOL;
