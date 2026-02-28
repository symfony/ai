<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Store;

use Symfony\AI\Agent\Workflow\ManagedWorkflowStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class InMemoryWorkflowStore implements WorkflowStoreInterface, ManagedWorkflowStoreInterface
{
    /**
     * @var WorkflowStateInterface[]
     */
    private array $states = [];

    public function setup(array $options = []): void
    {
        $this->states = [];
    }

    public function drop(array $options = []): void
    {
        $this->states = [];
    }

    public function save(WorkflowStateInterface $state): void
    {
        $this->states[$state->getId()] = $state;
    }

    public function load(string $id): ?WorkflowStateInterface
    {
        return $this->states[$id] ?? null;
    }
}
