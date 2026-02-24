<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LoadBalanced\Tests\Strategy;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LoadBalanced\PlatformCapacity;
use Symfony\AI\Platform\Bridge\LoadBalanced\Provider\NoLimitCapacityProvider;
use Symfony\AI\Platform\Bridge\LoadBalanced\Strategy\RandomStrategy;
use Symfony\AI\Platform\PlatformInterface;

class RandomStrategyTest extends TestCase
{
    public function testRandomStrategy()
    {
        $platforms = [
            new PlatformCapacity($this->createMock(PlatformInterface::class), new NoLimitCapacityProvider()),
            new PlatformCapacity($this->createMock(PlatformInterface::class), new NoLimitCapacityProvider()),
            new PlatformCapacity($this->createMock(PlatformInterface::class), new NoLimitCapacityProvider()),
        ];

        $strategy = new RandomStrategy();
        $result = iterator_to_array($strategy->order($platforms));

        $this->assertCount(3, $result);
        $this->assertEqualsCanonicalizing($platforms, $result);
    }
}
