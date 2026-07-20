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
use Symfony\AI\Agent\Workflow\Event\PlaceCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\PlaceEnteredEvent;
use Symfony\AI\Agent\Workflow\ParallelExecutionStrategyInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * Runs concurrently-active places one after another, from the shared base state.
 *
 * Correct, but without any speedup — use it to opt out of concurrency.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SequentialExecutionStrategy implements ParallelExecutionStrategyInterface
{
    public function run(
        WorkflowStateInterface $base,
        array $executors,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ): array {
        $results = [];

        foreach ($executors as $place => $executor) {
            $logger?->debug('Workflow "{id}" entering parallel place "{place}".', ['id' => $base->getId(), 'place' => $place]);
            $eventDispatcher?->dispatch(new PlaceEnteredEvent($base, $place));

            try {
                $results[$place] = $executor->execute($base, $place);
            } catch (\Throwable $exception) {
                throw new WorkflowBranchException($place, $exception, $results);
            }

            $eventDispatcher?->dispatch(new PlaceCompletedEvent($results[$place], $place));
        }

        return $results;
    }
}
