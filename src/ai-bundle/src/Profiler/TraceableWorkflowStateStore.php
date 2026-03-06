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
 */
final class TraceableWorkflowStateStore implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface, ResetInterface
{
    public array $calls = [];

    public function __construct(
        private readonly WorkflowStateStoreInterface&ManagedWorkflowStateStoreInterface $store,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

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
