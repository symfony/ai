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
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('ANTHROPIC_API_KEY'), httpClient: http_client());

$toolbox = new Toolbox([new Clock()], logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'claude-sonnet-4-5-20250929', [$processor], [$processor]);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
);

// plain chat
$messages->add(Message::ofUser('What is the capital of France?'));
$result = $agent->call($messages);
echo 'Turn 1: '.$result->getContent().\PHP_EOL;
$messages->add(Message::ofAssistant($result));

// tool call
$messages->add(Message::ofUser('What time is it right now?'));
$result = $agent->call($messages);
echo 'Turn 2: '.$result->getContent().\PHP_EOL;
$messages->add(Message::ofAssistant($result));

// another chat - the previous assistant turn (tool call + final answer) must be replayed via the
// Anthropic AssistantMessageNormalizer, including any tool_use / tool_result / thinking blocks.
$messages->add(Message::ofUser('What was the first question I asked you?'));
$result = $agent->call($messages);
echo 'Turn 3: '.$result->getContent().\PHP_EOL;
