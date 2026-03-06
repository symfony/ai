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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\TransitionResolutionException;
use Symfony\AI\Agent\Exception\WorkflowExecutorException;
use Symfony\AI\Agent\Exception\WorkflowGuardException;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface AgentWorkflowInterface
{
    /**
     * Run the workflow to completion from the given initial state.
     *
     * @throws InvalidArgumentException       When a place has no registered executor
     * @throws WorkflowExecutorException      When an executor fails
     * @throws WorkflowGuardException         When a guard rejects execution at a place
     * @throws TransitionResolutionException  When transition resolution fails
     * @throws WorkflowStateNotFoundException When resuming a non-existent state
     */
    public function run(WorkflowStateInterface $initialState): WorkflowStateInterface;

    /**
     * @param non-empty-string $id The workflow state identifier
     *
     * @throws WorkflowStateNotFoundException When no persisted state exists for the given id
     */
    public function resume(string $id): WorkflowStateInterface;
}
