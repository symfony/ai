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
use Symfony\AI\Agent\Workflow\Executor\CallableExecutor;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = Factory::createPlatform(env('OPENAI_API_KEY'), http_client());

// Three specialized agents that will run concurrently on the same topic.
$summaryAgent = new Agent($platform, 'gpt-4o-mini', [
    new SystemPromptInputProcessor('Summarize the given topic in two concise sentences.'),
]);
$prosAgent = new Agent($platform, 'gpt-4o-mini', [
    new SystemPromptInputProcessor('List three key advantages of the given topic as short bullet points.'),
]);
$consAgent = new Agent($platform, 'gpt-4o-mini', [
    new SystemPromptInputProcessor('List three key drawbacks of the given topic as short bullet points.'),
]);

// Definition with an AND-split: "fan_out" forks "start" into three concurrent
// branches, "join" merges them back once every branch has completed.
$builder = new DefinitionBuilder();
$builder
    ->addPlaces(['start', 'summary', 'pros', 'cons', 'report'])
    ->addTransition(new Transition('fan_out', 'start', ['summary', 'pros', 'cons']))
    ->addTransition(new Transition('join', ['summary', 'pros', 'cons'], 'report'))
    ->setInitialPlaces(['start']);

$workflow = new Workflow(
    $builder->build(),
    // AND-splits keep several places marked at once, so single-state must be off.
    new MethodMarkingStore(singleState: false, property: 'marking'),
);

$executors = [
    'start' => new CallableExecutor(static fn (WorkflowStateInterface $state, string $place): WorkflowStateInterface => $state),

    // These three executors wrap agent calls; the ConcurrentExecutionStrategy
    // dispatches them together, so their HTTP requests overlap on the wire.
    'summary' => new AgentExecutor($summaryAgent, inputKey: 'topic', outputKey: 'summary'),
    'pros' => new AgentExecutor($prosAgent, inputKey: 'topic', outputKey: 'pros'),
    'cons' => new AgentExecutor($consAgent, inputKey: 'topic', outputKey: 'cons'),

    'report' => new CallableExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
        $report = sprintf(
            "SUMMARY\n%s\n\nPROS\n%s\n\nCONS\n%s\n",
            $state->get('summary'),
            $state->get('pros'),
            $state->get('cons'),
        );

        return $state->set('report', $report);
    }),
];

// No parallel strategy is passed: AgentWorkflow defaults to ConcurrentExecutionStrategy,
// which runs the three agent branches concurrently instead of one after another.
$agentWorkflow = new AgentWorkflow($workflow, $executors, new WorkflowStateStore());
$initialState = new WorkflowState('fan-out-'.bin2hex(random_bytes(4)), ['topic' => 'Adopting Rust for systems programming']);

echo "=== Parallel Fan-out Workflow ===\n\n";
echo "Topic: {$initialState->get('topic')}\n\n";

$startedAt = microtime(true);
$finalState = $agentWorkflow->run($initialState);
$elapsed = microtime(true) - $startedAt;

echo $finalState->get('report')."\n";
echo 'Branches completed: '.implode(', ', $finalState->getCompletedPlaces())."\n";
echo sprintf("Wall-clock time: %.2fs (close to the slowest branch, not the sum of all three)\n", $elapsed);
