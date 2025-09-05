<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Chat\MessageStore;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class CacheStore implements MessageStoreInterface
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private string $id = '_message_store_cache',
        private int $ttl = 86400,
    ) {
        if (!interface_exists(CacheItemPoolInterface::class)) {
            throw new RuntimeException('For using the CacheStore as message store, a PSR-6 cache implementation is required. Try running "composer require symfony/cache" or another PSR-6 compatible cache.');
        }
    }

    public function save(MessageBag $messages, ?string $id = null): void
    {
        $item = $this->cache->getItem($id ?? $this->id);

        $item->set($messages);
        $item->expiresAfter($this->ttl);

        $this->cache->save($item);
    }

    public function load(?string $id = null): MessageBag
    {
        $item = $this->cache->getItem($id ?? $this->id);

        return $item->isHit() ? $item->get() : new MessageBag();
    }

    public function clear(?string $id = null): void
    {
        $this->cache->deleteItem($id ?? $this->id);
    }

    public function getId(): string
    {
        return $this->id;
    }
}
