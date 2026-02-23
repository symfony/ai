<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Capability;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Capability\InputDelayCapability;

final class InputDelayCapabilityTest extends TestCase
{
    public function testDelayFor()
    {
        $stamp = InputDelayCapability::delayFor(\DateInterval::createFromDateString('30 seconds'));
        $this->assertSame(30000, $stamp->getDelay());
        $stamp = InputDelayCapability::delayFor(\DateInterval::createFromDateString('30 minutes'));
        $this->assertSame(1800000, $stamp->getDelay());
        $stamp = InputDelayCapability::delayFor(\DateInterval::createFromDateString('30 hours'));
        $this->assertSame(108000000, $stamp->getDelay());

        $stamp = InputDelayCapability::delayFor(\DateInterval::createFromDateString('-5 seconds'));
        $this->assertSame(-5000, $stamp->getDelay());
    }

    public function testDelayUntil()
    {
        $stamp = InputDelayCapability::delayUntil(new \DateTimeImmutable('+30 seconds'));
        $this->assertSame(30000, $stamp->getDelay());

        $stamp = InputDelayCapability::delayUntil(new \DateTimeImmutable('-5 seconds'));
        $this->assertSame(-5000, $stamp->getDelay());
    }
}
