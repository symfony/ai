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
use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

// Mistral wants the reasoning trace back as a thinking chunk of the content list, and rejects the
// `reasoning_content` of the OpenAI-compatible convention with a 422.
$platform = Factory::createPlatform(env('MISTRAL_API_KEY'), http_client());

$toolbox = new Toolbox([clock_tool()], logger: logger());
$agent = new Agent($platform, 'mistral-medium-2604', toolbox: $toolbox);

$options = ['reasoning_effort' => 'high'];

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
