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
 * Randomises the platform selection order.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class RandomStrategy implements PlatformSelectionStrategy
{
    /**
     * @param array<PlatformCapacity> $platforms
     */
    public function order(array $platforms): iterable
    {
        shuffle($platforms);

        return $platforms;
    }
}
