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
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $cacheKey = '_message_store_cache',
        private readonly int $ttl = 86400,
    ) {
    }

    public function setup(array $options = []): void
    {
        $item = $this->cache->getItem($this->cacheKey);

        $item->set(new MessageBag());
        $item->expiresAfter($this->ttl);

        $this->cache->save($item);
    }

    public function save(MessageBag $messages): void
    {
        $item = $this->cache->getItem($this->cacheKey);

        $item->set($messages);
        $item->expiresAfter($this->ttl);

        $this->cache->save($item);
    }

    public function load(): MessageBag
    {
        $item = $this->cache->getItem($this->cacheKey);

        return $item->isHit() ? $item->get() : new MessageBag();
    }

    public function drop(): void
    {
        $this->cache->deleteItem($this->cacheKey);
    }
}
