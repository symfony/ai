<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LoadBalanced\Strategy;

use Symfony\AI\Platform\Bridge\LoadBalanced\PlatformCapacity;

/**
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
interface PlatformSelectionStrategy
{
    /**
     * Orders the platforms for selection priority.
     *
     * @param array<PlatformCapacity> $platforms
     *
     * @return iterable<PlatformCapacity>
     */
    public function order(array $platforms): iterable;
}
