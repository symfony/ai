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
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type WorkflowStateStoreData array{
 *     method: string,
 *     options?: array<string, mixed>,
 *     state?: WorkflowStateInterface,
 *     id?: string,
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableWorkflowStateStore implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface, ResetInterface
{
    /**
     * @var WorkflowStateStoreData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly WorkflowStateStoreInterface&ManagedWorkflowStateStoreInterface $store,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->calls[] = [
            'method' => 'setup',
            'options' => $options,
            'called_at' => $this->clock->now(),
        ];

        $this->store->setup($options);
    }

    public function drop(array $options = []): void
    {
        $this->calls[] = [
            'method' => 'drop',
            'options' => $options,
            'called_at' => $this->clock->now(),
        ];

        $this->store->drop($options);
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

    public function reset(): void
    {
        $this->calls = [];
    }
}
