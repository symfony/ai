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

use Symfony\Component\Semaphore\SemaphoreInterface;

/**
 * A capacity provider that limits concurrency using semaphore.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class ConcurrencyLimitedProvider implements CapacityProvider
{
    public function __construct(private readonly SemaphoreInterface $semaphore)
    {
    }

    public function tryAcquire(): bool
    {
        return $this->semaphore->acquire();
    }

    public function release(): void
    {
        $this->semaphore->release();
    }
}
