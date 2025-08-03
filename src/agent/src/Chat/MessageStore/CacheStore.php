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
use Symfony\AI\Agent\Chat\SessionAwareMessageStoreInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

final readonly class CacheStore implements MessageStoreInterface, SessionAwareMessageStoreInterface
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

    public function save(MessageBagInterface $messages): void
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

    public function clear(): void
    {
        $this->cache->deleteItem($this->cacheKey);
    }

    public function withSession(AbstractUid&TimeBasedUidInterface $session): MessageStoreInterface&SessionAwareMessageStoreInterface
    {
        return new $this($this->cache, $session->toRfc4122(), $this->ttl);
    }
}
