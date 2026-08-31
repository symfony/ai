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
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// With "store" => false the Responses API keeps its encrypted reasoning items across the tool call
// only if the whole assistant turn comes back, carried as the signature of a Thinking part.
$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$toolbox = new Toolbox([clock_tool()], logger: logger());
$agent = new Agent($platform, 'gpt-5-mini', toolbox: $toolbox);

$options = [
    'store' => false,
    'include' => ['reasoning.encrypted_content'],
    'reasoning' => ['effort' => 'low', 'summary' => 'auto'],
];

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
);

$messages->add(Message::ofUser('What time is it right now?'));
$execution = $agent->call($messages, $options);

$assistant = Message::ofAssistant($execution->getResult());
output()->writeln('<info>Turn 1:</info> '.$assistant->asText());
$messages->add($assistant);

$messages->add(Message::ofUser('What did I just ask you?'));
$execution = $agent->call($messages, $options);

$assistant = Message::ofAssistant($execution->getResult());
output()->writeln('<info>Turn 2:</info> '.$assistant->asText());
$messages->add($assistant);

print_conversation_shape($messages);
