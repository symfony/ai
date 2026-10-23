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
use Symfony\AI\Platform\Bridge\LoadBalanced\Provider\CompositeCapacityProvider;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Semaphore\SemaphoreInterface;

class CompositeCapacityProviderTest extends TestCase
{
    public function testTryAcquireFailsWhenSemaphoreIsUnavailable()
    {
        $mockSemaphore = $this->createMock(SemaphoreInterface::class);

        $mockSemaphore
            ->expects($this->once())
            ->method('acquire')
            ->willReturn(false);

        $composite = new CompositeCapacityProvider(
            $mockSemaphore,
            $this->createMock(LimiterInterface::class)
        );

        $this->assertFalse($composite->tryAcquire());
    }

    public function testTryAcquireFailsWhenLimiterHasReachedCapacity()
    {
        $mockSemaphore = $this->createMock(SemaphoreInterface::class);
        $mockLimiter = $this->createMock(LimiterInterface::class);
        $mockRateLimit = $this->createMock(RateLimit::class);

        $mockSemaphore
            ->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $mockLimiter
            ->expects($this->once())
            ->method('consume')
            ->willReturn($mockRateLimit);

        $mockRateLimit
            ->expects($this->once())
            ->method('isAccepted')
            ->willReturn(false);

        $composite = new CompositeCapacityProvider(
            $mockSemaphore,
            $mockLimiter
        );

        $this->assertFalse($composite->tryAcquire());
    }

    public function testTryAcquireSucceedsWhenSemaphoreAndLimiterAreAvailable()
    {
        $mockSemaphore = $this->createMock(SemaphoreInterface::class);
        $mockLimiter = $this->createMock(LimiterInterface::class);
        $mockRateLimit = $this->createMock(RateLimit::class);

        $mockSemaphore
            ->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $mockLimiter
            ->expects($this->once())
            ->method('consume')
            ->willReturn($mockRateLimit);

        $mockRateLimit
            ->expects($this->once())
            ->method('isAccepted')
            ->willReturn(true);

        $composite = new CompositeCapacityProvider(
            $mockSemaphore,
            $mockLimiter
        );

        $this->assertTrue($composite->tryAcquire());
    }

    public function testReleaseCallsSemaphoreRelease()
    {
        $mockSemaphore = $this->createMock(SemaphoreInterface::class);

        $composite = new CompositeCapacityProvider(
            $mockSemaphore,
            $this->createMock(LimiterInterface::class)
        );

        $mockSemaphore
            ->expects($this->once())
            ->method('release');

        $composite->release();
    }
}
