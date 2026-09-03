<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Approval\Checkpoint;

/**
 * In-memory storage for execution checkpoints, primarily used for testing and stateless runs.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
final class InMemoryCheckpointStore implements CheckpointStoreInterface
{
    /**
     * @var array<string, ExecutionCheckpoint>
     */
    private array $checkpoints = [];

    public function save(ExecutionCheckpoint $checkpoint): void
    {
        $this->checkpoints[$checkpoint->getId()] = $checkpoint;
    }

    public function get(string $id): ?ExecutionCheckpoint
    {
        $checkpoint = $this->checkpoints[$id] ?? null;
        if (null === $checkpoint) {
            return null;
        }

        if ($checkpoint->isExpired()) {
            unset($this->checkpoints[$id]);

            return null;
        }

        return $checkpoint;
    }

    public function remove(string $id): void
    {
        unset($this->checkpoints[$id]);
    }

    /**
     * @return ExecutionCheckpoint[]
     */
    public function all(): array
    {
        $active = [];
        foreach ($this->checkpoints as $id => $checkpoint) {
            if ($checkpoint->isExpired()) {
                unset($this->checkpoints[$id]);
                continue;
            }

            $active[] = $checkpoint;
        }

        return $active;
    }
}
