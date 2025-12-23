<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Failover;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FailoverPlatformFactory
{
    /**
     * @param PlatformInterface[] $platforms
     */
    public static function create(
        iterable $platforms,
        RateLimiterFactoryInterface $rateLimiterFactory,
        ClockInterface $clock = new MonotonicClock(),
        LoggerInterface $logger = new NullLogger(),
    ): PlatformInterface {
        return new FailoverPlatform(
            $platforms,
            $rateLimiterFactory,
            $clock,
            $logger,
        );
    }
}
