<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Store\Distance\DistanceCalculator;
use Symfony\AI\Store\Distance\DistanceStrategy;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class StoreFactory
{
    public static function create(
        CacheInterface&CacheItemPoolInterface $cache,
        string $key,
        string $strategy = 'cosine',
    ): ManagedStoreInterface&StoreInterface {
        return new Store($cache, new DistanceCalculator(DistanceStrategy::from($strategy)), $key);
    }
}
