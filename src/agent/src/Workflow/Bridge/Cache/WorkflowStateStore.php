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
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\WorkflowStateNotFoundException;
use Symfony\AI\Agent\Workflow\ManagedWorkflowStateStoreInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateNormalizer;
use Symfony\AI\Agent\Workflow\WorkflowStateStoreInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStateStore implements WorkflowStateStoreInterface, ManagedWorkflowStateStoreInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $cacheKeyPrefix = '_workflow_state_',
        private readonly int $ttl = 86400,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new WorkflowStateNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }
    }

    public function drop(array $options = []): void
    {
        $this->cache->clear();
    }

    public function save(WorkflowStateInterface $state): void
    {
        $item = $this->cache->getItem($this->cacheKeyPrefix.$state->getId());

        $item->set($this->serializer->serialize($state, 'json'));
        $item->expiresAfter($this->ttl);

        $this->cache->save($item);
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
    }
}
