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
use Symfony\Component\Semaphore\SemaphoreInterface;

/**
 * A composite capacity provider that combines rate limiting and concurrency limiting.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class CompositeCapacityProvider implements CapacityProvider
{
    public function __construct(private readonly SemaphoreInterface $semaphore, private readonly LimiterInterface $limiter)
    {
    }

    public function tryAcquire(): bool
    {
        if (!$this->semaphore->acquire()) {
            return false;
        }

        if (!$this->limiter->consume()->isAccepted()) {
            $this->semaphore->release();

            return false;
        }

        return true;
    }

    public function release(): void
    {
        $this->semaphore->release();
    }
}
