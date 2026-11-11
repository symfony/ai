<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LoadBalanced;

use Symfony\AI\Platform\Bridge\LoadBalanced\Provider\CapacityProvider;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Pairs a platform with its capacity provider for load balancing.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class PlatformCapacity
{
    public function __construct(
        public readonly PlatformInterface $platform,
        public readonly CapacityProvider $capacityProvider,
        public readonly ?string $model = null,
    ) {
    }
}
