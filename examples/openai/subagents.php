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
use Symfony\AI\Agent\Toolbox\Tool\Subagent;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

// Create a specialized agent for mathematical calculations
$mathAgent = new Agent(
    $platform,
    'gpt-4o-mini',
    [new SystemPromptInputProcessor('You are a mathematical calculator. When given a math problem, solve it and return only the numerical result with a brief explanation.')],
);

// Create a specialized agent for unit conversions
$conversionAgent = new Agent(
    $platform,
    'gpt-4o-mini',
    [new SystemPromptInputProcessor('You are a unit conversion specialist. Convert values between different units of measurement and return the result with a brief explanation.')],
);

$mathTool = new Subagent($mathAgent);
$conversionTool = new Subagent($conversionAgent);

$memoryFactory = new MemoryToolFactory();
$memoryFactory->addTool(
    $mathTool,
    'calculate',
    'Performs mathematical calculations. Use this when you need to solve math problems or do arithmetic.',
);
$memoryFactory->addTool(
    $conversionTool,
    'convert_units',
    'Converts values between units of measurement (e.g. km to miles, kg to pounds, Celsius to Fahrenheit).',
);

// Create the main orchestrating agent with both subagents as tools
$toolbox = new Toolbox([$mathTool, $conversionTool], toolFactory: $memoryFactory, logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor]);

// Ask a question that requires both calculation and conversion
$messages = new MessageBag(Message::ofUser('I drove 150 kilometers. How many miles is that? Also, what is 150 divided by 8?'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
