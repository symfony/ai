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
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\AiBundle\Profiler\TraceableAgent;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MockClock;

final class TraceableAgentTest extends TestCase
{
    public function testDataAreCollectedWhenCallingAgent()
    {
        $clock = new MockClock('2020-01-01 10:00:00');

        $traceableAgent = new TraceableAgent(new MockAgent([
            'Hello there' => 'General Kenobi',
        ]), $clock);

        $messageBag = new MessageBag(
            Message::ofUser('Hello there'),
        );

        $traceableAgent->call($messageBag);

        $this->assertCount(1, $traceableAgent->calls);
        $this->assertEquals([
            [
                'messages' => $messageBag,
                'options' => [],
                'capabilities' => [],
                'called_at' => $clock->now(),
            ],
        ], $traceableAgent->calls);
    }
}
