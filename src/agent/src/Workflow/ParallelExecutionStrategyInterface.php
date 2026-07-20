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
use Symfony\AI\Agent\Exception\WorkflowBranchException;

/**
 * Runs the executors of several concurrently-active workflow places (an AND-split).
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface ParallelExecutionStrategyInterface
{
    /**
     * Executes every place from the shared base state and returns each branch's result.
     *
     * @param array<non-empty-string, ExecutorInterface> $executors The executor of each active place, keyed by place
     *
     * @return array<non-empty-string, WorkflowStateInterface> Each branch's result state, keyed by place
     *
     * @throws WorkflowBranchException When a branch executor fails
     */
    public function run(
        WorkflowStateInterface $base,
        array $executors,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ): array;
}
