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

use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;

final class RedisWorkflowStore implements WorkflowStoreInterface
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly int $ttl = 3600,
        private readonly string $prefix = 'workflow:',
        private readonly int $lockTimeout = 10,
    ) {
    }

    public function save(WorkflowStateInterface $state): void
    {
        $key = $this->getKey($state->getId());
        $lockKey = $key.':lock';

        // Acquérir un lock distribué
        $lockAcquired = $this->redis->set(
            $lockKey,
            '1',
            ['NX', 'EX' => $this->lockTimeout]
        );

        if (!$lockAcquired) {
            throw new \RuntimeException('Could not acquire lock for workflow '.$state->getId());
        }

        try {
            $this->redis->setex(
                $key,
                $this->ttl,
                json_encode($state->toArray(), \JSON_THROW_ON_ERROR)
            );
        } finally {
            $this->redis->del($lockKey);
        }
    }

    public function load(string $id): ?WorkflowStateInterface
    {
        $key = $this->getKey($id);
        $data = $this->redis->get($key);

        if (false === $data) {
            return null;
        }

        return WorkflowState::fromArray(json_decode($data, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function delete(string $id): void
    {
        $this->redis->del($this->getKey($id));
    }

    public function exists(string $id): bool
    {
        return (bool) $this->redis->exists($this->getKey($id));
    }

    private function getKey(string $id): string
    {
        return $this->prefix.$id;
    }
}
