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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\Agent as AgentTool;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Create a specialized agent for mathematical calculations
$mathSystemPrompt = new SystemPromptInputProcessor('You are a mathematical calculator. When given a math problem, solve it and return only the numerical result with a brief explanation.');
$mathAgent = new Agent($platform, 'gpt-4o', [$mathSystemPrompt]);

// Wrap the math agent as a tool
$mathTool = new AgentTool($mathAgent);

// Use MemoryToolFactory to register the tool with metadata
$memoryFactory = new MemoryToolFactory();
$memoryFactory->addTool(
    $mathTool,
    'calculate',
    'Performs mathematical calculations. Use this when you need to solve math problems or do arithmetic.',
);

// Combine with ReflectionToolFactory using ChainFactory
$chainFactory = new ChainFactory([
    $memoryFactory,
    new ReflectionToolFactory(),
]);

// Create the main agent with the math agent as a tool
$toolbox = new Toolbox([$mathTool], toolFactory: $chainFactory, logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor]);

// Ask a question that requires mathematical calculation
$messages = new MessageBag(Message::ofUser('I have 15 apples and I want to share them equally among 4 friends. How many apples does each friend get and how many are left over?'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
