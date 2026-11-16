<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LoadBalanced\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LoadBalanced\Provider\RateLimitedCapacityProvider;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;

class RateLimitedCapacityProviderTest extends TestCase
{
    public function testTryAcquireCallsLimiterConsume()
    {
        $mockLimiter = $this->createMock(LimiterInterface::class);
        $mockRateLimit = $this->createMock(RateLimit::class);

        $mockLimiter
            ->expects($this->once())
            ->method('consume')
            ->willReturn($mockRateLimit);

        $mockRateLimit
            ->expects($this->once())
            ->method('isAccepted');

        $capacityProvider = new RateLimitedCapacityProvider($mockLimiter);

        $this->assertFalse($capacityProvider->tryAcquire());
    }
}
