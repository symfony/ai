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
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Fixtures\EuropeanCapitalsTool;
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('GEMINI_API_KEY'), http_client());

$toolbox = new Toolbox([new Clock(clock()), new EuropeanCapitalsTool()], logger: logger());
$agent = new Agent($platform, 'gemini-3.1-pro-preview', toolbox: $toolbox);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
);

// plain chat
$execution = $agent->call($messages->with(Message::ofUser('What is the capital of France?')));
echo 'Turn 1: '.$execution->asText().\PHP_EOL;

// parallel tool calls – Clock returns a scalar string, EuropeanCapitalsTool returns a list
// (the latter must be wrapped as a Protobuf Struct on the function_response leg)
$execution = $agent->call($execution->getMessages()->with(Message::ofUser('What time is it right now, and which European capitals do you know about via your tools?')));
echo 'Turn 2: '.$execution->asText().\PHP_EOL;

// another chat with tool results in MessageBag
$execution = $agent->call($execution->getMessages()->with(Message::ofUser('What was the first question I asked you?')));
echo 'Turn 3: '.$execution->asText().\PHP_EOL;
