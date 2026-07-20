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

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Records every call made to the decorated store, for the profiler.
 *
 * This decorator statically implements {@see ListableWorkflowStateStoreInterface} for transparency,
 * but its constructor only guarantees the WorkflowStateStoreInterface&ManagedWorkflowStateStoreInterface
 * intersection, so {@see list()} throws when the decorated store is not listable. Consumers must
 * unwrap via {@see getDecoratedStore()} before an `instanceof` capability check, as the workflow
 * commands do, rather than probing the decorator directly.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type WorkflowStateStoreData array{
 *     method: string,
 *     state?: WorkflowStateInterface,
 *     id?: string,
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableWorkflowStateStore implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface, ListableWorkflowStateStoreInterface, ResetInterface
{
    /**
     * @var WorkflowStateStoreData[]
     */
    private array $calls = [];

    public function __construct(
        private readonly WorkflowStateStoreInterface&ManagedWorkflowStateStoreInterface $store,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function setup(): void
    {
        $this->calls[] = [
            'method' => 'setup',
            'called_at' => $this->clock->now(),
        ];

        $this->store->setup();
    }

    public function drop(): void
    {
        $this->calls[] = [
            'method' => 'drop',
            'called_at' => $this->clock->now(),
        ];

        $this->store->drop();
    }

    public function save(WorkflowStateInterface $state): void
    {
        $this->calls[] = [
            'method' => 'save',
            'state' => $state,
            'called_at' => $this->clock->now(),
        ];

        $this->store->save($state);
    }

    public function load(string $id): WorkflowStateInterface
    {
        $this->calls[] = [
            'method' => 'load',
            'id' => $id,
            'called_at' => $this->clock->now(),
        ];

        return $this->store->load($id);
    }

    public function has(string $id): bool
    {
        $this->calls[] = [
            'method' => 'has',
            'id' => $id,
            'called_at' => $this->clock->now(),
        ];

        return $this->store->has($id);
    }

    public function delete(string $id): void
    {
        $this->calls[] = [
            'method' => 'delete',
            'id' => $id,
            'called_at' => $this->clock->now(),
        ];

        $this->store->delete($id);
    }

    public function list(): iterable
    {
        $this->calls[] = [
            'method' => 'list',
            'called_at' => $this->clock->now(),
        ];

        if (!$this->store instanceof ListableWorkflowStateStoreInterface) {
            throw new RuntimeException('The decorated workflow state store does not support listing.');
        }

        return $this->store->list();
    }

    public function getDecoratedStore(): WorkflowStateStoreInterface
    {
        return $this->store;
    }

    /**
     * @return WorkflowStateStoreData[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
