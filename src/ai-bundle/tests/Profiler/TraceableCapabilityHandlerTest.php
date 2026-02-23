<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Capability\DelayCapabilityHandler;
use Symfony\AI\Agent\Capability\InputDelayCapability;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\AiBundle\Profiler\TraceableCapabilityHandler;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MockClock;

final class TraceableCapabilityHandlerTest extends TestCase
{
    public function testDataAreCollected()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $agent = new MockAgent();
        $traceableCapabilityHandler = new TraceableCapabilityHandler(new DelayCapabilityHandler(), $clock);

        $bag = new MessageBag();

        $traceableCapabilityHandler->support(new InputDelayCapability(10));
        $traceableCapabilityHandler->handle($agent, $bag, [], new InputDelayCapability(10));

        $this->assertCount(2, $traceableCapabilityHandler->calls);
        $this->assertEquals([
            'method' => 'support',
            'capability' => InputDelayCapability::class,
            'checked_at' => $clock->now(),
        ], $traceableCapabilityHandler->calls[0]);
        $this->assertEquals([
            'method' => 'handle',
            'agent' => $agent,
            'messages' => $bag,
            'options' => [],
            'capability' => InputDelayCapability::class,
            'handled_at' => $clock->now(),
        ], $traceableCapabilityHandler->calls[1]);
    }
}
