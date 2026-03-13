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
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

// Create specialized agents for each step
$writerAgent = new Agent($platform, 'gpt-4o-mini', [
    new SystemPromptInputProcessor('You are a creative writer. Write a short paragraph about the given topic.'),
], );

$summarizerAgent = new Agent($platform, 'gpt-4o-mini', [
    new SystemPromptInputProcessor('You are a summarizer. Summarize the given text in one sentence.'),
]);

// Define a linear workflow: generate -> summarize -> done
$builder = new DefinitionBuilder();
$builder
    ->addPlaces(['generate', 'summarize', 'done'])
    ->addTransition(new Transition('to_summarize', 'generate', 'summarize'))
    ->addTransition(new Transition('to_done', 'summarize', 'done'))
    ->setInitialPlaces(['generate']);

$workflow = new Workflow(
    $builder->build(),
    new MethodMarkingStore(singleState: true, property: 'marking'),
);

// Map each place to an executor
$executors = [
    'generate' => new AgentExecutor($writerAgent, inputKey: 'topic', outputKey: 'draft'),
    'summarize' => new AgentExecutor($summarizerAgent, inputKey: 'draft', outputKey: 'summary'),
    'done' => new FiberExecutor(static fn (WorkflowStateInterface $state, string $place): WorkflowStateInterface => $state->set('completed_at', (new MonotonicClock())->now()->format('Y-m-d H:i:s'))),
];

// Run the workflow
$agentWorkflow = new AgentWorkflow($workflow, $executors, new WorkflowStateStore());
$initialState = new WorkflowState(Uuid::v7()->toRfc4122(), ['topic' => 'The future of space exploration']);

echo "=== Linear Workflow ===\n\n";
echo "Topic: {$initialState->get('topic')}\n\n";

$finalState = $agentWorkflow->run($initialState);

echo "Draft:\n{$finalState->get('draft')}\n\n";
echo "Summary:\n{$finalState->get('summary')}\n\n";
echo "Completed at: {$finalState->get('completed_at')}\n";
echo 'Steps completed: '.implode(' -> ', $finalState->getCompletedPlaces())."\n";
