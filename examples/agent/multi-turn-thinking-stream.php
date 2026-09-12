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
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

require_once dirname(__DIR__).'/bootstrap.php';

// The streaming counterpart of multi-turn-thinking.php: a streamed round returns the same result a
// buffered one does.
$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$toolbox = new Toolbox([clock_tool()], logger: logger());
$agent = new Agent($platform, 'claude-sonnet-4-5-20250929', toolbox: $toolbox);

$options = [
    'stream' => true,
    'max_tokens' => 4000,
    'thinking' => ['type' => 'enabled', 'budget_tokens' => 1024],
];

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
);

$messages->add(Message::ofUser('What time is it right now?'));
$execution = $agent->call($messages, $options);

output()->write('<info>Turn 1:</info> ');
foreach ($execution->asStream() as $delta) {
    if ($delta instanceof TextDelta) {
        output()->write($delta->getText());
    }
}
output()->writeln('');

$messages->add(Message::ofAssistant($execution->getResult()));

$messages->add(Message::ofUser('What did I just ask you?'));
$execution = $agent->call($messages, $options);

output()->write('<info>Turn 2:</info> ');
foreach ($execution->asStream() as $delta) {
    if ($delta instanceof TextDelta) {
        output()->write($delta->getText());
    }
}
output()->writeln('');

$messages->add(Message::ofAssistant($execution->getResult()));

print_conversation_shape($messages);
