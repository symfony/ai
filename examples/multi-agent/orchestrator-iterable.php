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
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Agent\InputProcessor\SystemPromptInputProcessor;
use Symfony\AI\Agent\MultiAgent\Handoff;
use Symfony\AI\Agent\MultiAgent\Handoff\Decision;
use Symfony\AI\Agent\MultiAgent\MultiAgent;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__).'/bootstrap.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());
$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client(), eventDispatcher: $dispatcher);

$orchestrator = new Agent(
    $platform,
    'gpt-5-mini',
    [new SystemPromptInputProcessor('You are an intelligent agent orchestrator that routes user questions to specialized agents.')],
);

$technical = new Agent(
    $platform,
    'gpt-4o-mini?max_output_tokens=150', // set max_output_tokens here to be faster and cheaper
    [new SystemPromptInputProcessor('You are a technical support specialist. Help users resolve bugs, problems, and technical errors.')],
    name: 'technical',
);

$fallback = new Agent(
    $platform,
    'gpt-5-mini',
    [new SystemPromptInputProcessor('You are a helpful general assistant. Assist users with any questions or tasks they may have. You should never ever answer technical question.')],
    name: 'fallback',
);

$multiAgent = new MultiAgent(
    orchestrator: $orchestrator,
    handoffs: [
        new Handoff(to: $technical, when: ['bug', 'problem', 'technical', 'error']),
    ],
    fallback: $fallback,
);

$question = 'I get this error in my php code: "Call to undefined method App\Controller\UserController::getName()" - this is my line of code: $user->getName() where $user is an instance of User entity.';
echo "Question: $question".\PHP_EOL.\PHP_EOL;

// iterating the multi-agent reports the routing decision and the steps of the agent it delegates to
foreach ($multiAgent->call(new MessageBag(Message::ofUser($question))) as $update) {
    if ($update instanceof Progress && 'handoff' === $update->getStage()) {
        $decision = $update->getPayload();
        assert($decision instanceof Decision);

        echo '>> '.$update->getMessage().' Reason: '.$decision->getReasoning().\PHP_EOL;

        continue;
    }

    if ($update instanceof Progress) {
        // the orchestrator's routing round reports here as well, before the handoff
        echo '   '.$update->getMessage().\PHP_EOL;
    }

    if ($update instanceof Result) {
        echo \PHP_EOL.'Answer: '.substr($update->getResult()->getContent(), 0, 300).'...'.\PHP_EOL;
    }
}
