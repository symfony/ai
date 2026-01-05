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
use Symfony\AI\Agent\Memory\MemoryInputProcessor;
use Symfony\AI\Agent\Memory\StaticMemoryProvider;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create($_ENV['OPENAI_API_KEY'], http_client());

$systemPromptProcessor = new SystemPromptInputProcessor('You are a professional trainer with short, personalized advice and a motivating claim.');

$personalFacts = new StaticMemoryProvider(
    'My name is Wilhelm Tell',
    'I wish to be a swiss national hero',
    'I am struggling with hitting apples but want to be professional with the bow and arrow',
);
$memoryProcessor = new MemoryInputProcessor([$personalFacts]);

$agent = new Agent($platform, 'gpt-4o-mini', [$systemPromptProcessor, $memoryProcessor]);
$messages = new MessageBag(Message::ofUser('What do we do today?'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
