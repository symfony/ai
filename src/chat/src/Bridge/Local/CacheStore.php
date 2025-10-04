<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Local;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class CacheStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private string $cacheKey,
        private int $ttl = 86400,
    ) {
        if (!interface_exists(CacheItemPoolInterface::class)) {
            throw new RuntimeException('For using the CacheStore as message store, a PSR-6 cache implementation is required. Try running "composer require symfony/cache" or another PSR-6 compatible cache.');
        }
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
