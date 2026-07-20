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

use Symfony\AI\Agent\Exception\WorkflowExecutorException;

/**
 * An executor that can split its work into a non-blocking dispatch and a blocking settle.
 *
 * When several places are active at once, the engine dispatches every async executor before
 * settling any of them, so their I/O (e.g. platform requests) overlaps.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface AsyncExecutorInterface extends ExecutorInterface
{
    /**
     * Starts the work for the given place without blocking on its result.
     *
     * @param non-empty-string $place
     *
     * @throws WorkflowExecutorException When the work cannot be started
     */
    public function dispatch(WorkflowStateInterface $state, string $place): PendingExecution;

    /**
     * Awaits previously dispatched work and returns the updated state.
     *
     * @param non-empty-string $place
     *
     * @throws WorkflowExecutorException When execution fails
     */
    public function settle(WorkflowStateInterface $state, string $place, PendingExecution $pending): WorkflowStateInterface;
}
