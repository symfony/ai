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
use Symfony\AI\Platform\Bridge\VertexAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once __DIR__.'/bootstrap.php';

$platform = Factory::createPlatform(env('GOOGLE_CLOUD_LOCATION'), env('GOOGLE_CLOUD_PROJECT'), httpClient: adc_aware_http_client());

$toolbox = new Toolbox([new Clock(clock()), new EuropeanCapitalsTool()], logger: logger());
$agent = new Agent($platform, 'gemini-2.5-flash', toolbox: $toolbox);

$messages = new MessageBag(
    Message::forSystem('You are a helpful assistant.'),
);

$messages->add(Message::ofUser('What is the capital of France?'));
$execution = $agent->call($messages);
echo 'Turn 1: '.$execution->asText().\PHP_EOL;
$messages->add(Message::ofAssistant($execution->getResult()));

$messages->add(Message::ofUser('What time is it right now, and which European capitals do you know about via your tools?'));
$execution = $agent->call($messages);
echo 'Turn 2: '.$execution->asText().\PHP_EOL;
$messages->add(Message::ofAssistant($execution->getResult()));

$messages->add(Message::ofUser('What was the first question I asked you?'));
$execution = $agent->call($messages);
echo 'Turn 3: '.$execution->asText().\PHP_EOL;
