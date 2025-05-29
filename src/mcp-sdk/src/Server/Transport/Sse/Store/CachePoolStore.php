<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server\Transport\Sse\Store;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\McpSdk\Server\Transport\Sse\StoreInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CachePoolStore implements StoreInterface
{
    public function __construct(
        private CacheItemPoolInterface $cachePool,
    ) {
    }

    public function push(Uuid $id, string $message): void
    {
        $item = $this->cachePool->getItem($this->getCacheKey($id));

        $messages = $item->isHit() ? $item->get() : [];
        $messages[] = $message;
        $item->set($messages);

        $this->cachePool->save($item);
    }

    public function pop(Uuid $id): ?string
    {
        $item = $this->cachePool->getItem($this->getCacheKey($id));

        if (!$item->isHit()) {
            return null;
        }

        $messages = $item->get();
        $message = array_shift($messages);

        $item->set($messages);
        $this->cachePool->save($item);

        return $message;
    }

    public function remove(Uuid $id): void
    {
        $this->cachePool->deleteItem($this->getCacheKey($id));
    }

    private function getCacheKey(Uuid $id): string
    {
        return 'message_'.$id->toRfc4122();
    }
}
