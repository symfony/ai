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
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface ExecutorInterface
{
    /**
     * Execute work for the given place and return the updated state.
     *
     * The executor MAY set '_next_transition' in the state to influence
     * which transition is applied after execution.
     *
     * @param non-empty-string $place The current workflow place being executed
     *
     * @throws WorkflowExecutorException When execution fails
     */
    public function execute(WorkflowStateInterface $state, string $place): WorkflowStateInterface;
}
