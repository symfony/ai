<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Executor;

use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Workflow\ExecutorInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * Executor that delegates to a callable.
 *
 * The callable receives the workflow state and the place name, and must
 * return a WorkflowState.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CallableExecutor implements ExecutorInterface
{
    /**
     * @param \Closure(WorkflowStateInterface, non-empty-string): WorkflowStateInterface $callback
     */
    public function __construct(
        private readonly \Closure $callback,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        try {
            $result = ($this->callback)($state, $place);
        } catch (\Throwable $e) {
            throw new WorkflowExecutorException(\sprintf('Callable execution failed at place "%s": "%s".', $place, $e->getMessage()), 0, $e);
        }

        if (!$result instanceof WorkflowStateInterface) {
            throw new WorkflowExecutorException(\sprintf('CallableExecutor callback must return a WorkflowState, got "%s".', get_debug_type($result)));
        }

        return $result;
    }
}
