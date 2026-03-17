<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\WorkflowGuardException;
use Symfony\AI\Agent\Workflow\AgentWorkflow;
use Symfony\AI\Agent\Workflow\Executor\FiberExecutor;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\GuardInterface;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

final class AgentWorkflowTest extends TestCase
{
    public function testRunLinearWorkflow()
    {
        $workflow = $this->createLinearWorkflow();
        $store = new WorkflowStateStore();

        $executors = [
            'start' => $this->createExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('step1', 'done')),
            'middle' => $this->createExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('step2', 'done')),
            'end' => $this->createExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('step3', 'done')),
        ];

        $agentWorkflow = new AgentWorkflow($workflow, $executors, $store);
        $finalState = $agentWorkflow->run(new WorkflowState('linear-1'));

        $this->assertSame('done', $finalState->get('step1'));
        $this->assertSame('done', $finalState->get('step2'));
        $this->assertSame('done', $finalState->get('step3'));
        $this->assertNull($finalState->getCurrentPlace());
        $this->assertSame(['start', 'middle', 'end'], $finalState->getCompletedPlaces());
    }

    public function testRunWithConditionalBranching()
    {
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
            'draft' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('draft_text', 'Hello');
            }),
            'review' => new FiberExecutor(static function (WorkflowStateInterface $state, string $place): WorkflowStateInterface {
                return $state->withNextTransition('approve')->set('reviewed', true);
            }),
            'approved' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('status', 'approved');
            }),
            'rejected' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('status', 'rejected');
            }),
        ];

        $store = new WorkflowStateStore();
        $agentWorkflow = new AgentWorkflow($workflow, $executors, $store);
        $finalState = $agentWorkflow->run(new WorkflowState('branch-1'));

        $this->assertSame('approved', $finalState->get('status'));
        $this->assertTrue($finalState->get('reviewed'));
        $this->assertSame(['draft', 'review', 'approved'], $finalState->getCompletedPlaces());
    }

    public function testRunThrowsOnMissingExecutor()
    {
        $workflow = $this->createLinearWorkflow();
        $store = new WorkflowStateStore();

        $agentWorkflow = new AgentWorkflow($workflow, [], $store);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No executor registered for place "start"');
        $this->expectExceptionCode(0);
        $agentWorkflow->run(new WorkflowState('missing-1'));
    }

    public function testRunPersistsStateAfterEachPlace()
    {
        $workflow = $this->createLinearWorkflow();
        $store = new WorkflowStateStore();

        $executors = [
            'start' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('a', 1);
            }),
            'middle' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('b', 2);
            }),
            'end' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('c', 3);
            }),
        ];

        $agentWorkflow = new AgentWorkflow($workflow, $executors, $store);
        $agentWorkflow->run(new WorkflowState('persist-1'));

        $this->assertTrue($store->has('persist-1'));
        $loaded = $store->load('persist-1');
        $this->assertSame(1, $loaded->get('a'));
        $this->assertSame(2, $loaded->get('b'));
        $this->assertSame(3, $loaded->get('c'));
    }

    public function testResumeFromPersistedState()
    {
        $builder = new DefinitionBuilder();
        $builder
            ->addPlaces(['step1', 'step2', 'step3'])
            ->addTransition(new Transition('to_step2', 'step1', 'step2'))
            ->addTransition(new Transition('to_step3', 'step2', 'step3'))
            ->setInitialPlaces(['step1']);

        $workflow = new Workflow(
            $builder->build(),
            new MethodMarkingStore(singleState: true, property: 'marking'),
        );

        $store = new WorkflowStateStore();

        // Simulate a workflow that was interrupted after step1
        $interruptedState = new WorkflowState('resume-1', ['data' => 'initial']);
        $interruptedState->withCompletedPlace('step1');
        $interruptedState->set('step1_result', 'done');
        $store->save($interruptedState);

        $callCount = 0;
        $executors = [
            'step1' => $this->createExecutor(static function (WorkflowStateInterface $state) use (&$callCount): WorkflowStateInterface {
                ++$callCount;

                return $state->set('step1_result', 'done');
            }),
            'step2' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step2_result', 'done');
            }),
            'step3' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step3_result', 'done');
            }),
        ];

        $agentWorkflow = new AgentWorkflow($workflow, $executors, $store);
        $finalState = $agentWorkflow->resume('resume-1');

        // resume reconstructs from last completed place (step1), so it continues from step2
        $this->assertSame('done', $finalState->get('step2_result'));
        $this->assertSame('done', $finalState->get('step3_result'));
    }

    public function testGuardAllowsExecution()
    {
        $workflow = $this->createLinearWorkflow();
        $store = new WorkflowStateStore();

        $executors = [
            'start' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step1', 'done');
            }),
            'middle' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step2', 'done');
            }),
            'end' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step3', 'done');
            }),
        ];

        $guard = $this->createGuard(true);

        $agentWorkflow = new AgentWorkflow($workflow, $executors, $store, guards: [
            'start' => [$guard],
            'middle' => [$guard],
        ]);

        $finalState = $agentWorkflow->run(new WorkflowState('guard-allow'));

        $this->assertSame('done', $finalState->get('step1'));
        $this->assertSame('done', $finalState->get('step2'));
        $this->assertSame('done', $finalState->get('step3'));
    }

    public function testGuardBlocksExecution()
    {
        $workflow = $this->createLinearWorkflow();
        $store = new WorkflowStateStore();

        $executors = [
            'start' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step1', 'done');
            }),
            'middle' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step2', 'done');
            }),
            'end' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step3', 'done');
            }),
        ];

        $agentWorkflow = new AgentWorkflow($workflow, $executors, $store, guards: [
            'middle' => [$this->createGuard(false)],
        ]);

        $this->expectException(WorkflowGuardException::class);
        $this->expectExceptionMessage('Guard rejected execution at place "middle"');

        $agentWorkflow->run(new WorkflowState('guard-block'));
    }

    public function testMultipleGuardsOnSamePlace()
    {
        $workflow = $this->createLinearWorkflow();
        $store = new WorkflowStateStore();

        $executors = [
            'start' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step1', 'done');
            }),
            'middle' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step2', 'done');
            }),
            'end' => $this->createExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                return $state->set('step3', 'done');
            }),
        ];

        // First guard passes, second rejects
        $agentWorkflow = new AgentWorkflow($workflow, $executors, $store, guards: [
            'middle' => [$this->createGuard(true), $this->createGuard(false)],
        ]);

        $this->expectException(WorkflowGuardException::class);
        $this->expectExceptionMessage('Guard rejected execution at place "middle"');

        $agentWorkflow->run(new WorkflowState('guard-multi'));
    }

    public function testGuardOnSpecificPlaceOnly()
    {
        $workflow = $this->createLinearWorkflow();
        $store = new WorkflowStateStore();

        $executorCallLog = [];
        $executors = [
            'start' => $this->createExecutor(static function (WorkflowStateInterface $state) use (&$executorCallLog): WorkflowStateInterface {
                $executorCallLog[] = 'start';

                return $state->set('step1', 'done');
            }),
            'middle' => $this->createExecutor(static function (WorkflowStateInterface $state) use (&$executorCallLog): WorkflowStateInterface {
                $executorCallLog[] = 'middle';

                return $state->set('step2', 'done');
            }),
            'end' => $this->createExecutor(static function (WorkflowStateInterface $state) use (&$executorCallLog): WorkflowStateInterface {
                $executorCallLog[] = 'end';

                return $state->set('step3', 'done');
            }),
        ];

        // Guard only on 'end' place, blocks it — 'start' and 'middle' should execute
        $agentWorkflow = new AgentWorkflow($workflow, $executors, $store, guards: [
            'end' => [$this->createGuard(false)],
        ]);

        try {
            $agentWorkflow->run(new WorkflowState('guard-specific'));
        } catch (WorkflowGuardException) {
            // expected
        }

        $this->assertSame(['start', 'middle'], $executorCallLog);
    }

    private function createGuard(bool $result): GuardInterface
    {
        return new class($result) implements GuardInterface {
            public function __construct(private readonly bool $result)
            {
            }

            public function execute(WorkflowStateInterface $state, string $place): bool
            {
                return $this->result;
            }
        };
    }

    private function createLinearWorkflow(): Workflow
    {
        $builder = new DefinitionBuilder();
        $builder
            ->addPlaces(['start', 'middle', 'end'])
            ->addTransition(new Transition('to_middle', 'start', 'middle'))
            ->addTransition(new Transition('to_end', 'middle', 'end'))
            ->setInitialPlaces(['start']);

        return new Workflow(
            $builder->build(),
            new MethodMarkingStore(singleState: true, property: 'marking'),
        );
    }

    private function createExecutor(\Closure $callback): ExecutorInterface
    {
        return new class($callback) implements ExecutorInterface {
            public function __construct(private readonly \Closure $callback)
            {
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return ($this->callback)($state);
            }
        };
    }
}
