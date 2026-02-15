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
use Symfony\AI\Agent\Policy\DelayPolicyHandler;
use Symfony\AI\Agent\Policy\InputDelayPolicy;
use Symfony\AI\AiBundle\Profiler\TraceablePolicyHandler;
use Symfony\AI\Platform\Message\MessageBag;

final class TraceablePolicyHandlerTest extends TestCase
{
    public function testDataAreCollected()
    {
        $traceablePolicyHandler = new TraceablePolicyHandler(new DelayPolicyHandler());

        $bag = new MessageBag();

        $traceablePolicyHandler->support(new InputDelayPolicy(10));
        $traceablePolicyHandler->handle($bag, [], new InputDelayPolicy(10));

        $this->assertCount(2, $traceablePolicyHandler->calls);
        $this->assertSame([
            'method' => 'support',
            'policy' => InputDelayPolicy::class,
        ], $traceablePolicyHandler->calls[0]);
        $this->assertSame([
            'method' => 'handle',
            'messages' => $bag,
            'options' => [],
            'policy' => InputDelayPolicy::class,
        ], $traceablePolicyHandler->calls[1]);
    }
}
