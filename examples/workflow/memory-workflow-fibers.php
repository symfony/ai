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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Workflow\Step\FiberStepExecutor;
use Symfony\AI\Agent\Workflow\Step\Step;
use Symfony\AI\Agent\Workflow\Store\InMemoryWorkflowStore;
use Symfony\AI\Agent\Workflow\Transition\Transition;
use Symfony\AI\Agent\Workflow\Transition\TransitionRegistry;
use Symfony\AI\Agent\Workflow\WorkflowExecutor;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

require_once dirname(__DIR__).'/bootstrap.php';

// agent
$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());
$agent = new Agent($platform, 'gpt-5.2');

$transitionRegistry = new TransitionRegistry();

// Adding transitions
$transitionRegistry->addTransition(new Transition(
    name: 'to_processing',
    from: 'start',
    to: 'processing',
    guards: [
        static fn ($state): bool => !empty($state->getContext()['input']),
    ],
    beforeCallback: static fn (WorkflowStateInterface $state) => $state->mergeContext(['started_at' => time()]),
    afterCallback: static fn (WorkflowStateInterface $state) => $state->mergeContext(['entered_processing' => time()]),
));

$transitionRegistry->addTransition(new Transition(
    name: 'to_validation',
    from: 'processing',
    to: 'validation',
));

$transitionRegistry->addTransition(new Transition(
    name: 'to_completed',
    from: 'validation',
    to: 'completed',
    guards: [
        static fn ($state): bool => isset($state->getContext()['validated']) && true === $state->getContext()['validated'],
    ],
));

// Executor
$executor = new WorkflowExecutor(
    $transitionRegistry,
    new InMemoryWorkflowStore(),
    new FiberStepExecutor(),
    new EventDispatcher(),
    logger(),
    maxExecutionTime: 600
);

// Adding steps
$executor->addStep(new Step(
    name: 'start',
    executor: static fn (AgentInterface $agent, WorkflowStateInterface $state): ResultInterface => $agent->call(
        new MessageBag(Message::ofUser($state->getContext()['input']))
    ),
    retryCount: 3,
    retryDelay: 1000,
));

$executor->addStep(new Step(
    name: 'processing',
    executor: static function (AgentInterface $agent, $state): ResultInterface {
        $result = $agent->call(
            new MessageBag(Message::ofUser('Process: '.$state->getContext()['last_result']))
        );

        $state->mergeContext(['processed' => true]);

        return $result;
    },
    retryCount: 5,
));

$executor->addStep(new Step(
    name: 'validation',
    executor: static function (AgentInterface $agent, WorkflowStateInterface $state): ResultInterface {
        $result = $agent->call(
            new MessageBag(Message::ofUser('Validate: '.$state->getContext()['last_result']))
        );

        $state->mergeContext(['validated' => true]);

        return $result;
    },
));

$executor->addStep(new Step(
    name: 'completed',
    executor: static function (AgentInterface $agent, WorkflowStateInterface $state): ResultInterface {
        $result = $agent->call(
            new MessageBag(Message::ofUser('Complete: '.$state->getContext()['last_result']))
        );

        $state->mergeContext(['completed' => true]);

        return $result;
    },
));

// Creating and executing the workflow
$state = new WorkflowState(
    id: Uuid::v7()->toRfc4122(),
    currentStep: 'start',
    context: ['input' => 'What is AI?']
);

$result = $executor->execute($agent, $state);

echo $result->getContent().\PHP_EOL;

// $result = $executor->resume($state->getId());
