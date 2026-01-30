<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $cacheKey = '_message_store_cache',
        private readonly int $ttl = 86400,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        $item = $this->cache->getItem($this->cacheKey);

        $item->set([]);
        $item->expiresAfter($this->ttl);

        $this->cache->save($item);
    }

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        $item = $this->cache->getItem($identifier ?? $this->cacheKey);

        $item->set($this->serializer->normalize($messages));
        $item->expiresAfter($this->ttl);

        $this->cache->save($item);
    }

    public function load(?string $identifier = null): MessageBag
    {
        $item = $this->cache->getItem($identifier ?? $this->cacheKey);

        $payload = $item->isHit() ? $item->get() : [];

        return $this->serializer->denormalize($payload, MessageBag::class);
    }

    public function drop(): void
    {
        $this->cache->deleteItem($this->cacheKey);
    }
}
