<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\ParallelExecution;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Agent\Exception\WorkflowBranchException;
use Symfony\AI\Agent\Workflow\AsyncExecutorInterface;
use Symfony\AI\Agent\Workflow\Event\PlaceCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\PlaceEnteredEvent;
use Symfony\AI\Agent\Workflow\Executor\CallableExecutor;
use Symfony\AI\Agent\Workflow\ParallelExecution\ConcurrentExecutionStrategy;
use Symfony\AI\Agent\Workflow\PendingExecution;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ConcurrentExecutionStrategyTest extends TestCase
{
    public function testRunReturnsResultsKeyedByPlaceForSyncExecutors()
    {
        $base = new WorkflowState('con-1');
        $strategy = new ConcurrentExecutionStrategy();

        $executors = [
            'branch-a' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('a', 'result-a')),
            'branch-b' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('b', 'result-b')),
        ];

        $results = $strategy->run($base, $executors);

        $this->assertArrayHasKey('branch-a', $results);
        $this->assertArrayHasKey('branch-b', $results);
        $this->assertSame('result-a', $results['branch-a']->get('a'));
        $this->assertSame('result-b', $results['branch-b']->get('b'));
    }

    public function testAllSyncExecutorsRun()
    {
        $base = new WorkflowState('con-2');
        $strategy = new ConcurrentExecutionStrategy();

        $callLog = [];
        $executors = [
            'place-one' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$callLog): WorkflowStateInterface {
                $callLog[] = 'place-one';

                return $state;
            }),
            'place-two' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$callLog): WorkflowStateInterface {
                $callLog[] = 'place-two';

                return $state;
            }),
        ];

        $strategy->run($base, $executors);

        $this->assertContains('place-one', $callLog);
        $this->assertContains('place-two', $callLog);
        $this->assertCount(2, $callLog);
    }

    public function testAsyncExecutorDispatchAndSettleAreCalled()
    {
        $base = new WorkflowState('con-3');
        $strategy = new ConcurrentExecutionStrategy();

        $asyncExecutor = new class implements AsyncExecutorInterface {
            public bool $dispatchCalled = false;
            public bool $settleCalled = false;

            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                $this->dispatchCalled = true;

                return new PendingExecution('handle');
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                $this->settleCalled = true;

                return $state->set('async-result', 'done');
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        $results = $strategy->run($base, ['async-place' => $asyncExecutor]);

        $this->assertTrue($asyncExecutor->dispatchCalled);
        $this->assertTrue($asyncExecutor->settleCalled);
        $this->assertArrayHasKey('async-place', $results);
        $this->assertSame('done', $results['async-place']->get('async-result'));
    }

    public function testMixedAsyncAndSyncExecutors()
    {
        $base = new WorkflowState('con-4');
        $strategy = new ConcurrentExecutionStrategy();

        $syncOrder = [];

        $asyncExecutor = new class implements AsyncExecutorInterface {
            /** @var list<string> */
            public array $dispatchOrder = [];
            /** @var list<string> */
            public array $settleOrder = [];

            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                $this->dispatchOrder[] = $place;

                return new PendingExecution(['place' => $place]);
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                $this->settleOrder[] = $place;

                return $state->set($place.'-result', 'async-done');
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        $syncExecutor = new CallableExecutor(static function (WorkflowStateInterface $state, string $place) use (&$syncOrder): WorkflowStateInterface {
            $syncOrder[] = $place;

            return $state->set($place.'-result', 'sync-done');
        });

        $executors = [
            'async-one' => $asyncExecutor,
            'sync-one' => $syncExecutor,
            'async-two' => $asyncExecutor,
            'sync-two' => $syncExecutor,
        ];

        $results = $strategy->run($base, $executors);

        // All four branches must have produced results.
        $this->assertArrayHasKey('async-one', $results);
        $this->assertArrayHasKey('sync-one', $results);
        $this->assertArrayHasKey('async-two', $results);
        $this->assertArrayHasKey('sync-two', $results);

        // Async results come from settle.
        $this->assertSame('async-done', $results['async-one']->get('async-one-result'));
        $this->assertSame('async-done', $results['async-two']->get('async-two-result'));

        // Sync results come from execute.
        $this->assertSame('sync-done', $results['sync-one']->get('sync-one-result'));
        $this->assertSame('sync-done', $results['sync-two']->get('sync-two-result'));

        // Both async executors were dispatched before either settled.
        $this->assertContains('async-one', $asyncExecutor->dispatchOrder);
        $this->assertContains('async-two', $asyncExecutor->dispatchOrder);
        $this->assertContains('async-one', $asyncExecutor->settleOrder);
        $this->assertContains('async-two', $asyncExecutor->settleOrder);
    }

    public function testAsyncDispatchOrderPrecedesSyncExecution()
    {
        // The implementation dispatches async executors first (before running sync ones).
        // We verify this ordering guarantee.
        $base = new WorkflowState('con-5');
        $strategy = new ConcurrentExecutionStrategy();

        $log = [];

        $asyncExecutor = new class implements AsyncExecutorInterface {
            /** @var list<string> */
            public array $log = [];

            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                $this->log[] = 'dispatch:'.$place;

                return new PendingExecution($place);
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                $this->log[] = 'settle:'.$place;

                return $state->set($place, 'settled');
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        $syncExecutor = new CallableExecutor(static function (WorkflowStateInterface $state, string $place) use (&$log): WorkflowStateInterface {
            $log[] = 'sync:'.$place;

            return $state;
        });

        $executors = [
            'async-branch' => $asyncExecutor,
            'sync-branch' => $syncExecutor,
        ];

        $strategy->run($base, $executors);

        $combinedLog = array_merge($asyncExecutor->log, $log);

        // dispatch must appear before sync, and sync must appear before settle.
        $dispatchPos = array_search('dispatch:async-branch', $asyncExecutor->log, true);
        $settlePos = array_search('settle:async-branch', $asyncExecutor->log, true);
        $syncPos = array_search('sync:sync-branch', $log, true);

        $this->assertNotFalse($dispatchPos);
        $this->assertNotFalse($settlePos);
        $this->assertNotFalse($syncPos);

        // Dispatch happens before settle in the async log.
        $this->assertLessThan($settlePos, $dispatchPos, 'async dispatch must happen before async settle');

        // Async log has at least dispatch entry (sync runs in between, but is tracked separately).
        $this->assertContains('dispatch:async-branch', $combinedLog);
        $this->assertContains('settle:async-branch', $combinedLog);
        $this->assertContains('sync:sync-branch', $combinedLog);
    }

    public function testFailingAsyncDispatchThrowsWorkflowBranchException()
    {
        $base = new WorkflowState('con-6');
        $strategy = new ConcurrentExecutionStrategy();

        $asyncExecutor = new class implements AsyncExecutorInterface {
            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                throw new \RuntimeException('dispatch failed');
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                return $state;
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        try {
            $strategy->run($base, ['failing-async' => $asyncExecutor]);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('failing-async', $exception->getPlace());
        }
    }

    public function testFailingAsyncSettleThrowsWorkflowBranchException()
    {
        $base = new WorkflowState('con-7');
        $strategy = new ConcurrentExecutionStrategy();

        $asyncExecutor = new class implements AsyncExecutorInterface {
            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                return new PendingExecution('ok');
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                throw new \RuntimeException('settle failed');
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        try {
            $strategy->run($base, ['settling-async' => $asyncExecutor]);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('settling-async', $exception->getPlace());
        }
    }

    public function testFailingSyncExecutorThrowsWorkflowBranchException()
    {
        $base = new WorkflowState('con-8');
        $strategy = new ConcurrentExecutionStrategy();

        $executors = [
            'bad-sync' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \RuntimeException('sync failed');
            }),
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('bad-sync', $exception->getPlace());
        }
    }

    public function testDispatchesPlaceEnteredEventsForAllBranches()
    {
        $base = new WorkflowState('con-9');
        $strategy = new ConcurrentExecutionStrategy();

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
            'place-x' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
            'place-y' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];

        $strategy->run($base, $executors, $dispatcher);

        $entered = array_filter($dispatcher->events, static fn (object $e): bool => $e instanceof PlaceEnteredEvent);
        $this->assertCount(2, $entered);
    }

    public function testDispatchesPlaceCompletedEventsForAllBranches()
    {
        $base = new WorkflowState('con-10');
        $strategy = new ConcurrentExecutionStrategy();

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
            'place-m' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
            'place-n' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];

        $strategy->run($base, $executors, $dispatcher);

        $completed = array_filter($dispatcher->events, static fn (object $e): bool => $e instanceof PlaceCompletedEvent);
        $this->assertCount(2, $completed);
    }

    public function testRunWithNoExecutorsReturnsEmptyArray()
    {
        $base = new WorkflowState('con-11');
        $strategy = new ConcurrentExecutionStrategy();

        $results = $strategy->run($base, []);

        $this->assertSame([], $results);
    }

    public function testAsyncSettleReceivesPendingExecutionFromDispatch()
    {
        $base = new WorkflowState('con-12');
        $strategy = new ConcurrentExecutionStrategy();

        $dispatchedPending = new PendingExecution(['secret' => 'payload']);

        $asyncExecutor = new class($dispatchedPending) implements AsyncExecutorInterface {
            public ?PendingExecution $receivedPending = null;

            public function __construct(private readonly PendingExecution $toDispatch)
            {
            }

            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                return $this->toDispatch;
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                $this->receivedPending = $pending;

                return $state;
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        $strategy->run($base, ['tracked-async' => $asyncExecutor]);

        $this->assertSame($dispatchedPending, $asyncExecutor->receivedPending);
    }

    public function testPendingHandleDataIsPreservedThroughSettle()
    {
        $base = new WorkflowState('con-13');
        $strategy = new ConcurrentExecutionStrategy();

        $asyncExecutor = new class implements AsyncExecutorInterface {
            public mixed $receivedHandleData = null;

            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                return new PendingExecution(['token' => 'abc123', 'requestId' => 42]);
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                $this->receivedHandleData = $pending->handle;

                return $state->set('settled', true);
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        $strategy->run($base, ['handle-test' => $asyncExecutor]);

        $this->assertSame(['token' => 'abc123', 'requestId' => 42], $asyncExecutor->receivedHandleData);
    }

    // --- Partial-failure resume: completedBranches in WorkflowBranchException ---

    public function testFailingSyncBranchCompletedBranchesIsEmptyWhenNoOtherSyncRanFirst()
    {
        $base = new WorkflowState('con-14');
        $strategy = new ConcurrentExecutionStrategy();

        // Single sync executor that fails — no branches completed before it.
        $executors = [
            'failing-sync' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \RuntimeException('sync failed immediately');
            }),
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('failing-sync', $exception->getPlace());
            $this->assertSame([], $exception->getCompletedBranches());
        }
    }

    public function testFailingSyncBranchCompletedBranchesContainsPriorSuccessfulSyncResults()
    {
        $base = new WorkflowState('con-15');
        $strategy = new ConcurrentExecutionStrategy();

        // Two sync branches succeed, the third fails — the exception must carry the two successful ones.
        $executors = [
            'sync-one' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('sync-one-result', 'ok')),
            'sync-two' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('sync-two-result', 'ok')),
            'sync-fail' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \RuntimeException('third sync fails');
            }),
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('sync-fail', $exception->getPlace());

            $completedBranches = $exception->getCompletedBranches();

            $this->assertArrayHasKey('sync-one', $completedBranches);
            $this->assertArrayHasKey('sync-two', $completedBranches);
            $this->assertArrayNotHasKey('sync-fail', $completedBranches);

            $this->assertSame('ok', $completedBranches['sync-one']->get('sync-one-result'));
            $this->assertSame('ok', $completedBranches['sync-two']->get('sync-two-result'));
        }
    }

    public function testFailingSettleCompletedBranchesContainsAlreadySettledAndSyncResults()
    {
        $base = new WorkflowState('con-16');
        $strategy = new ConcurrentExecutionStrategy();

        // One sync executor succeeds; first async settles fine; second async settle fails.
        // The exception must carry the sync result AND the first-settled async result.
        $syncExecutor = new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('sync-result', 'sync-ok'));

        $goodAsyncExecutor = new class implements AsyncExecutorInterface {
            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                return new PendingExecution('good-handle');
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                return $state->set('good-async-result', 'async-ok');
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        $failingAsyncExecutor = new class implements AsyncExecutorInterface {
            public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution
            {
                return new PendingExecution('failing-handle');
            }

            public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface
            {
                throw new \RuntimeException('async settle failed');
            }

            public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
            {
                return $state;
            }
        };

        $executors = [
            'async-good' => $goodAsyncExecutor,
            'sync-branch' => $syncExecutor,
            'async-fail' => $failingAsyncExecutor,
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('async-fail', $exception->getPlace());

            $completedBranches = $exception->getCompletedBranches();

            // The sync branch and the first-settled async branch must be reported as completed.
            $this->assertArrayHasKey('sync-branch', $completedBranches);
            $this->assertArrayHasKey('async-good', $completedBranches);
            $this->assertArrayNotHasKey('async-fail', $completedBranches);

            $this->assertSame('sync-ok', $completedBranches['sync-branch']->get('sync-result'));
            $this->assertSame('async-ok', $completedBranches['async-good']->get('good-async-result'));
        }
    }
}
