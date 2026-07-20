<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\WorkflowBranchException;
use Symfony\AI\Agent\Exception\WorkflowGuardException;
use Symfony\AI\Agent\Exception\WorkflowLockedException;
use Symfony\AI\Agent\Exception\WorkflowMaxStepsExceededException;
use Symfony\AI\Agent\Workflow\Event\PlaceCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\PlaceEnteredEvent;
use Symfony\AI\Agent\Workflow\Event\TransitionAppliedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowFailedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowStartedEvent;
use Symfony\AI\Agent\Workflow\ParallelExecution\ConcurrentExecutionStrategy;
use Symfony\AI\Agent\Workflow\TransitionResolver\StateBasedTransitionResolver;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Orchestrates workflow execution by running an executor at each place
 * and using the Symfony Workflow component for transition logic.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class AgentWorkflow implements AgentWorkflowInterface
{
    private readonly ParallelExecutionStrategyInterface $parallelStrategy;

    /**
     * @param array<non-empty-string, ExecutorInterface> $executors Map of place name to executor
     * @param list<GuardInterface>                       $guards    Guards consulted before each place; each guard decides which places it applies to
     * @param positive-int                               $maxSteps  Safety cap on loop iterations, guarding against cyclic definitions
     */
    public function __construct(
        private readonly WorkflowInterface $workflow,
        private readonly array $executors,
        private readonly WorkflowStateStoreInterface $store,
        private readonly TransitionResolverInterface $transitionResolver = new StateBasedTransitionResolver(),
        private readonly array $guards = [],
        private readonly int $maxSteps = 100,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?LockFactory $lockFactory = null,
        ?ParallelExecutionStrategyInterface $parallelStrategy = null,
        private readonly MergePolicy $mergePolicy = MergePolicy::FailOnConflict,
    ) {
        $this->parallelStrategy = $parallelStrategy ?? new ConcurrentExecutionStrategy();
    }

    public function run(WorkflowStateInterface $initialState): WorkflowStateInterface
    {
        $this->assertExecutorsCoverDefinition();

        // A run always starts from clean progress, even if the caller passes a reused state.
        $state = new WorkflowState($initialState->getId(), $initialState->all());

        return $this->withLock($state->getId(), function () use ($state): WorkflowStateInterface {
            $subject = new \stdClass();
            $subject->marking = null;

            return $this->doRun($state, $subject, false);
        });
    }

    public function resume(string $id): WorkflowStateInterface
    {
        $this->assertExecutorsCoverDefinition();

        return $this->withLock($id, fn (): WorkflowStateInterface => $this->doResume($id));
    }

    private function doResume(string $id): WorkflowStateInterface
    {
        $state = $this->store->load($id);

        $interruptedFork = $state->getInterruptedFork();
        if ([] !== $interruptedFork) {
            // Resume an interrupted AND-split: rebuild the multi-place marking so the run loop
            // re-enters runParallelPlaces(), which skips the branches that already completed.
            $subject = new \stdClass();
            $subject->marking = array_fill_keys($interruptedFork, 1);

            return $this->doRun($state, $subject, true);
        }

        $currentPlace = $state->getCurrentPlace();
        $completedPlaces = $state->getCompletedPlaces();

        $subject = new \stdClass();

        if (null !== $currentPlace && !\in_array($currentPlace, $completedPlaces, true)) {
            // The run was interrupted while executing this place — re-run it.
            $subject->marking = $currentPlace;

            return $this->doRun($state, $subject, true);
        }

        if ([] === $completedPlaces) {
            // Nothing ran yet — start from the workflow's initial place(s). getMarking() seeds the
            // null marking from the definition's initial places, exactly as run() does for a fresh state.
            $subject->marking = null;

            return $this->doRun($state, $subject, true);
        }

        // The run stopped between two places — advance past the last completed one.
        $lastCompletedPlace = $completedPlaces[array_key_last($completedPlaces)];
        $subject->marking = $lastCompletedPlace;

        $transitionName = $this->transitionResolver->resolve($state, $lastCompletedPlace, $this->workflow, $subject);

        if (null === $transitionName) {
            // The workflow already reached a final place.
            return $state;
        }

        $state = $state->clearNextTransition();
        $this->workflow->apply($subject, $transitionName);

        return $this->doRun($state, $subject, true);
    }

    private function doRun(WorkflowStateInterface $state, object $subject, bool $isResume): WorkflowStateInterface
    {
        $this->logger?->info('Workflow "{id}" {action}.', ['id' => $state->getId(), 'action' => $isResume ? 'resumed' : 'started']);
        $this->eventDispatcher?->dispatch(new WorkflowStartedEvent($state, $isResume));

        $this->store->save($state);

        $marking = $this->workflow->getMarking($subject);
        $steps = 0;

        while (true) {
            if (++$steps > $this->maxSteps) {
                $exception = new WorkflowMaxStepsExceededException($this->maxSteps);
                $this->logger?->error($exception->getMessage(), ['id' => $state->getId()]);
                $this->eventDispatcher?->dispatch(new WorkflowFailedEvent($state, null, $exception));

                throw $exception;
            }

            $places = array_keys($marking->getPlaces());

            $state = 1 === \count($places)
                ? $this->runPlace($state, $places[0])
                : $this->runParallelPlaces($state, $places);

            try {
                $transitionName = $this->transitionResolver->resolve($state, $places[0], $this->workflow, $subject);
            } catch (\Throwable $exception) {
                $this->logger?->error('Workflow "{id}" transition resolution failed: {message}', ['id' => $state->getId(), 'message' => $exception->getMessage()]);
                $this->eventDispatcher?->dispatch(new WorkflowFailedEvent($state, null, $exception));

                throw $exception;
            }

            if (null === $transitionName) {
                break;
            }

            $state = $state->clearNextTransition();
            $marking = $this->workflow->apply($subject, $transitionName);

            $this->logger?->debug('Workflow "{id}" applied transition "{transition}".', ['id' => $state->getId(), 'transition' => $transitionName]);
            $this->eventDispatcher?->dispatch(new TransitionAppliedEvent($state, $transitionName));
        }

        $state = $state->withCurrentPlace(null);
        $this->store->save($state);

        $this->logger?->info('Workflow "{id}" completed.', ['id' => $state->getId()]);
        $this->eventDispatcher?->dispatch(new WorkflowCompletedEvent($state));

        return $state;
    }

    /**
     * Runs a single place; this is the fast path for linear workflows.
     *
     * @param non-empty-string $place
     */
    private function runPlace(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        $executor = $this->executors[$place] ?? throw new InvalidArgumentException(\sprintf('No executor registered for place "%s".', $place));

        // Persist the place before executing it, so a crash is recoverable.
        $state = $state->withCurrentPlace($place);
        $this->store->save($state);

        try {
            $this->guardPlace($state, $place);

            $this->logger?->debug('Workflow "{id}" entering place "{place}".', ['id' => $state->getId(), 'place' => $place]);
            $this->eventDispatcher?->dispatch(new PlaceEnteredEvent($state, $place));

            $state = $executor->execute($state, $place);
        } catch (\Throwable $exception) {
            // currentPlace is still set: resume() will re-run exactly this place.
            $this->store->save($state);
            $this->logger?->error('Workflow "{id}" failed at place "{place}": {message}', ['id' => $state->getId(), 'place' => $place, 'message' => $exception->getMessage()]);
            $this->eventDispatcher?->dispatch(new WorkflowFailedEvent($state, $place, $exception));

            throw $exception;
        }

        $state = $state->markCompleted($place);
        $this->store->save($state);

        $this->logger?->debug('Workflow "{id}" completed place "{place}".', ['id' => $state->getId(), 'place' => $place]);
        $this->eventDispatcher?->dispatch(new PlaceCompletedEvent($state, $place));

        return $state;
    }

    /**
     * Runs the still-pending places of a concurrently-active set (an AND-split) and merges their
     * result. Branches already listed in the completed places are skipped, so resuming an
     * interrupted fork only re-runs the branches that had not finished.
     *
     * @param non-empty-list<non-empty-string> $places
     */
    private function runParallelPlaces(WorkflowStateInterface $base, array $places): WorkflowStateInterface
    {
        $completedPlaces = $base->getCompletedPlaces();
        $pending = [];
        foreach ($places as $place) {
            if (\in_array($place, $completedPlaces, true)) {
                continue;
            }

            $pending[$place] = $this->executors[$place] ?? throw new InvalidArgumentException(\sprintf('No executor registered for place "%s".', $place));
        }

        if ([] === $pending) {
            // Every branch already ran (resuming a fully-completed fork) — just clear the marker.
            return [] === $base->getInterruptedFork() ? $base : $base->withInterruptedFork([]);
        }

        $this->logger?->info('Workflow "{id}" forking into {count} parallel places.', ['id' => $base->getId(), 'count' => \count($pending)]);

        $guardedPlace = null;

        try {
            foreach (array_keys($pending) as $place) {
                $guardedPlace = $place;
                $this->guardPlace($base, $place);
            }

            $guardedPlace = null;

            $branchStates = $this->parallelStrategy->run($base, $pending, $this->eventDispatcher, $this->logger);
        } catch (\Throwable $exception) {
            $state = $this->persistInterruptedFork($base, $places, $exception);

            $cause = $exception instanceof WorkflowBranchException ? ($exception->getPrevious() ?? $exception) : $exception;
            // A guard rejection is not a WorkflowBranchException, so fall back to the place whose guard
            // was running when it threw, matching the linear path's place attribution.
            $failedPlace = $exception instanceof WorkflowBranchException ? $exception->getPlace() : $guardedPlace;

            $this->logger?->error('Workflow "{id}" parallel execution failed: {message}', ['id' => $base->getId(), 'message' => $cause->getMessage()]);
            $this->eventDispatcher?->dispatch(new WorkflowFailedEvent($state, $failedPlace, $cause));

            throw $cause;
        }

        try {
            $state = WorkflowState::mergeBranches($base, $branchStates, $this->mergePolicy);
        } catch (\Throwable $exception) {
            // The branches ran but the join failed (e.g. a merge conflict); surface it through the same
            // failure channel as every other error instead of letting it escape doRun unobserved.
            $this->logger?->error('Workflow "{id}" parallel join failed: {message}', ['id' => $base->getId(), 'message' => $exception->getMessage()]);
            $this->eventDispatcher?->dispatch(new WorkflowFailedEvent($base, null, $exception));

            throw $exception;
        }

        $this->store->save($state);

        $this->logger?->info('Workflow "{id}" joined {count} parallel places.', ['id' => $base->getId(), 'count' => \count($pending)]);

        return $state;
    }

    /**
     * Persists progress when a fork is interrupted: the branches that completed are folded in and
     * the full fork is recorded, so resume() rebuilds the marking and re-runs only what is pending.
     *
     * @param non-empty-list<non-empty-string> $forkPlaces
     */
    private function persistInterruptedFork(WorkflowStateInterface $base, array $forkPlaces, \Throwable $exception): WorkflowStateInterface
    {
        $state = $base;

        if ($exception instanceof WorkflowBranchException && [] !== $exception->getCompletedBranches()) {
            try {
                $state = WorkflowState::mergeBranches($base, $exception->getCompletedBranches(), $this->mergePolicy);
            } catch (\Throwable) {
                // A conflict among the succeeded branches must not mask the branch failure;
                // fall back to the pre-fork state so resume() re-runs every branch.
                $state = $base;
            }
        }

        $state = $state->withInterruptedFork($forkPlaces)->withCurrentPlace(null);
        $this->store->save($state);

        return $state;
    }

    /**
     * @param \Closure(): WorkflowStateInterface $operation
     *
     * @throws WorkflowLockedException When the same workflow id is already running elsewhere
     */
    private function withLock(string $id, \Closure $operation): WorkflowStateInterface
    {
        if (null === $this->lockFactory) {
            return $operation();
        }

        $lock = $this->lockFactory->createLock('agent-workflow-'.$id);

        if (!$lock->acquire()) {
            $this->logger?->warning('Workflow "{id}" run skipped: it is already locked by another process.', ['id' => $id]);

            throw new WorkflowLockedException($id);
        }

        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    /**
     * @param non-empty-string $place
     *
     * @throws WorkflowGuardException
     */
    private function guardPlace(WorkflowStateInterface $state, string $place): void
    {
        foreach ($this->guards as $guard) {
            if ($guard->supports($place) && !$guard->allows($state, $place)) {
                throw new WorkflowGuardException(\sprintf('Guard rejected execution at place "%s".', $place));
            }
        }
    }

    /**
     * @throws InvalidArgumentException When a definition place has no registered executor
     */
    private function assertExecutorsCoverDefinition(): void
    {
        $missing = array_diff(
            array_keys($this->workflow->getDefinition()->getPlaces()),
            array_keys($this->executors),
        );

        if ([] !== $missing) {
            throw new InvalidArgumentException(\sprintf('No executor registered for place(s): "%s".', implode('", "', $missing)));
        }
    }
}
