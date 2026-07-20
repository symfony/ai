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
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Exception\WorkflowGuardException;
use Symfony\AI\Agent\Exception\WorkflowLockedException;
use Symfony\AI\Agent\Exception\WorkflowMaxStepsExceededException;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\AbstractGuard;
use Symfony\AI\Agent\Workflow\AgentWorkflow;
use Symfony\AI\Agent\Workflow\Event\PlaceCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\PlaceEnteredEvent;
use Symfony\AI\Agent\Workflow\Event\TransitionAppliedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowFailedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowStartedEvent;
use Symfony\AI\Agent\Workflow\Executor\CallableExecutor;
use Symfony\AI\Agent\Workflow\GuardInterface;
use Symfony\AI\Agent\Workflow\InMemory\WorkflowStateStore;
use Symfony\AI\Agent\Workflow\MergePolicy;
use Symfony\AI\Agent\Workflow\ParallelExecution\SequentialExecutionStrategy;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

final class AgentWorkflowTest extends TestCase
{
    public function testRunLinearWorkflow()
    {
        $store = new WorkflowStateStore();
        $executors = [
            'start' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('step1', 'done')),
            'middle' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('step2', 'done')),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('step3', 'done')),
        ];

        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $executors, $store);
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

        $workflow = new Workflow($builder->build(), new MethodMarkingStore(singleState: true, property: 'marking'));

        $executors = [
            'draft' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('draft_text', 'Hello')),
            'review' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->withNextTransition('approve')->set('reviewed', true)),
            'approved' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('status', 'approved')),
            'rejected' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('status', 'rejected')),
        ];

        $agentWorkflow = new AgentWorkflow($workflow, $executors, new WorkflowStateStore());
        $finalState = $agentWorkflow->run(new WorkflowState('branch-1'));

        $this->assertSame('approved', $finalState->get('status'));
        $this->assertTrue($finalState->get('reviewed'));
        $this->assertSame(['draft', 'review', 'approved'], $finalState->getCompletedPlaces());
    }

    public function testRunFailsFastWhenExecutorMissing()
    {
        $store = new WorkflowStateStore();
        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), [], $store);

        try {
            $agentWorkflow->run(new WorkflowState('missing-1'));
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('No executor registered for place(s): "start", "middle", "end".', $exception->getMessage());
        }

        // Fail-fast: nothing is persisted when validation fails.
        $this->assertFalse($store->has('missing-1'));
    }

    public function testRunPersistsStateAfterEachPlace()
    {
        $store = new WorkflowStateStore();
        $executors = [
            'start' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('a', 1)),
            'middle' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('b', 2)),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('c', 3)),
        ];

        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $executors, $store);
        $agentWorkflow->run(new WorkflowState('persist-1'));

        $this->assertTrue($store->has('persist-1'));
        $loaded = $store->load('persist-1');
        $this->assertSame(1, $loaded->get('a'));
        $this->assertSame(2, $loaded->get('b'));
        $this->assertSame(3, $loaded->get('c'));
    }

    public function testResumeContinuesAfterLastCompletedPlace()
    {
        $store = new WorkflowStateStore();
        // Simulate a run that completed 'start' and stopped between places.
        $store->save((new WorkflowState('resume-1'))->markCompleted('start'));

        $startCalls = 0;
        $executors = [
            'start' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$startCalls): WorkflowStateInterface {
                ++$startCalls;

                return $state;
            }),
            'middle' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('middle_result', 'done')),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('end_result', 'done')),
        ];

        $finalState = (new AgentWorkflow($this->createLinearWorkflow(), $executors, $store))->resume('resume-1');

        $this->assertSame(0, $startCalls, 'an already-completed place must not be re-executed on resume');
        $this->assertSame('done', $finalState->get('middle_result'));
        $this->assertSame('done', $finalState->get('end_result'));
        $this->assertSame(['start', 'middle', 'end'], $finalState->getCompletedPlaces());
    }

    public function testResumeReExecutesInterruptedPlace()
    {
        $store = new WorkflowStateStore();
        // 'start' completed, 'middle' interrupted mid-execution.
        $store->save((new WorkflowState('resume-2'))->markCompleted('start')->withCurrentPlace('middle'));

        $startCalls = 0;
        $middleCalls = 0;
        $executors = [
            'start' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$startCalls): WorkflowStateInterface {
                ++$startCalls;

                return $state;
            }),
            'middle' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$middleCalls): WorkflowStateInterface {
                ++$middleCalls;

                return $state->set('middle_result', 'done');
            }),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('end_result', 'done')),
        ];

        $finalState = (new AgentWorkflow($this->createLinearWorkflow(), $executors, $store))->resume('resume-2');

        $this->assertSame(0, $startCalls);
        $this->assertSame(1, $middleCalls);
        $this->assertSame('done', $finalState->get('middle_result'));
        $this->assertSame('done', $finalState->get('end_result'));
    }

    public function testResumeOnAlreadyCompletedWorkflowIsNoop()
    {
        $store = new WorkflowStateStore();
        $store->save((new WorkflowState('resume-3'))->markCompleted('start')->markCompleted('middle')->markCompleted('end'));

        $calls = 0;
        $executor = new CallableExecutor(static function (WorkflowStateInterface $state) use (&$calls): WorkflowStateInterface {
            ++$calls;

            return $state;
        });

        $finalState = (new AgentWorkflow($this->createLinearWorkflow(), ['start' => $executor, 'middle' => $executor, 'end' => $executor], $store))->resume('resume-3');

        $this->assertSame(0, $calls);
        $this->assertSame(['start', 'middle', 'end'], $finalState->getCompletedPlaces());
    }

    public function testResumeThrowsWhenStateNotFound()
    {
        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $this->linearExecutors(), new WorkflowStateStore());

        $this->expectException(WorkflowStateNotFoundException::class);

        $agentWorkflow->resume('does-not-exist');
    }

    public function testGuardAllowsExecution()
    {
        $store = new WorkflowStateStore();
        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $this->linearExecutors(), $store, guards: [
            $this->createGuard(true, ['start']),
            $this->createGuard(true, ['middle']),
        ]);

        $finalState = $agentWorkflow->run(new WorkflowState('guard-allow'));

        $this->assertSame(['start', 'middle', 'end'], $finalState->getCompletedPlaces());
    }

    public function testGuardBlocksExecution()
    {
        $store = new WorkflowStateStore();
        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $this->linearExecutors(), $store, guards: [
            $this->createGuard(false, ['middle']),
        ]);

        $this->expectException(WorkflowGuardException::class);
        $this->expectExceptionMessage('Guard rejected execution at place "middle"');

        $agentWorkflow->run(new WorkflowState('guard-block'));
    }

    public function testMultipleGuardsOnSamePlace()
    {
        $store = new WorkflowStateStore();
        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $this->linearExecutors(), $store, guards: [
            $this->createGuard(true, ['middle']),
            $this->createGuard(false, ['middle']),
        ]);

        $this->expectException(WorkflowGuardException::class);
        $this->expectExceptionMessage('Guard rejected execution at place "middle"');

        $agentWorkflow->run(new WorkflowState('guard-multi'));
    }

    public function testGuardOnSpecificPlaceOnlyStopsAtThatPlace()
    {
        $store = new WorkflowStateStore();
        $executorCallLog = [];
        $executors = [
            'start' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$executorCallLog): WorkflowStateInterface {
                $executorCallLog[] = 'start';

                return $state;
            }),
            'middle' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$executorCallLog): WorkflowStateInterface {
                $executorCallLog[] = 'middle';

                return $state;
            }),
            'end' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$executorCallLog): WorkflowStateInterface {
                $executorCallLog[] = 'end';

                return $state;
            }),
        ];

        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $executors, $store, guards: [
            $this->createGuard(false, ['end']),
        ]);

        try {
            $agentWorkflow->run(new WorkflowState('guard-specific'));
        } catch (WorkflowGuardException) {
            // expected
        }

        $this->assertSame(['start', 'middle'], $executorCallLog);
    }

    public function testUnscopedGuardAppliesToEveryPlace()
    {
        $store = new WorkflowStateStore();
        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $this->linearExecutors(), $store, guards: [
            $this->createGuard(false),
        ]);

        $this->expectException(WorkflowGuardException::class);
        $this->expectExceptionMessage('Guard rejected execution at place "start"');

        $agentWorkflow->run(new WorkflowState('guard-unscoped'));
    }

    public function testMaxStepsExceededOnCyclicWorkflow()
    {
        $builder = new DefinitionBuilder();
        $builder
            ->addPlaces(['ping', 'pong'])
            ->addTransition(new Transition('to_pong', 'ping', 'pong'))
            ->addTransition(new Transition('to_ping', 'pong', 'ping'))
            ->setInitialPlaces(['ping']);

        $workflow = new Workflow($builder->build(), new MethodMarkingStore(singleState: true, property: 'marking'));

        $executor = new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state);
        $agentWorkflow = new AgentWorkflow($workflow, ['ping' => $executor, 'pong' => $executor], new WorkflowStateStore(), maxSteps: 5);

        $this->expectException(WorkflowMaxStepsExceededException::class);
        $this->expectExceptionMessage('Workflow exceeded the maximum number of steps (5)');

        $agentWorkflow->run(new WorkflowState('cycle-1'));
    }

    public function testDispatchesLifecycleEvents()
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<object> */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };

        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $this->linearExecutors(), new WorkflowStateStore(), eventDispatcher: $dispatcher);
        $agentWorkflow->run(new WorkflowState('events-1'));

        $eventClasses = array_map('get_class', $dispatcher->events);

        $this->assertSame([
            WorkflowStartedEvent::class,
            PlaceEnteredEvent::class,
            PlaceCompletedEvent::class,
            TransitionAppliedEvent::class,
            PlaceEnteredEvent::class,
            PlaceCompletedEvent::class,
            TransitionAppliedEvent::class,
            PlaceEnteredEvent::class,
            PlaceCompletedEvent::class,
            WorkflowCompletedEvent::class,
        ], $eventClasses);
    }

    public function testDispatchesFailureEventWhenExecutorThrows()
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<object> */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };

        $executors = [
            'start' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
            'middle' => new CallableExecutor(static function (): WorkflowStateInterface {
                throw new \RuntimeException('boom at middle');
            }),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];

        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $executors, new WorkflowStateStore(), eventDispatcher: $dispatcher);

        try {
            $agentWorkflow->run(new WorkflowState('events-failure'));
            $this->fail('Expected run() to throw when the middle executor fails.');
        } catch (WorkflowExecutorException $exception) {
            $this->assertStringContainsString('boom at middle', $exception->getMessage());
        }

        $failedEvents = array_values(array_filter($dispatcher->events, static fn (object $event): bool => $event instanceof WorkflowFailedEvent));

        $this->assertCount(1, $failedEvents);
        $this->assertSame('middle', $failedEvents[0]->getPlace());
        $this->assertInstanceOf(WorkflowExecutorException::class, $failedEvents[0]->getError());
    }

    public function testRunIgnoresStaleProgressOnIncomingState()
    {
        $store = new WorkflowStateStore();
        $callLog = [];
        $executors = [
            'start' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$callLog): WorkflowStateInterface {
                $callLog[] = 'start';

                return $state;
            }),
            'middle' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$callLog): WorkflowStateInterface {
                $callLog[] = 'middle';

                return $state;
            }),
            'end' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$callLog): WorkflowStateInterface {
                $callLog[] = 'end';

                return $state;
            }),
        ];

        $agentWorkflow = new AgentWorkflow($this->createLinearWorkflow(), $executors, $store);
        // The incoming state carries stale progress that run() must ignore.
        $agentWorkflow->run(new WorkflowState('stale-1', ['seed' => 'x'], ['start', 'middle'], 'end'));

        $this->assertSame(['start', 'middle', 'end'], $callLog);
    }

    public function testLockingPreventsRunWhenAlreadyHeld()
    {
        $lockStore = new InMemoryStore();
        $lockFactory = new LockFactory($lockStore);

        // Acquire the lock externally to simulate another process holding it.
        $externalLock = $lockFactory->createLock('agent-workflow-locked-run');
        $externalLock->acquire();

        $agentWorkflow = new AgentWorkflow(
            $this->createLinearWorkflow(),
            $this->linearExecutors(),
            new WorkflowStateStore(),
            lockFactory: $lockFactory,
        );

        try {
            $agentWorkflow->run(new WorkflowState('locked-run'));
            $this->fail('Expected a WorkflowLockedException.');
        } catch (WorkflowLockedException $exception) {
            $this->assertStringContainsString('locked-run', $exception->getMessage());
        } finally {
            $externalLock->release();
        }
    }

    public function testLockingPreventsResumeWhenAlreadyHeld()
    {
        $lockStore = new InMemoryStore();
        $lockFactory = new LockFactory($lockStore);

        $store = new WorkflowStateStore();
        $store->save((new WorkflowState('locked-resume'))->markCompleted('start'));

        $externalLock = $lockFactory->createLock('agent-workflow-locked-resume');
        $externalLock->acquire();

        $agentWorkflow = new AgentWorkflow(
            $this->createLinearWorkflow(),
            $this->linearExecutors(),
            $store,
            lockFactory: $lockFactory,
        );

        try {
            $agentWorkflow->resume('locked-resume');
            $this->fail('Expected a WorkflowLockedException.');
        } catch (WorkflowLockedException $exception) {
            $this->assertStringContainsString('locked-resume', $exception->getMessage());
        } finally {
            $externalLock->release();
        }
    }

    public function testLockingReleasedAfterSuccessfulRun()
    {
        if ($this->markingStoreReusesSubject()) {
            $this->markTestSkipped('symfony/workflow 7.4.0-BETA1 regressed MethodMarkingStore: getMarking() caches the per-class accessor bound to the first subject, so reusing a Workflow with a fresh subject reads the previous marking. Reported upstream to symfony/symfony.');
        }

        $lockStore = new InMemoryStore();
        $lockFactory = new LockFactory($lockStore);

        $agentWorkflow = new AgentWorkflow(
            $this->createLinearWorkflow(),
            $this->linearExecutors(),
            new WorkflowStateStore(),
            lockFactory: $lockFactory,
        );

        // First run must succeed.
        $finalState = $agentWorkflow->run(new WorkflowState('lock-release'));
        $this->assertSame(['start', 'middle', 'end'], $finalState->getCompletedPlaces());

        // After the first run, the lock is released; a second run must also succeed.
        $finalState2 = $agentWorkflow->run(new WorkflowState('lock-release-2'));
        $this->assertSame(['start', 'middle', 'end'], $finalState2->getCompletedPlaces());
    }

    public function testRunSucceedsWithoutLockFactory()
    {
        // Sanity check: no lock factory means no locking, always runs.
        $agentWorkflow = new AgentWorkflow(
            $this->createLinearWorkflow(),
            $this->linearExecutors(),
            new WorkflowStateStore(),
        );

        $finalState = $agentWorkflow->run(new WorkflowState('no-lock'));
        $this->assertSame(['start', 'middle', 'end'], $finalState->getCompletedPlaces());
    }

    public function testAndSplitRunsAllParallelBranches()
    {
        $agentWorkflow = new AgentWorkflow(
            $this->createAndSplitWorkflow(),
            $this->andSplitExecutors(),
            new WorkflowStateStore(),
        );

        $finalState = $agentWorkflow->run(new WorkflowState('and-split-1'));

        $this->assertSame('done', $finalState->get('branch-a-result'));
        $this->assertSame('done', $finalState->get('branch-b-result'));
    }

    public function testAndSplitMergesAllBranchOutputs()
    {
        $agentWorkflow = new AgentWorkflow(
            $this->createAndSplitWorkflow(),
            $this->andSplitExecutors(),
            new WorkflowStateStore(),
            mergePolicy: MergePolicy::FailOnConflict,
        );

        $finalState = $agentWorkflow->run(new WorkflowState('and-split-2'));

        // Both parallel branch outputs must be visible in the merged state.
        $this->assertTrue($finalState->has('branch-a-result'));
        $this->assertTrue($finalState->has('branch-b-result'));
    }

    public function testAndSplitAllBranchPlacesMarkedCompleted()
    {
        $agentWorkflow = new AgentWorkflow(
            $this->createAndSplitWorkflow(),
            $this->andSplitExecutors(),
            new WorkflowStateStore(),
        );

        $finalState = $agentWorkflow->run(new WorkflowState('and-split-3'));

        $completedPlaces = $finalState->getCompletedPlaces();
        $this->assertContains('branch-a', $completedPlaces);
        $this->assertContains('branch-b', $completedPlaces);
    }

    public function testAndSplitContinuesToFinalPlaceAfterJoin()
    {
        // The workflow is: start → (branch-a || branch-b) → end.
        // After joining, the workflow must continue to the 'end' place.
        $agentWorkflow = new AgentWorkflow(
            $this->createAndSplitWorkflow(),
            $this->andSplitExecutors(),
            new WorkflowStateStore(),
        );

        $finalState = $agentWorkflow->run(new WorkflowState('and-split-4'));

        $this->assertContains('start', $finalState->getCompletedPlaces());
        $this->assertContains('end', $finalState->getCompletedPlaces());
    }

    // --- Partial-failure resume for AND-split ---

    public function testAndSplitBranchFailureThrowsAndInterruptedForkIsRecorded()
    {
        $store = new WorkflowStateStore();

        // branch-c fails on the first call and succeeds on any subsequent call.
        $branchCCallCount = 0;
        $executors = [
            'start' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->withNextTransition('fork')),
            'branch-a' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('branch-a-result', 'done')),
            'branch-b' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('branch-b-result', 'done')),
            'branch-c' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$branchCCallCount): WorkflowStateInterface {
                ++$branchCCallCount;
                if (1 === $branchCCallCount) {
                    throw new \RuntimeException('branch-c transient failure');
                }

                return $state->set('branch-c-result', 'done');
            }),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];

        $agentWorkflow = new AgentWorkflow(
            $this->createThreeBranchAndSplitWorkflow(),
            $executors,
            $store,
            parallelStrategy: new SequentialExecutionStrategy(),
        );

        // (a) run() must throw the branch failure, unwrapped from the WorkflowBranchException to the
        //     cause the failing branch executor raised.
        $thrownException = null;
        try {
            $agentWorkflow->run(new WorkflowState('three-branch-1'));
            $this->fail('Expected run() to throw due to branch-c failure.');
        } catch (WorkflowExecutorException $exception) {
            $thrownException = $exception;
        }

        $this->assertInstanceOf(WorkflowExecutorException::class, $thrownException, 'run() must throw the failing branch cause.');
        $this->assertStringContainsString('branch-c transient failure', $thrownException->getMessage());

        // (b) The persisted state must record the full fork in interruptedFork and
        //     include the two branches that completed before the failure.
        $persisted = $store->load('three-branch-1');

        $interruptedFork = $persisted->getInterruptedFork();
        $this->assertContains('branch-a', $interruptedFork);
        $this->assertContains('branch-b', $interruptedFork);
        $this->assertContains('branch-c', $interruptedFork);
        $this->assertCount(3, $interruptedFork);

        $completedPlaces = $persisted->getCompletedPlaces();
        $this->assertContains('branch-a', $completedPlaces);
        $this->assertContains('branch-b', $completedPlaces);
        $this->assertNotContains('branch-c', $completedPlaces);
    }

    public function testAndSplitResumeSkipsAlreadyCompletedBranchesAndRunsFailedOne()
    {
        $store = new WorkflowStateStore();

        // branch-a and branch-b always succeed; branch-c fails on the first call only.
        $branchACallCount = 0;
        $branchBCallCount = 0;
        $branchCCallCount = 0;

        $executors = [
            'start' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->withNextTransition('fork')),
            'branch-a' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$branchACallCount): WorkflowStateInterface {
                ++$branchACallCount;

                return $state->set('branch-a-result', 'done');
            }),
            'branch-b' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$branchBCallCount): WorkflowStateInterface {
                ++$branchBCallCount;

                return $state->set('branch-b-result', 'done');
            }),
            'branch-c' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$branchCCallCount): WorkflowStateInterface {
                ++$branchCCallCount;
                if (1 === $branchCCallCount) {
                    throw new \RuntimeException('branch-c transient failure');
                }

                return $state->set('branch-c-result', 'done');
            }),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];

        $agentWorkflow = new AgentWorkflow(
            $this->createThreeBranchAndSplitWorkflow(),
            $executors,
            $store,
            parallelStrategy: new SequentialExecutionStrategy(),
        );

        // First run — branch-c fails.
        try {
            $agentWorkflow->run(new WorkflowState('three-branch-2'));
        } catch (\Throwable) {
            // expected first-run failure
        }

        // (c) resume() must complete without throwing.
        $finalState = $agentWorkflow->resume('three-branch-2');

        // (d) branch-a and branch-b executors must NOT run again (call counts stay at 1);
        //     branch-c must run exactly once more.
        $this->assertSame(1, $branchACallCount, 'branch-a must not re-execute on resume');
        $this->assertSame(1, $branchBCallCount, 'branch-b must not re-execute on resume');
        $this->assertSame(2, $branchCCallCount, 'branch-c must re-execute exactly once on resume');

        // (e) The final merged state must contain every branch's output.
        $this->assertSame('done', $finalState->get('branch-a-result'));
        $this->assertSame('done', $finalState->get('branch-b-result'));
        $this->assertSame('done', $finalState->get('branch-c-result'));
    }

    public function testAndSplitResumedStateHasNoInterruptedForkAfterSuccess()
    {
        $store = new WorkflowStateStore();

        $branchCCallCount = 0;
        $executors = [
            'start' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->withNextTransition('fork')),
            'branch-a' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('branch-a-result', 'done')),
            'branch-b' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('branch-b-result', 'done')),
            'branch-c' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$branchCCallCount): WorkflowStateInterface {
                ++$branchCCallCount;
                if (1 === $branchCCallCount) {
                    throw new \RuntimeException('branch-c transient failure');
                }

                return $state->set('branch-c-result', 'done');
            }),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];

        $agentWorkflow = new AgentWorkflow(
            $this->createThreeBranchAndSplitWorkflow(),
            $executors,
            $store,
            parallelStrategy: new SequentialExecutionStrategy(),
        );

        try {
            $agentWorkflow->run(new WorkflowState('three-branch-3'));
        } catch (\Throwable) {
            // expected first-run failure
        }

        $finalState = $agentWorkflow->resume('three-branch-3');

        // After a successful resume the interruptedFork must be cleared.
        $this->assertSame([], $finalState->getInterruptedFork());
    }

    public function testAndSplitAllCompletedPlacesPresentAfterResume()
    {
        $store = new WorkflowStateStore();

        $branchCCallCount = 0;
        $executors = [
            'start' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->withNextTransition('fork')),
            'branch-a' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('branch-a-result', 'done')),
            'branch-b' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('branch-b-result', 'done')),
            'branch-c' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$branchCCallCount): WorkflowStateInterface {
                ++$branchCCallCount;
                if (1 === $branchCCallCount) {
                    throw new \RuntimeException('branch-c transient failure');
                }

                return $state->set('branch-c-result', 'done');
            }),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];

        $agentWorkflow = new AgentWorkflow(
            $this->createThreeBranchAndSplitWorkflow(),
            $executors,
            $store,
            parallelStrategy: new SequentialExecutionStrategy(),
        );

        try {
            $agentWorkflow->run(new WorkflowState('three-branch-4'));
        } catch (\Throwable) {
            // expected
        }

        $finalState = $agentWorkflow->resume('three-branch-4');

        $completedPlaces = $finalState->getCompletedPlaces();
        $this->assertContains('start', $completedPlaces);
        $this->assertContains('branch-a', $completedPlaces);
        $this->assertContains('branch-b', $completedPlaces);
        $this->assertContains('branch-c', $completedPlaces);
        $this->assertContains('end', $completedPlaces);
    }

    /**
     * @return array<non-empty-string, CallableExecutor>
     */
    private function linearExecutors(): array
    {
        $passthrough = new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state);

        return ['start' => $passthrough, 'middle' => $passthrough, 'end' => $passthrough];
    }

    /**
     * @param list<non-empty-string> $places
     */
    private function createGuard(bool $result, array $places = []): GuardInterface
    {
        return new class($result, $places) extends AbstractGuard {
            /**
             * @param list<non-empty-string> $places
             */
            public function __construct(private readonly bool $result, array $places)
            {
                parent::__construct($places);
            }

            public function allows(WorkflowStateInterface $state, string $place): bool
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

        return new Workflow($builder->build(), new MethodMarkingStore(singleState: true, property: 'marking'));
    }

    /**
     * Detects the symfony/workflow 7.4.0-BETA1 MethodMarkingStore regression: getMarking() caches
     * the per-class accessor closure bound to the first subject instance, so a Workflow reused with
     * a fresh subject — as AgentWorkflow does on every run() — reads the previous subject's marking
     * instead of re-seeding the initial place. Returns true only on a workflow release exhibiting it.
     */
    private function markingStoreReusesSubject(): bool
    {
        $builder = new DefinitionBuilder();
        $builder
            ->addPlaces(['first', 'second'])
            ->addTransition(new Transition('advance', 'first', 'second'))
            ->setInitialPlaces(['first']);
        $workflow = new Workflow($builder->build(), new MethodMarkingStore(singleState: true, property: 'marking'));

        $used = new \stdClass();
        $used->marking = null;
        $workflow->getMarking($used);
        $workflow->apply($used, 'advance');

        $fresh = new \stdClass();
        $fresh->marking = null;

        return ['first'] !== array_keys($workflow->getMarking($fresh)->getPlaces());
    }

    /**
     * Builds a workflow that forks into two concurrent places and joins them before ending:
     *
     *   start → [branch-a || branch-b] → end
     */
    private function createAndSplitWorkflow(): Workflow
    {
        $builder = new DefinitionBuilder();
        $builder
            ->addPlaces(['start', 'branch-a', 'branch-b', 'end'])
            ->addTransition(new Transition('fork', 'start', ['branch-a', 'branch-b']))
            ->addTransition(new Transition('join', ['branch-a', 'branch-b'], 'end'))
            ->setInitialPlaces(['start']);

        return new Workflow($builder->build(), new MethodMarkingStore(singleState: false, property: 'marking'));
    }

    /**
     * @return array<non-empty-string, CallableExecutor>
     */
    private function andSplitExecutors(): array
    {
        return [
            'start' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->withNextTransition('fork')),
            'branch-a' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('branch-a-result', 'done')),
            'branch-b' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('branch-b-result', 'done')),
            'end' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];
    }

    /**
     * Builds an AND-split with three concurrent branches:
     *
     *   start → [branch-a || branch-b || branch-c] → end
     */
    private function createThreeBranchAndSplitWorkflow(): Workflow
    {
        $builder = new DefinitionBuilder();
        $builder
            ->addPlaces(['start', 'branch-a', 'branch-b', 'branch-c', 'end'])
            ->addTransition(new Transition('fork', 'start', ['branch-a', 'branch-b', 'branch-c']))
            ->addTransition(new Transition('join', ['branch-a', 'branch-b', 'branch-c'], 'end'))
            ->setInitialPlaces(['start']);

        return new Workflow($builder->build(), new MethodMarkingStore(singleState: false, property: 'marking'));
    }
}
