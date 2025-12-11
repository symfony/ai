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
use Symfony\AI\Agent\SleepTime\MemoryBlock;
use Symfony\AI\Agent\SleepTime\MemoryBlockProvider;
use Symfony\AI\Agent\SleepTime\SleepTimeAgent;
use Symfony\AI\Agent\SleepTime\Tool\RethinkMemory;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Shared memory blocks between primary and sleeping agents
$memoryBlocks = [
    new MemoryBlock('summary'),
    new MemoryBlock('user_preferences'),
];

// Sleeping agent: uses the rethink_memory tool to update shared memory blocks
$rethinkTool = new RethinkMemory($memoryBlocks);
$sleepToolbox = new Toolbox([$rethinkTool]);
$sleepingAgent = new Agent($platform, 'gpt-4o-mini', [], [new AgentProcessor($sleepToolbox)]);

// Primary agent: gets enriched memory from sleep-time processing via MemoryBlockProvider
$memoryBlockProvider = new MemoryBlockProvider($memoryBlocks);
$primaryAgent = new Agent($platform, 'gpt-4o-mini', [
    new SystemPromptInputProcessor('You are a helpful assistant.'),
    new MemoryInputProcessor([$memoryBlockProvider]),
]);

// SleepTimeAgent: triggers the sleeping agent every 3 calls to enrich memory
$agent = new SleepTimeAgent($primaryAgent, $sleepingAgent, $memoryBlocks, frequency: 3);

$result = $agent->call(new MessageBag(
    Message::ofUser('My name is John and I prefer concise answers.'),
));

echo $result->getContent().\PHP_EOL;
