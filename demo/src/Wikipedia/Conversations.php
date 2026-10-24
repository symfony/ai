<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Wikipedia;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Keeps the conversations outside of the session, since the worker doing the research has none.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Conversations
{
    private const CACHE_PREFIX = 'wikipedia-chat-';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public static function topic(string $id): string
    {
        return 'https://symfony.com/ai/demo/wikipedia/'.$id;
    }

    public function load(string $id): MessageBag
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX.$id);

        return $item->isHit() ? $item->get() : new MessageBag();
    }

    public function save(string $id, MessageBag $messages): void
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX.$id);
        $item->set($messages)->expiresAfter(self::CACHE_TTL);

        $this->cache->save($item);
    }

    public function delete(string $id): void
    {
        $this->cache->deleteItem(self::CACHE_PREFIX.$id);
    }
}
