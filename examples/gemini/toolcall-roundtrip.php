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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

$toolbox = new Toolbox([new Clock()], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gemini-2.5-flash', [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
);

// plain chat
$messages->add(Message::ofUser('What is the capital of France?'));
$result = $agent->call($messages);
echo 'Turn 1: '.$result->getContent().PHP_EOL;
$messages->add(Message::ofAssistant($result->getContent()));

// tool call
$messages->add(Message::ofUser('What time is it right now?'));
$result = $agent->call($messages);
echo 'Turn 2: '.$result->getContent().PHP_EOL;
$messages->add(Message::ofAssistant($result->getContent()));

// another chat with tool result in MessageBag
$messages->add(Message::ofUser('What was the first question I asked you?'));
$result = $agent->call($messages);
echo 'Turn 3: '.$result->getContent().PHP_EOL;
