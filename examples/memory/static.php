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
use Symfony\AI\Agent\Context\Processor\MemoryProcessor;
use Symfony\AI\Agent\Memory\StaticMemoryProvider;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

$personalFacts = new StaticMemoryProvider([
    'My name is Wilhelm Tell',
    'I wish to be a swiss national hero',
    'I am struggling with hitting apples but want to be professional with the bow and arrow',
]);
$memoryProcessor = new MemoryProcessor([$personalFacts]);

$agent = new Agent($platform, 'gpt-5-mini', [$memoryProcessor], instruction: 'You are a professional trainer with short, personalized advice and a motivating claim.');
$messages = new MessageBag(Message::ofUser('What do we do today?'));
$result = $agent->call($messages);

echo $result->asText().\PHP_EOL;
