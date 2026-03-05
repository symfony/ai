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
 * Executor that wraps a callable in a PHP Fiber for cooperative multitasking.
 *
 * The callable receives the WorkflowState and place name, and must return
 * a WorkflowState. It may call Fiber::suspend() to yield control.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FiberExecutor implements ExecutorInterface
{
    /**
     * @param \Closure(WorkflowStateInterface, string): WorkflowStateInterface $callback
     */
    public function __construct(
        private readonly \Closure $callback,
    ) {
    }

    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface
    {
        $fiber = new \Fiber($this->callback);

        try {
            $fiber->start($state, $place);

            while (!$fiber->isTerminated()) {
                $fiber->resume();
            }

            $result = $fiber->getReturn();
        } catch (\Throwable $e) {
            throw new WorkflowExecutorException(\sprintf('Fiber execution failed at place "%s": %s.', $place, $e->getMessage()), 0, $e);
        }

        if (!$result instanceof WorkflowStateInterface) {
            throw new WorkflowExecutorException(\sprintf('FiberExecutor callback must return a WorkflowState, got "%s".', get_debug_type($result)));
        }

        return $result;
    }
}
