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
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateNormalizer;
use Symfony\AI\Agent\Workflow\WorkflowStoreInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CacheWorkflowStore implements WorkflowStoreInterface, ManagedWorkflowStoreInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
        private readonly string $key = 'workflow_',
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new WorkflowStateNormalizer(),
        ], [new JsonEncoder()]),
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
        $this->cache->get($this->getKey($state->getId()), function (ItemInterface $item) use ($state) {
            $item->expiresAfter($this->ttl);

            return $this->serializer->serialize($state, 'json');
        });
    }

    public function load(string $id): ?WorkflowStateInterface
    {
        $item = $this->cache->getItem($this->getKey($id));

        if (!$item->isHit()) {
            return null;
        }

        return $this->serializer->deserialize($item->get(), WorkflowStateInterface::class, 'json');
    }

    public function remove(string $id): void
    {
        $this->cache->deleteItem($this->getKey($id));
    }

    private function getKey(string $id): string
    {
        return $this->key.$id;
    }
}
