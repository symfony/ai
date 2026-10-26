<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * Keeps each conversation in its own cache item.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageStore implements MessageStoreInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $cacheKey = '_message_store_cache',
        private readonly int $ttl = 86400,
    ) {
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $item = $this->cache->getItem($this->key($id));

        return $item->isHit() ? $item->get() : MessageBag::forId($id);
    }

    public function save(MessageBag $messages): void
    {
        ConflictDetector::assertContinues($this->load($messages->getId()), $messages);

        $this->cache->save($this->cache->getItem($this->key($messages->getId()))->set($messages)->expiresAfter($this->ttl));
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $this->cache->deleteItem($this->key($id));
    }

    private function key(AbstractUid $id): string
    {
        return $this->cacheKey.'.'.$id->toRfc4122();
    }
}
