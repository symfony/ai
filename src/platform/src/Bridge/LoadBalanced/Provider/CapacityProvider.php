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
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
interface CapacityProvider
{
    public function tryAcquire(): bool;

    public function release(): void;
}
