<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\InMemory;

use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\AbstractWorkflowStateStore;
use Symfony\AI\Agent\Workflow\ListableWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * In-memory workflow state store, intended for testing and single-request runs.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStateStore extends AbstractWorkflowStateStore implements ListableWorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface, ResetInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $states = [];

    public function setup(): void
    {
    }

    public function drop(): void
    {
        $this->states = [];
    }

    public function save(WorkflowStateInterface $state): void
    {
        $this->states[$state->getId()] = $this->serializer->normalize($state);
    }

    public function load(string $id): WorkflowStateInterface
    {
        if (!$this->has($id)) {
            throw new WorkflowStateNotFoundException(\sprintf('Workflow state with id "%s" not found.', $id));
        }

        return $this->serializer->denormalize($this->states[$id], WorkflowStateInterface::class);
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->states);
    }

    public function delete(string $id): void
    {
        unset($this->states[$id]);
    }

    public function list(): iterable
    {
        return array_keys($this->states);
    }

    public function reset(): void
    {
        $this->states = [];
    }
}
