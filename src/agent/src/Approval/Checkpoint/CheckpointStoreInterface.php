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
 * Storage contract for persisting and retrieving execution checkpoints.
 *
 * @author Saiful Islam <saif012@gmail.com>
 */
interface CheckpointStoreInterface
{
    /**
     * Persists an execution checkpoint.
     */
    public function save(ExecutionCheckpoint $checkpoint): void;

    /**
     * Retrieves an execution checkpoint by its ID.
     */
    public function get(string $id): ?ExecutionCheckpoint;

    /**
     * Removes an execution checkpoint.
     */
    public function remove(string $id): void;

    /**
     * Returns all active (non-expired) checkpoints.
     *
     * @return ExecutionCheckpoint[]
     */
    public function all(): array;
}
