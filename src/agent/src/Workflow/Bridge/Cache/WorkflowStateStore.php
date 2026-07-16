<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Bridge\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\AbstractWorkflowStateStore;
use Symfony\AI\Agent\Workflow\ListableWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * Workflow state store backed by a PSR-6 cache pool.
 *
 * Persisted state ids are tracked in an index entry so that {@see drop()} only
 * removes workflow entries instead of clearing the whole pool.
 *
 * The index is maintained with an unsynchronized read-modify-write, so concurrent saves of different
 * ids to the same pool can race. It is only an optimization for {@see list()}/{@see drop()} — {@see
 * list()} verifies each id still exists — so a lost index update at most hides an id from those two
 * operations and never corrupts a stored state.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStateStore extends AbstractWorkflowStateStore implements ListableWorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $cacheKeyPrefix = '_workflow_state_',
        private readonly int $ttl = 86400,
    ) {
        parent::__construct();
    }

    public function setup(): void
    {
    }

    public function drop(): void
    {
        $item = $this->cache->getItem($this->indexKey());
        $cached = $item->isHit() ? $item->get() : [];
        $ids = \is_array($cached) ? $cached : [];

        $keys = array_map(fn ($id): string => $this->cacheKeyPrefix.$id, $ids);
        $keys[] = $this->indexKey();

        $this->cache->deleteItems($keys);
    }

    public function save(WorkflowStateInterface $state): void
    {
        $item = $this->cache->getItem($this->cacheKeyPrefix.$state->getId());
        $item->set($this->serializer->serialize($state, 'json'));
        $item->expiresAfter($this->ttl);

        $this->cache->save($item);

        $this->registerId($state->getId());
    }

    public function load(string $id): WorkflowStateInterface
    {
        $item = $this->cache->getItem($this->cacheKeyPrefix.$id);

        if (!$item->isHit()) {
            throw new WorkflowStateNotFoundException(\sprintf('Workflow state with id "%s" not found.', $id));
        }

        return $this->serializer->deserialize($item->get(), WorkflowStateInterface::class, 'json');
    }

    public function has(string $id): bool
    {
        return $this->cache->getItem($this->cacheKeyPrefix.$id)->isHit();
    }

    public function delete(string $id): void
    {
        $this->cache->deleteItem($this->cacheKeyPrefix.$id);
        $this->unregisterId($id);
    }

    public function list(): iterable
    {
        $item = $this->cache->getItem($this->indexKey());

        if (!$item->isHit()) {
            return [];
        }

        $cached = $item->get();

        return \is_array($cached) ? array_values(array_filter($cached, 'is_string')) : [];
    }

    private function indexKey(): string
    {
        return $this->cacheKeyPrefix.'index';
    }

    private function registerId(string $id): void
    {
        $item = $this->cache->getItem($this->indexKey());
        $cached = $item->isHit() ? $item->get() : [];
        $ids = \is_array($cached) ? $cached : [];

        if (\in_array($id, $ids, true)) {
            return;
        }

        $ids[] = $id;
        $item->set($ids);

        $this->cache->save($item);
    }

    private function unregisterId(string $id): void
    {
        $item = $this->cache->getItem($this->indexKey());

        if (!$item->isHit()) {
            return;
        }

        $cached = $item->get();
        $ids = \is_array($cached) ? $cached : [];

        $item->set(array_values(array_filter($ids, static fn ($known): bool => $known !== $id)));

        $this->cache->save($item);
    }
}
