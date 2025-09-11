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
use Symfony\AI\Agent\MultiAgent\HandoffRule;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$model = new Gpt(Gpt::GPT_4O_MINI);

// Create orchestrator agent for routing decisions
$orchestrator = new Agent(
    $platform,
    $model,
    systemMessage: Message::forSystem('You are an intelligent agent orchestrator that routes user questions to specialized agents.'),
    logger: logger()
);

// Create technical agent for handling technical issues
$technicalAgent = new Agent(
    $platform,
    $model,
    systemMessage: Message::forSystem('You are a technical support specialist. Help users resolve bugs, problems, and technical errors.'),
    logger: logger()
);

// Create general agent for handling any other questions
$generalAgent = new Agent(
    $platform,
    $model,
    systemMessage: Message::forSystem('You are a helpful general assistant. Assist users with any questions or tasks they may have.'),
    logger: logger()
);

// Define handoff rules
$rules = [
    new HandoffRule('technical', ['bug', 'problem', 'technical', 'error']),
    new HandoffRule('general', []), // Empty triggers for general agent
];

// Create multi-agent orchestrator
$multiAgent = new MultiAgent(
    $orchestrator,
    ['technical' => $technicalAgent, 'general' => $generalAgent],
    $rules
);

// Test with a technical question
echo "=== Technical Question ===\n";
$messages = new MessageBag(
    Message::ofUser('I have a bug in my PHP code where the array is not being sorted properly. Can you help?')
);
$result = $multiAgent->call($messages);
echo $result->getContent().PHP_EOL.PHP_EOL;

// Test with a general question
echo "=== General Question ===\n";
$messages = new MessageBag(
    Message::ofUser('What is the weather like today?')
);
$result = $multiAgent->call($messages);
echo $result->getContent().PHP_EOL;