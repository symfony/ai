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

use Symfony\AI\Agent\Workflow\ManagedWorkflowStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type WorkflowStoreData array{
 *     method: string,
 *     state?: WorkflowStateInterface,
 *     id?: string,
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableWorkflowStore implements WorkflowStoreInterface, ManagedWorkflowStoreInterface
{
    /**
     * @var WorkflowStoreData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly WorkflowStoreInterface&ManagedWorkflowStoreInterface $store,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->calls[] = [
            'method' => 'setup',
            'called_at' => $this->clock->now(),
        ];

        $this->store->setup($options);
    }

    public function drop(array $options = []): void
    {
        $this->calls[] = [
            'method' => 'drop',
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

    public function load(string $id): ?WorkflowStateInterface
    {
        $this->calls[] = [
            'method' => 'load',
            'id' => $id,
            'called_at' => $this->clock->now(),
        ];

        return $this->store->load($id);
    }
}
