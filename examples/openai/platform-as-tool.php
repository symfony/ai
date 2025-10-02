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
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\Platform as PlatformTool;
use Symfony\AI\Agent\Toolbox\ToolFactory\ChainFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Create a specialized OpenAI platform tool using gpt-4o for complex analysis
$analysisTool = new PlatformTool($platform, 'gpt-4o');

// Use MemoryToolFactory to register the tool with metadata
$memoryFactory = new MemoryToolFactory();
$memoryFactory->addTool(
    $analysisTool,
    'advanced_analysis',
    'Performs deep analysis and complex reasoning tasks using GPT-4o. Use this for tasks requiring sophisticated understanding.',
);

// Combine with ReflectionToolFactory using ChainFactory
$chainFactory = new ChainFactory([
    $memoryFactory,
    new ReflectionToolFactory(),
]);

// Create the main agent with gpt-4o-mini but with gpt-4o available as a tool
$toolbox = new Toolbox([$analysisTool], toolFactory: $chainFactory, logger: logger());
$processor = new AgentProcessor($toolbox);
$agent = new Agent($platform, 'gpt-4o-mini', [$processor], [$processor], logger: logger());

// Ask a question that could benefit from advanced analysis
$messages = new MessageBag(Message::ofUser('Analyze the philosophical implications of artificial consciousness and whether current AI systems exhibit any form of genuine understanding. Provide a detailed analysis.'));
$result = $agent->call($messages);

echo $result->getContent().\PHP_EOL;
