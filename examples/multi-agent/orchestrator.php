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
use Symfony\AI\Agent\MultiAgent\Handoff;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);

// Create orchestrator agent for routing decisions
$orchestrator = new Agent(
    $platform,
    'gpt-4o-mini',
    [new SystemPromptInputProcessor('You are an intelligent agent orchestrator that routes user questions to specialized agents.')],
);

// Create technical agent for handling technical issues
$technical = new Agent(
    $platform,
    'gpt-4o-mini?max_output_tokens=150', // set max_output_tokens here to be faster and cheaper
    [new SystemPromptInputProcessor('You are a technical support specialist. Help users resolve bugs, problems, and technical errors.')],
    name: 'technical',
);

// Create general agent for handling any other questions
$fallback = new Agent(
    $platform,
    'gpt-4o-mini',
    [new SystemPromptInputProcessor('You are a helpful general assistant. Assist users with any questions or tasks they may have. You should never ever answer technical question.')],
    name: 'fallback',
);

$multiAgent = new MultiAgent(
    orchestrator: $orchestrator,
    handoffs: [
        new Handoff(to: $technical, when: ['bug', 'problem', 'technical', 'error']),
    ],
    fallback: $fallback,
    logger: logger()
);

echo "=== Technical Question ===\n";
$technicalQuestion = 'I get this error in my php code: "Call to undefined method App\Controller\UserController::getName()" - this is my line of code: $user->getName() where $user is an instance of User entity.';
echo "Question: $technicalQuestion\n\n";
$messages = new MessageBag(Message::ofUser($technicalQuestion));
$result = $multiAgent->call($messages);
echo 'Answer: '.substr($result->getContent(), 0, 300).'...'.\PHP_EOL.\PHP_EOL;

echo "=== General Question ===\n";
$generalQuestion = 'Can you give me a lasagne recipe?';
echo "Question: $generalQuestion\n\n";
$messages = new MessageBag(Message::ofUser($generalQuestion));
$result = $multiAgent->call($messages);
echo 'Answer: '.substr($result->getContent(), 0, 300).'...'.\PHP_EOL;
