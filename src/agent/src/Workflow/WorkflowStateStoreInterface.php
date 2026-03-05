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

use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;

/**
 * Persists workflow state across executions.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface WorkflowStateStoreInterface
{
    public function save(WorkflowStateInterface $state): void;

    /**
     * @param non-empty-string $id
     *
     * @throws WorkflowStateNotFoundException When no state exists for the given id
     */
    public function load(string $id): WorkflowStateInterface;

    /**
     * @param non-empty-string $id
     */
    public function has(string $id): bool;

    /**
     * @param non-empty-string $id
     */
    public function delete(string $id): void;
}
