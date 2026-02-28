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
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CacheWorkflowStore implements WorkflowStoreInterface, ManagedWorkflowStoreInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
        private readonly string $key = 'workflow_',
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->cache->get($this->key, static fn () => []);
    }

    public function drop(array $options = []): void
    {
        $this->cache->clear();
    }

    public function save(WorkflowStateInterface $state): void
    {
        $item = $this->cache->getItem($this->getKey($state->getId()));

        $item->set($state->toArray());
        $item->expiresAfter($this->ttl);

        $this->cache->save($item);
    }

    public function load(string $id): ?WorkflowStateInterface
    {
        $item = $this->cache->getItem($this->getKey($id));

        if (!$item->isHit()) {
            return null;
        }

        return WorkflowState::fromArray($item->get());
    }

    private function getKey(string $id): string
    {
        return $this->key.$id;
    }
}
