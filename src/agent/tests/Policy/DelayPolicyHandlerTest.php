<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Policy;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Policy\DelayPolicyHandler;
use Symfony\AI\Agent\Policy\InputDelayPolicy;
use Symfony\AI\Agent\Policy\InputPolicyInterface;
use Symfony\AI\Agent\Policy\OutputDelayPolicy;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MockClock;

final class DelayPolicyHandlerTest extends TestCase
{
    public function testHandlerSupport()
    {
        $handler = new DelayPolicyHandler();

        $this->assertFalse($handler->support(new class implements InputPolicyInterface {}));
        $this->assertTrue($handler->support(new InputDelayPolicy(1)));
        $this->assertTrue($handler->support(new OutputDelayPolicy(1)));
    }

    public function testHandlerCanHandleInputDelay()
    {
        $clock = new MockClock('01-01-2020 10:00:00');

        $handler = new DelayPolicyHandler($clock);

        $handler->handle(new MessageBag(), [], new InputDelayPolicy(10));

        $this->assertSame('2020-01-01 10:00:10', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testHandlerCanHandleOutputDelay()
    {
        $clock = new MockClock('01-01-2020 10:00:00');

        $handler = new DelayPolicyHandler($clock);

        $handler->handle(new MessageBag(), [], new OutputDelayPolicy(10));

        $this->assertSame('2020-01-01 10:00:10', $clock->now()->format('Y-m-d H:i:s'));
    }
}
