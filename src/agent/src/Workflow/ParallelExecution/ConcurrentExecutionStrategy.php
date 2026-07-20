<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\ParallelExecution;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Exception\WorkflowBranchException;
use Symfony\AI\Agent\Workflow\AsyncExecutorInterface;
use Symfony\AI\Agent\Workflow\Event\PlaceCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\PlaceEnteredEvent;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\ParallelExecutionStrategyInterface;
use Symfony\AI\Agent\Workflow\PendingExecution;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * Runs concurrently-active places with overlapping I/O.
 *
 * Every {@see AsyncExecutorInterface} is dispatched first, so its platform request transfers in the
 * background; synchronous executors then run while those requests are in flight; finally the async
 * executors are settled. Branches whose executor is not async run sequentially — correct, but with
 * no speedup.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ConcurrentExecutionStrategy implements ParallelExecutionStrategyInterface
{
    public function run(
        WorkflowStateInterface $base,
        array $executors,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ): array {
        /** @var array<non-empty-string, array{executor: AsyncExecutorInterface, handle: PendingExecution}> $dispatched */
        $dispatched = [];
        /** @var array<non-empty-string, ExecutorInterface> $synchronous */
        $synchronous = [];
        /** @var array<non-empty-string, WorkflowStateInterface> $results */
        $results = [];

        // 1. Dispatch every async executor so their requests overlap on the wire.
        foreach ($executors as $place => $executor) {
            $logger?->debug('Workflow "{id}" entering parallel place "{place}".', ['id' => $base->getId(), 'place' => $place]);
            $eventDispatcher?->dispatch(new PlaceEnteredEvent($base, $place));

            if (!$executor instanceof AsyncExecutorInterface) {
                $logger?->debug('Workflow "{id}" place "{place}" executor is not async; it runs sequentially.', ['id' => $base->getId(), 'place' => $place]);
                $synchronous[$place] = $executor;

                continue;
            }

            try {
                $dispatched[$place] = ['executor' => $executor, 'handle' => $executor->dispatch($base, $place)];
            } catch (\Throwable $exception) {
                throw new WorkflowBranchException($place, $exception, $results);
            }
        }

        // 2-3. Run synchronous executors while the dispatched requests keep transferring, then settle
        // the dispatched ones. A branch failure must not abandon the sibling requests already in
        // flight, so on any error the still-pending dispatched handles are drained in the cleanup
        // below (there is no cancel on AsyncExecutorInterface, so settling is the way to release them).
        $settled = [];

        try {
            foreach ($synchronous as $place => $executor) {
                try {
                    $results[$place] = $executor->execute($base, $place);
                } catch (\Throwable $exception) {
                    throw new WorkflowBranchException($place, $exception, $results);
                }

                $eventDispatcher?->dispatch(new PlaceCompletedEvent($results[$place], $place));
            }

            foreach ($dispatched as $place => ['executor' => $executor, 'handle' => $handle]) {
                $settled[$place] = true;

                try {
                    $results[$place] = $executor->settle($base, $place, $handle);
                } catch (\Throwable $exception) {
                    throw new WorkflowBranchException($place, $exception, $results);
                }

                $eventDispatcher?->dispatch(new PlaceCompletedEvent($results[$place], $place));
            }
        } catch (\Throwable $exception) {
            foreach ($dispatched as $place => ['executor' => $executor, 'handle' => $handle]) {
                if (isset($settled[$place])) {
                    continue;
                }

                // Best-effort drain so a sibling branch's in-flight request is not leaked.
                try {
                    $executor->settle($base, $place, $handle);
                } catch (\Throwable) {
                }
            }

            throw $exception;
        }

        return $results;
    }
}
