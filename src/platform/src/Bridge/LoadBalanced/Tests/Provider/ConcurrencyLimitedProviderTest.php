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
use Symfony\AI\Platform\Bridge\LoadBalanced\Provider\ConcurrencyLimitedProvider;
use Symfony\Component\Semaphore\SemaphoreInterface;

class ConcurrencyLimitedProviderTest extends TestCase
{
    public function testTryAcquireCallsSemaphoreAcquire()
    {
        $mockSemaphore = $this->createMock(SemaphoreInterface::class);

        $composite = new ConcurrencyLimitedProvider($mockSemaphore);

        $mockSemaphore
            ->expects($this->once())
            ->method('acquire');

        $composite->tryAcquire();
    }

    public function testReleaseCallsSemaphoreRelease()
    {
        $mockSemaphore = $this->createMock(SemaphoreInterface::class);

        $composite = new ConcurrencyLimitedProvider($mockSemaphore);

        $mockSemaphore
            ->expects($this->once())
            ->method('release');

        $composite->release();
    }
}
