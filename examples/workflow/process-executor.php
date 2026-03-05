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
use Symfony\AI\Agent\Workflow\Executor\ProcessExecutor;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

require_once dirname(__DIR__).'/bootstrap.php';

$platform = PlatformFactory::create(env('OPENAI_API_KEY'), http_client());

$writerAgent = new Agent(
    $platform,
    'gpt-4o-mini',
    [new SystemPromptInputProcessor('You are a writer. Write a short paragraph (50-100 words) about the given topic.')],
);

// Define workflow: generate -> count_words -> report
$builder = new DefinitionBuilder();
$builder
    ->addPlaces(['generate', 'count_words', 'report'])
    ->addTransition(new Transition('to_count', 'generate', 'count_words'))
    ->addTransition(new Transition('to_report', 'count_words', 'report'))
    ->setInitialPlaces(['generate']);

$workflow = new Workflow(
    $builder->build(),
    new MethodMarkingStore(singleState: true, property: 'marking'),
);

$executors = [
    // AgentExecutor: generate content with an AI agent
    'generate' => new AgentExecutor($writerAgent, inputKey: 'topic', outputKey: 'content'),

    // ProcessExecutor: use shell command to count words (dynamic command from state)
    'count_words' => new ProcessExecutor(
        command: static function (WorkflowStateInterface $state, string $place): array {
            // Write content to a temp file and count words
            $tmpFile = tempnam(sys_get_temp_dir(), 'wf_');
            file_put_contents($tmpFile, $state->get('content'));

            return ['wc', '-w', $tmpFile];
        },
        outputKey: 'word_count_raw',
    ),

    // FiberExecutor: aggregate results into a final report
    'report' => new FiberExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
        $wordCount = trim($state->get('word_count_raw'));

        return $state->set('report', sprintf(
            "Content generated: %d words\nTopic: %s",
            (int) $wordCount,
            $state->get('topic'),
        ));
    }),
];

// Run the workflow
$agentWorkflow = new AgentWorkflow($workflow, $executors, new WorkflowStateStore());
$initialState = new WorkflowState('mixed-'.bin2hex(random_bytes(4)), ['topic' => 'The impact of artificial intelligence on healthcare']);

echo "=== Mixed Executors Workflow ===\n\n";

$finalState = $agentWorkflow->run($initialState);

echo "Generated content:\n{$finalState->get('content')}\n\n";
echo "Report:\n{$finalState->get('report')}\n\n";
echo 'Steps completed: '.implode(' -> ', $finalState->getCompletedPlaces())."\n";
