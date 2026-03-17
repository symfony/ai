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
use Symfony\AI\Agent\Workflow\AgentWorkflow;
use Symfony\AI\Agent\Workflow\Executor\AgentExecutor;
use Symfony\AI\Agent\Workflow\Executor\FiberExecutor;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$writerAgent = new Agent(
    $platform,
    'gpt-4o-mini',
    [new SystemPromptInputProcessor('You are a writer. Write a short paragraph about the given topic.')],
);

$reviewerAgent = new Agent(
    $platform,
    'gpt-4o-mini',
    [new SystemPromptInputProcessor('You are a strict content reviewer. Reply ONLY with "APPROVED" or "REJECTED" followed by a brief reason.')],
);

// Define workflow with conditional branching: draft -> review -> approved or rejected
$builder = new DefinitionBuilder();
$builder
    ->addPlaces(['draft', 'review', 'approved', 'rejected'])
    ->addTransition(new Transition('to_review', 'draft', 'review'))
    ->addTransition(new Transition('approve', 'review', 'approved'))
    ->addTransition(new Transition('reject', 'review', 'rejected'))
    ->setInitialPlaces(['draft']);

$workflow = new Workflow(
    $builder->build(),
    new MethodMarkingStore(singleState: true, property: 'marking'),
);

$executors = [
    'draft' => new AgentExecutor($writerAgent, inputKey: 'topic', outputKey: 'draft_text'),

    // The review step uses FiberExecutor for conditional branching
    'review' => new FiberExecutor(static function (WorkflowStateInterface $state, string $place) use ($reviewerAgent): WorkflowStateInterface {
        $messages = new MessageBag(Message::ofUser('Review this content: '.$state->get('draft_text')));
        $result = $reviewerAgent->call($messages);
        $feedback = $result->getContent();

        $state->set('review_feedback', $feedback);

        // Choose the transition based on the reviewer's response
        if (str_contains(strtoupper($feedback), 'APPROVED')) {
            return $state->withNextTransition('approve');
        }

        return $state->withNextTransition('reject');
    }),

    'approved' => new FiberExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
        return $state->set('status', 'published');
    }),

    'rejected' => new FiberExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
        return $state->set('status', 'needs_revision');
    }),
];

// Run the workflow
$agentWorkflow = new AgentWorkflow($workflow, $executors, new WorkflowStateStore());
$initialState = new WorkflowState('review-'.bin2hex(random_bytes(4)), ['topic' => 'Benefits of open source software']);

echo "=== Conditional Branching Workflow ===\n\n";
echo "Topic: {$initialState->get('topic')}\n\n";

$finalState = $agentWorkflow->run($initialState);

echo "Draft:\n{$finalState->get('draft_text')}\n\n";
echo "Review feedback:\n{$finalState->get('review_feedback')}\n\n";
echo "Final status: {$finalState->get('status')}\n";
echo 'Path taken: '.implode(' -> ', $finalState->getCompletedPlaces())."\n";
