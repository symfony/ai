<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TraceableWorkflowStateStore implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface
{
    public function setup(array $options = []): void
    {
        // TODO: Implement setup() method.
    }

    public function drop(array $options = []): void
    {
        // TODO: Implement drop() method.
    }

    public function save(WorkflowStateInterface $state): void
    {
        // TODO: Implement save() method.
    }

    public function load(string $id): WorkflowStateInterface
    {
        // TODO: Implement load() method.
    }

    public function has(string $id): bool
    {
        // TODO: Implement has() method.
    }

    public function delete(string $id): void
    {
        // TODO: Implement delete() method.
    }
}
