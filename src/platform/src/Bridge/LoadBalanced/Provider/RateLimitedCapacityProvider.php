<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LoadBalanced\Provider;

use Symfony\Component\RateLimiter\LimiterInterface;

/**
 * A capacity provider that limits usage with a rate limiter.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class RateLimitedCapacityProvider implements CapacityProvider
{
    public function __construct(private readonly LimiterInterface $limiter)
    {
    }

    public function tryAcquire(): bool
    {
        return $this->limiter->consume()->isAccepted();
    }

    public function release(): void
    {
        // no-op
    }
}
