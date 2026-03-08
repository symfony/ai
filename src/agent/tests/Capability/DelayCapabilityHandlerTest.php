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
use Symfony\AI\Agent\Capability\DelayCapabilityHandler;
use Symfony\AI\Agent\Capability\InputCapabilityInterface;
use Symfony\AI\Agent\Capability\InputDelayCapability;
use Symfony\AI\Agent\Capability\OutputDelayCapability;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MockClock;

final class DelayCapabilityHandlerTest extends TestCase
{
    public function testHandlerSupport()
    {
        $handler = new DelayCapabilityHandler();

        $this->assertFalse($handler->support(new class implements InputCapabilityInterface {}));
        $this->assertTrue($handler->support(new InputDelayCapability(1)));
        $this->assertTrue($handler->support(new OutputDelayCapability(1)));
    }

    public function testHandlerCanHandleInputDelay()
    {
        $clock = new MockClock('01-01-2020 10:00:00');

        $handler = new DelayCapabilityHandler($clock);

        $handler->handle(new MockAgent(), new MessageBag(), [], new InputDelayCapability(10));

        $this->assertSame('2020-01-01 10:00:10', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testHandlerCanHandleOutputDelay()
    {
        $clock = new MockClock('01-01-2020 10:00:00');

        $handler = new DelayCapabilityHandler($clock);

        $handler->handle(new MockAgent(), new MessageBag(), [], new OutputDelayCapability(10));

        $this->assertSame('2020-01-01 10:00:10', $clock->now()->format('Y-m-d H:i:s'));
    }
}
