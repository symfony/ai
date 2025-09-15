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
use Symfony\AI\Agent\MultiAgent\HandoffRule;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Create orchestrator agent for routing decisions
$orchestrator = new Agent(
    $platform,
    new Gpt(Gpt::GPT_4O_MINI),
    [new SystemPromptInputProcessor('You are an intelligent agent orchestrator that routes user questions to specialized agents.')],
    logger: logger()
);

// Create technical agent for handling technical issues
$technical = new Agent(
    $platform,
    new Gpt(Gpt::GPT_4O_MINI),
    [new SystemPromptInputProcessor('You are a technical support specialist. Help users resolve bugs, problems, and technical errors.')],
    name: 'technical',
    logger: logger()
);

// Create general agent for handling any other questions
$general = new Agent(
    $platform,
    new Gpt(Gpt::GPT_4O_MINI),
    [new SystemPromptInputProcessor('You are a helpful general assistant. Assist users with any questions or tasks they may have. You should neverr ever answer technical question.')],
    name: 'general',
    logger: logger()
);

// Create multi-agent
$multiAgent = new MultiAgent(
    orchestrator: $orchestrator,
    agents: [$technical, $general],
    rules: [
        new HandoffRule(agentName: 'technical', triggers: ['bug', 'problem', 'technical', 'error']),
        new HandoffRule(agentName: 'general', triggers: []),
    ],
    logger: logger()
);

echo "=== Technical Question ===\n";
$messages = new MessageBag(
    Message::ofUser('I get this error in my php code: "Call to undefined method App\Controller\UserController::getName()" - this is my line of code: $user->getName() where $user is an instance of User entity.')
);
$result = $multiAgent->call($messages);
echo substr($result->getContent(), 0, 300).'...'.\PHP_EOL.\PHP_EOL;

echo "=== General Question ===\n";
$messages = new MessageBag(
    Message::ofUser('Can you give me a lasagne recipe?')
);
$result = $multiAgent->call($messages);
echo substr($result->getContent(), 0, 300).'...'.\PHP_EOL;
