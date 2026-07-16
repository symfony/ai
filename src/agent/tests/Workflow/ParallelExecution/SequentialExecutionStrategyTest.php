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
use Symfony\AI\Agent\Workflow\Event\PlaceCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\PlaceEnteredEvent;
use Symfony\AI\Agent\Workflow\Executor\CallableExecutor;
use Symfony\AI\Agent\Workflow\ParallelExecution\SequentialExecutionStrategy;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SequentialExecutionStrategyTest extends TestCase
{
    public function testRunReturnsResultsKeyedByPlace()
    {
        $base = new WorkflowState('seq-1');
        $strategy = new SequentialExecutionStrategy();

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

    public function testAllExecutorsRun()
    {
        $base = new WorkflowState('seq-2');
        $strategy = new SequentialExecutionStrategy();

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
            'place-three' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$callLog): WorkflowStateInterface {
                $callLog[] = 'place-three';

                return $state;
            }),
        ];

        $strategy->run($base, $executors);

        $this->assertSame(['place-one', 'place-two', 'place-three'], $callLog);
    }

    public function testExecutorReceivesBaseState()
    {
        $base = new WorkflowState('seq-3', ['seed' => 'original']);
        $strategy = new SequentialExecutionStrategy();

        $receivedValues = [];
        $executors = [
            'branch-a' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$receivedValues): WorkflowStateInterface {
                $receivedValues['branch-a'] = $state->get('seed');

                return $state;
            }),
            'branch-b' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$receivedValues): WorkflowStateInterface {
                $receivedValues['branch-b'] = $state->get('seed');

                return $state;
            }),
        ];

        $strategy->run($base, $executors);

        // Both branches see the shared base state value, not each other's mutations.
        $this->assertSame('original', $receivedValues['branch-a']);
        $this->assertSame('original', $receivedValues['branch-b']);
    }

    public function testFailingExecutorThrowsWorkflowBranchException()
    {
        $base = new WorkflowState('seq-4');
        $strategy = new SequentialExecutionStrategy();

        $executors = [
            'good-branch' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
            'bad-branch' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \RuntimeException('branch failed');
            }),
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('bad-branch', $exception->getPlace());
            $this->assertStringContainsString('bad-branch', $exception->getMessage());
            $this->assertInstanceOf(\Throwable::class, $exception->getPrevious());
        }
    }

    public function testBranchExceptionCarriesFailingPlaceName()
    {
        $base = new WorkflowState('seq-5');
        $strategy = new SequentialExecutionStrategy();

        $executors = [
            'first-place' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \LogicException('executor error');
            }),
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('first-place', $exception->getPlace());
        }
    }

    public function testFirstExecutorFailurePreventsFurtherExecution()
    {
        $base = new WorkflowState('seq-6');
        $strategy = new SequentialExecutionStrategy();

        $secondCalled = false;
        $executors = [
            'failing' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \RuntimeException('first branch failed');
            }),
            'second' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$secondCalled): WorkflowStateInterface {
                $secondCalled = true;

                return $state;
            }),
        ];

        try {
            $strategy->run($base, $executors);
        } catch (WorkflowBranchException) {
            // expected
        }

        $this->assertFalse($secondCalled, 'Second executor must not run after the first one fails.');
    }

    public function testDispatchesPlaceEnteredAndCompletedEvents()
    {
        $base = new WorkflowState('seq-7');
        $strategy = new SequentialExecutionStrategy();

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
            'place-a' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
            'place-b' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state),
        ];

        $strategy->run($base, $executors, $dispatcher);

        $eventClasses = array_map('get_class', $dispatcher->events);
        $this->assertSame([
            PlaceEnteredEvent::class,
            PlaceCompletedEvent::class,
            PlaceEnteredEvent::class,
            PlaceCompletedEvent::class,
        ], $eventClasses);
    }

    public function testRunWithNoExecutorsReturnsEmptyArray()
    {
        $base = new WorkflowState('seq-8');
        $strategy = new SequentialExecutionStrategy();

        $results = $strategy->run($base, []);

        $this->assertSame([], $results);
    }

    public function testRunWithoutEventDispatcherSucceeds()
    {
        $base = new WorkflowState('seq-9');
        $strategy = new SequentialExecutionStrategy();

        $executors = [
            'branch-a' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('done', true)),
        ];

        $results = $strategy->run($base, $executors, null, null);

        $this->assertArrayHasKey('branch-a', $results);
        $this->assertTrue($results['branch-a']->get('done'));
    }

    public function testEachBranchStartsFromSharedBaseNotPreviousBranchOutput()
    {
        // Sequential execution should not chain branch outputs — each branch sees the original base.
        $base = new WorkflowState('seq-10', ['counter' => 0]);
        $strategy = new SequentialExecutionStrategy();

        $seenCounters = [];
        $executors = [
            'branch-a' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$seenCounters): WorkflowStateInterface {
                $seenCounters['branch-a'] = $state->get('counter');

                return $state->set('counter', 99);
            }),
            'branch-b' => new CallableExecutor(static function (WorkflowStateInterface $state) use (&$seenCounters): WorkflowStateInterface {
                $seenCounters['branch-b'] = $state->get('counter');

                return $state;
            }),
        ];

        $strategy->run($base, $executors);

        // branch-b must receive the base counter (0), not branch-a's modified counter (99).
        $this->assertSame(0, $seenCounters['branch-a']);
        $this->assertSame(0, $seenCounters['branch-b']);
    }

    // --- Partial-failure resume: completedBranches in WorkflowBranchException ---

    public function testCompletedBranchesIsEmptyWhenFirstBranchFails()
    {
        $base = new WorkflowState('seq-11');
        $strategy = new SequentialExecutionStrategy();

        $executors = [
            'failing-first' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \RuntimeException('fails immediately');
            }),
            'second' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('second', 'done')),
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('failing-first', $exception->getPlace());
            $this->assertSame([], $exception->getCompletedBranches());
        }
    }

    public function testCompletedBranchesContainsResultStatesOfBranchesBeforeFailure()
    {
        $base = new WorkflowState('seq-12');
        $strategy = new SequentialExecutionStrategy();

        $executors = [
            'first' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('first-result', 'done')),
            'second' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('second-result', 'also-done')),
            'failing-third' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \RuntimeException('third branch fails');
            }),
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $this->assertSame('failing-third', $exception->getPlace());

            $completedBranches = $exception->getCompletedBranches();

            $this->assertArrayHasKey('first', $completedBranches);
            $this->assertArrayHasKey('second', $completedBranches);
            $this->assertArrayNotHasKey('failing-third', $completedBranches);

            $this->assertSame('done', $completedBranches['first']->get('first-result'));
            $this->assertSame('also-done', $completedBranches['second']->get('second-result'));
        }
    }

    public function testCompletedBranchesAreKeyedByPlaceName()
    {
        $base = new WorkflowState('seq-13');
        $strategy = new SequentialExecutionStrategy();

        $executors = [
            'place-alpha' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('alpha', 'ok')),
            'place-beta' => new CallableExecutor(static fn (WorkflowStateInterface $state): WorkflowStateInterface => $state->set('beta', 'ok')),
            'place-gamma' => new CallableExecutor(static function (WorkflowStateInterface $state): WorkflowStateInterface {
                throw new \RuntimeException('gamma fails');
            }),
        ];

        try {
            $strategy->run($base, $executors);
            $this->fail('Expected a WorkflowBranchException.');
        } catch (WorkflowBranchException $exception) {
            $completedBranches = $exception->getCompletedBranches();

            $this->assertSame(['place-alpha', 'place-beta'], array_keys($completedBranches));
        }
    }
}
