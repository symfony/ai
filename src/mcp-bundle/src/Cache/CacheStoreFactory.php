<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Cache;

use Mcp\Server\Session\Psr16SessionStore;
use Mcp\Server\Session\Psr6SessionStore;
use Mcp\Server\Session\SessionStoreInterface;
use Mcp\Server\Subscription\NotificationBusInterface;
use Mcp\Server\Subscription\Psr16NotificationBus;
use Mcp\Server\Subscription\Psr6NotificationBus;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Picks the PSR-6 or the PSR-16 implementation for the configured cache service at runtime,
 * since an alias or a factory can hide the class of that service when the container compiles.
 *
 * @internal
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CacheStoreFactory
{
    public static function sessionStore(CacheItemPoolInterface|CacheInterface $cache, string $prefix, int $ttl): SessionStoreInterface
    {
        if ($cache instanceof CacheItemPoolInterface) {
            return new Psr6SessionStore($cache, $prefix, $ttl);
        }

        return new Psr16SessionStore($cache, $prefix, $ttl);
    }

    public static function notificationBus(CacheItemPoolInterface|CacheInterface $cache, string $prefix, LoggerInterface $logger): NotificationBusInterface
    {
        if ($cache instanceof CacheItemPoolInterface) {
            return new Psr6NotificationBus($cache, $prefix, logger: $logger);
        }

        return new Psr16NotificationBus($cache, $prefix, logger: $logger);
    }
}
