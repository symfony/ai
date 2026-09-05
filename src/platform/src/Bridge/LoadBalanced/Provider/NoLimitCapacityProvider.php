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

/**
 * A capacity provider with no restrictions - always allows acquisition.
 *
 * Use this for platforms that don't need rate limiting or concurrency control.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class NoLimitCapacityProvider implements CapacityProvider
{
    public function tryAcquire(): bool
    {
        return true;
    }

    public function release(): void
    {
        // no-op
    }
}
